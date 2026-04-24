<?php
namespace App\Middleware;

use App\Utils\JWT;
use App\Router;

class AuthMiddleware {
    /**
     * Check if the request has a valid JWT token
     * Returns the decoded token payload on success, returns null if invalid
     */
    public static function authenticate(): ?array {
        $env = require __DIR__ . '/../../config/env.php';
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            Router::jsonResponse(['error' => 'Token não encontrado'], 401);
        }
        
        $payload = JWT::verify($token, $env['JWT_SECRET']);
        
        if (!$payload) {
            Router::jsonResponse(['error' => 'Token inválido ou expirado'], 401);
        }
        
        return $payload;
    }
    
    /**
     * Check if authenticated user is an admin
     */
    public static function requireAdmin(): void {
        $env = require __DIR__ . '/../../config/env.php';
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            Router::jsonResponse(['error' => 'Token não encontrado'], 401);
        }
        
        $payload = JWT::verify($token, $env['JWT_SECRET']);
        
        if (!$payload) {
            Router::jsonResponse(['error' => 'Token inválido ou expirado'], 401);
        }
        
        // Check if user has admin role (perfil === 1)
        if (($payload['perfil'] ?? null) !== 1) {
            Router::jsonResponse(['error' => 'Acesso negado. Privilégios de administrador necessários.'], 403);
        }
    }
}
