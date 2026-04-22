<?php
namespace App;

class Router {
    private array $routes = [];

    public function get(string $path, callable $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void {
        // Convert route params like :id to regex
        $regex = preg_replace('/:[a-zA-Z0-9_]+/', '([a-zA-Z0-9_\-]+)', $path);
        
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $regex . '/?$#',
            'handler' => $handler
        ];
    }

    public function dispatch(string $method, string $uri): void {
        $uri = parse_url($uri, PHP_URL_PATH);
        // Remove base path if necessary. For simplicity, we assume API is accessed directly
        // at the configured URI structure.
        if (empty($uri)) $uri = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Remove full match
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        // Handle 404
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'method' => $method, 'uri' => $uri]);
    }
    
    public static function jsonResponse($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    public static function getJsonPayload() {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
