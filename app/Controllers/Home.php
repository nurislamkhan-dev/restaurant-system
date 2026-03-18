<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class Home extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        helper(['form']);
        $session = session();
       

        if ($this->request->getMethod() === 'POST') {

            $rules = [
                'email'    => 'required|valid_email',
                'password' => 'required|min_length[4]',
            ];

            if (! $this->validate($rules)) {
                return view('login', [
                    'validation' => $this->validator
                ]);
            }

            $email    = trim($this->request->getPost('email'));
            $password = $this->request->getPost('password');

            $userModel = new UserModel();
            $user = $userModel->where('email', $email)->first();

            // ❌ user not found
            if (! $user) {
                return view('login', ['error' => 'User not found']);
            }

            // ❌ wrong password
            if (! password_verify($password, $user['password'])) {
                return view('login', ['error' => 'Wrong password']);
            }

            // ❌ inactive user
            if ($user['status'] !== 'active') {
                return view('login', ['error' => 'User inactive']);
            }

            // ✅ success login
            $session->set([
                'user_id'      => $user['id'],
                'user_name'    => $user['name'],
                'user_email'   => $user['email'],
                'is_logged_in' => true,
            ]);

            return redirect()->to('/dashboard');
        }

        return view('login');
    }

    public function dashboard()
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return redirect()->to('/');
        }

        return view('dashboard');
    }

    public function logout()
    {
        $session = session();
        $userId  = $session->get('user_id');

        $session->destroy();

        log_message('info', 'User logged out', ['user_id' => $userId]);

        return redirect()->to('/');
    }

    public function uberSandboxToken()
    {
        $session = session();
        if (! $session->get('is_logged_in')) {
            return $this->failUnauthorized('Unauthorized');
        }

        $clientId     = getenv('UBER_CLIENT_ID');
        $clientSecret = getenv('UBER_CLIENT_SECRET');
        $grantType    = getenv('GRANT_TYPE') ?: 'client_credentials';
        $scope        = getenv('UBER_SANDBOX_SCOPE') ?: (getenv('SCOPE') ?: 'eats.store eats.order');
        $scope        = trim($scope);
        $scope        = trim($scope, "\"'");

        if (! $clientId || ! $clientSecret) {
            return $this->failValidationErrors('Missing UBER_CLIENT_ID or UBER_CLIENT_SECRET in .env');
        }

        /** @var CURLRequest $http */
        $http = Services::curlrequest();

        try {
            $response = $http->post('https://sandbox-login.uber.com/oauth/v2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                // Avoid throwing exceptions on 4xx/5xx so we can display Uber's error body
                'http_errors' => false,
                'form_params' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => $grantType,
                    'scope'         => $scope,
                ],
                'timeout' => 15,
            ]);

            $statusCode = $response->getStatusCode();
            $body       = $response->getBody();
            $data       = json_decode($body, true);
            $data       = is_array($data) ? $data : ['raw' => $body];

            if ($statusCode < 200 || $statusCode >= 300 || ! isset($data['access_token'])) {
                log_message('error', 'Home::uberSandboxToken failed', [
                    'status_code' => $statusCode,
                    'response'    => $data,
                ]);

                $message = $data['error_description'] ?? $data['message'] ?? $data['error'] ?? 'Failed to create token';

                return $this->respond([
                    'error'       => $message,
                    'status_code' => $statusCode,
                    'response'    => $data,
                ], 502);
            }

            return $this->respond([
                'access_token' => $data['access_token'],
                'token_type'   => $data['token_type'] ?? 'Bearer',
                'expires_in'   => $data['expires_in'] ?? null,
                'scope'        => $data['scope'] ?? $scope,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Home::uberSandboxToken error: {message}', ['message' => $e->getMessage()]);
            return $this->respond([
                'error'   => 'Failed to create token',
                'details' => ENVIRONMENT === 'development' ? $e->getMessage() : null,
            ], 502);
        }
    }
}
