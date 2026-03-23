<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\Session\Session;
use Config\Services;

class Home extends BaseController
{
    use ResponseTrait;

    /** @var list<string> */
    protected $helpers = ['form', 'url'];

    public function index()
    {
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

            // ✅ success login — regenerate session id after auth
            $session->regenerate(true);
            $session->set([
                'user_id'      => $user['id'],
                'user_name'    => $user['name'],
                'user_email'   => $user['email'],
                'is_logged_in' => true,
                'auth_method'  => 'password',
            ]);

            // Attach Uber Eats sandbox access_token (same client_credentials flow as dashboard Uber tools).
            $this->mergeUberOAuthIntoSession($session);

            return redirect()->to(site_url('dashboard'));
        }

        $viewData = [];
        if ($session->get('is_logged_in')) {
            $viewData['already_logged_in'] = true;
            $viewData['logged_in_name']    = $session->get('user_name');
            $viewData['logged_in_email']   = $session->get('user_email');
            $viewData['auth_method']       = $session->get('auth_method');
        }

        return view('login', $viewData);
    }

    public function dashboard()
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return redirect()->to(base_url());
        }

        $expRaw = $session->get('uber_token_expires_at');

        return view('dashboard', [
            'logged_in_name'  => $session->get('user_name') ?? '',
            'logged_in_email' => $session->get('user_email') ?? '',
            'auth_method'     => $session->get('auth_method'),
            'uber_access_token' => $session->get('uber_access_token') ?? '',
            'uber_token_expires_at' => $expRaw ?? '',
            'uber_token_expires_at_formatted' => $this->formatTokenExpiryDisplay($expRaw),
            'uber_token_expires_relative' => is_numeric($expRaw) ? $this->formatTokenExpiryRelative((int) $expRaw) : '',
            'uber_token_scope' => $session->get('uber_token_scope') ?? '',
            'uber_token_type' => $session->get('uber_token_type') ?? '',
        ]);
    }

    /**
     * Human-readable expiry for a Unix timestamp (Uber token).
     */
    protected function formatTokenExpiryDisplay(mixed $expAt): string
    {
        if ($expAt === null || $expAt === '' || ! is_numeric($expAt)) {
            return '—';
        }

        $t = (int) $expAt;
        if ($t <= 0) {
            return '—';
        }

        $tzName = config('App')->appTimezone ?? 'UTC';

        try {
            $dt = (new \DateTimeImmutable('@' . $t))->setTimezone(new \DateTimeZone($tzName));
        } catch (\Exception $e) {
            return (string) $expAt;
        }

        return $dt->format('l, F j, Y \a\t g:i:s A T');
    }

    /**
     * Short relative label, e.g. "29 days, 5 hours left" or "" if expired / unknown.
     */
    protected function formatTokenExpiryRelative(int $expiresAtUnix): string
    {
        $now = time();
        if ($expiresAtUnix <= $now) {
            return '';
        }

        $diff  = $expiresAtUnix - $now;
        $days  = intdiv($diff, 86400);
        $hours = intdiv($diff % 86400, 3600);
        $mins  = intdiv($diff % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
        }
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
        }
        if ($days === 0 && $hours === 0 && $mins > 0) {
            $parts[] = $mins . ' minute' . ($mins === 1 ? '' : 's');
        }
        if ($parts === []) {
            return 'less than a minute left';
        }

        return implode(', ', $parts) . ' left';
    }

    public function logout()
    {
        $session = session();
        $userId  = $session->get('user_id');

        $session->destroy();

        log_message('info', 'User logged out', ['user_id' => $userId]);

        return redirect()->to(base_url());
    }

    /**
     * Sign in using Uber Eats sandbox OAuth client_credentials (or a pasted access token).
     * Token endpoint: https://sandbox-login.uber.com/oauth/v2/token
     */
    public function uberSandboxLogin()
    {
        $session = session();

        if ($session->get('is_logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to(base_url());
        }

        // Optional: paste a token you already obtained from the sandbox token URL (dev convenience).
        $pasted = trim((string) $this->request->getPost('uber_access_token'));
        if ($pasted !== '') {
            $session->regenerate(true);
            $session->set($this->uberSandboxSessionPayload($pasted, null, null, null));

            log_message('info', 'Home::uberSandboxLogin session created (pasted token)');

            return redirect()->to(site_url('dashboard'));
        }

        $fetch = $this->fetchUberSandboxAccessTokenFromEnv();
        if (! $fetch['ok']) {
            log_message('error', 'Home::uberSandboxLogin token fetch failed', [
                'status_code' => $fetch['status_code'] ?? null,
                'response'    => $fetch['response'] ?? null,
            ]);

            $message = $fetch['message'] ?? 'Failed to obtain Uber sandbox access_token';

            return view('login', [
                'error' => $message,
                'uber_oauth_detail' => $fetch['response'] ?? null,
            ]);
        }

        $data      = $fetch['data'];
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;
        $expiresAt = $expiresIn !== null && $expiresIn > 0 ? time() + $expiresIn : null;
        $scopeOut  = $data['scope'] ?? ($fetch['requested_scope'] ?? null);

        $session->regenerate(true);
        $session->set($this->uberSandboxSessionPayload(
            (string) $data['access_token'],
            $expiresAt,
            $scopeOut,
            $data['token_type'] ?? 'Bearer'
        ));

        log_message('info', 'Home::uberSandboxLogin session created (client_credentials)');

        return redirect()->to(site_url('dashboard'));
    }

    public function uberSandboxToken()
    {
        $session = session();
        if (! $session->get('is_logged_in')) {
            return $this->failUnauthorized('Unauthorized');
        }

        $existing = $session->get('uber_access_token')
            ?: $session->get('access_token');
        if (is_string($existing) && $existing !== '') {
            $expiresAt = $session->get('uber_token_expires_at');
            if ($expiresAt === null || (is_numeric($expiresAt) && (int) $expiresAt > time())) {
                return $this->respond([
                    'access_token' => $existing,
                    'token_type'   => $session->get('uber_token_type') ?? 'Bearer',
                    'expires_in'   => is_numeric($expiresAt) ? max(0, (int) $expiresAt - time()) : null,
                    'scope'        => $session->get('uber_token_scope'),
                    'source'       => 'session',
                ]);
            }
        }

        $fetch = $this->fetchUberSandboxAccessTokenFromEnv();
        if (! $fetch['ok']) {
            $message = $fetch['message'] ?? 'Failed to create token';
            log_message('error', 'Home::uberSandboxToken failed', [
                'status_code' => $fetch['status_code'] ?? null,
                'response'    => $fetch['response'] ?? null,
            ]);

            return $this->respond([
                'error'        => $message,
                'status_code'  => $fetch['status_code'] ?? null,
                'response'     => $fetch['response'] ?? null,
            ], 502);
        }

        $data      = $fetch['data'];
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        return $this->respond([
            'access_token' => $data['access_token'],
            'token_type'   => $data['token_type'] ?? 'Bearer',
            'expires_in'   => $expiresIn,
            'scope'        => $data['scope'] ?? ($fetch['requested_scope'] ?? null),
            'source'       => 'oauth',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function uberSandboxSessionPayload(
        string $accessToken,
        ?int $expiresAtUnix,
        ?string $scope,
        ?string $tokenType
    ): array {
        return [
            'user_id'               => null,
            'user_name'             => 'Uber Sandbox',
            'user_email'            => 'sandbox@uber.local',
            'is_logged_in'          => true,
            'auth_method'           => 'uber_sandbox',
            'uber_access_token'     => $accessToken,
            'access_token'          => $accessToken,
            'uber_token_expires_at' => $expiresAtUnix,
            'uber_token_scope'      => $scope,
            'uber_token_type'       => $tokenType ?? 'Bearer',
        ];
    }

    /**
     * Fetches sandbox OAuth token and adds uber_* (+ access_token) to an existing session.
     * Login still succeeds if Uber credentials are missing or the token request fails.
     */
    protected function mergeUberOAuthIntoSession(Session $session): void
    {
        $fetch = $this->fetchUberSandboxAccessTokenFromEnv();

        if (! $fetch['ok']) {
            log_message('warning', 'mergeUberOAuthIntoSession: no Uber token (dashboard Uber actions may still request one)', [
                'message'     => $fetch['message'] ?? null,
                'status_code' => $fetch['status_code'] ?? null,
            ]);

            return;
        }

        $data      = $fetch['data'];
        $token     = (string) $data['access_token'];
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;
        $expiresAt = $expiresIn !== null && $expiresIn > 0 ? time() + $expiresIn : null;
        $scopeOut  = $data['scope'] ?? ($fetch['requested_scope'] ?? null);

        $session->set([
            'uber_access_token'     => $token,
            'access_token'          => $token,
            'uber_token_expires_at' => $expiresAt,
            'uber_token_scope'      => $scopeOut,
            'uber_token_type'       => $data['token_type'] ?? 'Bearer',
        ]);
    }

    /**
     * Client credentials against https://sandbox-login.uber.com/oauth/v2/token
     *
     * @return array{ok: bool, message?: string, status_code?: int, response?: array|string, data?: array, requested_scope?: string}
     */
    protected function fetchUberSandboxAccessTokenFromEnv(): array
    {
        $clientId     = getenv('UBER_CLIENT_ID');
        $clientSecret = getenv('UBER_CLIENT_SECRET');
        $grantType    = getenv('GRANT_TYPE') ?: 'client_credentials';
        $scope        = getenv('UBER_SANDBOX_SCOPE') ?: (getenv('SCOPE') ?: 'eats.store eats.order');
        $scope        = trim((string) $scope);
        $scope        = trim($scope, "\"'");

        if (! $clientId || ! $clientSecret) {
            return [
                'ok'      => false,
                'message' => 'Missing UBER_CLIENT_ID or UBER_CLIENT_SECRET in .env',
            ];
        }

        $tokenUrl = trim((string) (getenv('UBER_EATS_OAUTH_TOKEN_URL') ?: ''));
        if ($tokenUrl === '') {
            $tokenUrl = 'https://sandbox-login.uber.com/oauth/v2/token';
        }

        /** @var CURLRequest $http */
        $http = Services::curlrequest();

        try {
            $response = $http->post($tokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
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
                $message = $data['error_description'] ?? $data['message'] ?? $data['error'] ?? 'Failed to create token';

                return [
                    'ok'          => false,
                    'message'     => is_string($message) ? $message : 'Failed to create token',
                    'status_code' => $statusCode,
                    'response'    => $data,
                    'requested_scope' => $scope,
                ];
            }

            return [
                'ok'                => true,
                'data'              => $data,
                'requested_scope'   => $scope,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'fetchUberSandboxAccessTokenFromEnv error: {message}', ['message' => $e->getMessage()]);

            return [
                'ok'      => false,
                'message' => 'Failed to create token',
                'response' => ENVIRONMENT === 'development' ? ['exception' => $e->getMessage()] : null,
            ];
        }
    }
}
