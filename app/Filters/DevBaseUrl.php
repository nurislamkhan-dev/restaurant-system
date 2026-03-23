<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Aligns Config\App::$baseURL with the current request host (scheme + authority).
 *
 * Fixes login/session issues when .env says http://localhost:8080/ but the browser
 * uses http://127.0.0.1:8080/ (or the opposite): form posts & redirects would target
 * a different host than the cookie domain, so the session appears "lost" after login.
 *
 * Only runs in development; production should keep a correct fixed app.baseURL.
 */
class DevBaseUrl implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT !== 'development' || is_cli()) {
            return;
        }

        $uri = $request->getUri();
        if ($uri === null) {
            return;
        }

        $authority = $uri->getAuthority();
        if ($authority === '') {
            return;
        }

        /** @var \Config\App $app */
        $app         = config('App');
        $app->baseURL = $uri->getScheme() . '://' . $authority . '/';
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
