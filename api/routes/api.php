<?php

/**
 * API Routes Definition & Router
 * Maps incoming HTTP requests to endpoints and controllers
 */

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/AuthController.php';

class Router
{
    private array $routes = [];

    /**
     * Register a GET route
     *
     * @param string $path
     * @param callable|array ...$handlers
     */
    public function get(string $path, ...$handlers): void
    {
        $this->addRoute('GET', $path, $handlers);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, ...$handlers): void
    {
        $this->addRoute('POST', $path, $handlers);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, ...$handlers): void
    {
        $this->addRoute('PUT', $path, $handlers);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, ...$handlers): void
    {
        $this->addRoute('DELETE', $path, $handlers);
    }

    /**
     * Register a route for any HTTP method
     */
    public function any(string $path, ...$handlers): void
    {
        $this->addRoute('*', $path, $handlers);
    }

    /**
     * Store route definition
     */
    private function addRoute(string $method, string $path, array $handlers): void
    {
        $normalizedPath = $this->normalizePath($path);
        $this->routes[] = [
            'method'   => strtoupper($method),
            'path'     => $normalizedPath,
            'handlers' => $handlers
        ];
    }

    /**
     * Normalize URL path
     */
    private function normalizePath(string $path): string
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed;
    }

    /**
     * Parse incoming request body (JSON or Form Data)
     */
    public function getRequestBody(): array
    {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                return $jsonData;
            }
        }
        return $_POST;
    }

    /**
     * Dispatch the request to the matching route
     */
    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $requestMethod = strtoupper($requestMethod);
        $parsedUri     = parse_url($requestUri, PHP_URL_PATH);

        // Normalize requested path relative to /api
        $path = $this->extractApiPath($parsedUri);

        $matchedPath = false;

        foreach ($this->routes as $route) {
            // Check method match
            $methodMatch = ($route['method'] === '*' || $route['method'] === $requestMethod);

            // Match path pattern (supports parameters like {id})
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#i';

            if (preg_match($pattern, $path, $matches)) {
                $matchedPath = true;

                if ($methodMatch) {
                    array_shift($matches); // Remove full match

                    $request = [
                        'method' => $requestMethod,
                        'path'   => $path,
                        'params' => $matches,
                        'query'  => $_GET,
                        'body'   => $this->getRequestBody(),
                        'user'   => null
                    ];

                    $handlers = $route['handlers'];
                    $totalHandlers = count($handlers);

                    for ($i = 0; $i < $totalHandlers; $i++) {
                        $handler = $handlers[$i];
                        $isLastHandler = ($i === $totalHandlers - 1);

                        if ($isLastHandler) {
                            call_user_func_array($handler, [$request, ...$matches]);
                            return;
                        } else {
                            // Run middleware
                            call_user_func_array($handler, [&$request, ...$matches]);
                        }
                    }
                    return;
                }
            }
        }

        if ($matchedPath) {
            sendMethodNotAllowed("Method {$requestMethod} not allowed for endpoint: {$path}");
        } else {
            sendNotFound("Endpoint not found: {$path}");
        }
    }

    /**
     * Extract the path relative to the API root
     */
    private function extractApiPath(string $uri): string
    {
        $uri = urldecode($uri);

        // Remove script name if accessing directly (e.g. /oshens_gloceries/api/index.php)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $apiBaseDir = dirname($scriptName);

        if (!empty($apiBaseDir) && $apiBaseDir !== '/' && $apiBaseDir !== '\\') {
            $apiBaseDir = str_replace('\\', '/', $apiBaseDir);
            if (strpos($uri, $apiBaseDir) === 0) {
                $uri = substr($uri, strlen($apiBaseDir));
            }
        }

        // If 'route' query parameter is used as a fallback (e.g. index.php?route=auth/login)
        if (!empty($_GET['route'])) {
            $uri = '/' . ltrim($_GET['route'], '/');
        }

        // Remove index.php if still present in path
        $uri = preg_replace('#^/index\.php#i', '', $uri);

        return $this->normalizePath($uri);
    }
}

// -------------------------------------------------------------
// Route Registrations
// -------------------------------------------------------------

$router = new Router();
$authController = new AuthController();

/**
 * Root API Information endpoint
 * GET /api or GET /
 */
$router->get('/', function ($req) {
    sendSuccess('Welcome to Oshens Groceries REST API', [
        'name'        => 'Oshens Groceries API',
        'version'     => '1.0.0',
        'status'      => 'online',
        'endpoints'   => [
            'test'          => 'GET /api/test',
            'auth_register' => 'POST /api/auth/register',
            'auth_login'    => 'POST /api/auth/login',
            'auth_verify'   => 'GET /api/auth/verify',
            'auth_logout'   => 'POST /api/auth/logout',
            'auth_profile'  => 'GET /api/auth/profile (Protected)'
        ]
    ]);
});

/**
 * Test endpoint
 * GET /api/test
 */
$router->get('/test', function ($req) {
    sendSuccess('API is working!', [
        'version'     => '1.0.0',
        'environment' => 'development'
    ]);
});

// -------------------------------------------------------------
// Authentication Routes
// -------------------------------------------------------------

/**
 * User Registration
 * POST /api/auth/register
 */
$router->post('/auth/register', function ($req) use ($authController) {
    $authController->register($req['body']);
});

/**
 * User Login
 * POST /api/auth/login
 */
$router->post('/auth/login', function ($req) use ($authController) {
    $authController->login($req['body']);
});

/**
 * Verify Token
 * GET /api/auth/verify
 */
$router->get('/auth/verify', function ($req) use ($authController) {
    $authController->verify();
});

/**
 * User Logout
 * POST /api/auth/logout
 */
$router->post('/auth/logout', function ($req) use ($authController) {
    $authController->logout();
});

/**
 * Protected Route Example: Current User Profile
 * GET /api/auth/profile
 */
$router->get('/auth/profile', [AuthMiddleware::class, 'handle'], function ($req) use ($authController) {
    $authController->profile($req['user']);
});

return $router;
