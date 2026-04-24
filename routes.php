<?php
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ChatController;
use App\Controllers\SyncController;
use App\Middleware\AuthMiddleware;

global $router;

// Auth Routes (no authentication required)
$router->post('/api/login', function() { (new AuthController())->login(); });
$router->post('/api/password-recovery', function() { (new AuthController())->recoverPassword(); });
$router->post('/api/validate-reset-token', function() { (new AuthController())->validateResetToken(); });
$router->post('/api/reset-password', function() { (new AuthController())->resetPassword(); });

// User CRUD Routes (Admin only)
$router->get('/api/users', function() { 
    AuthMiddleware::requireAdmin();
    (new UserController())->index(); 
});

$router->post('/api/users', function() { 
    AuthMiddleware::requireAdmin();
    (new UserController())->create(); 
});

$router->put('/api/users/:id', function($id) { 
    AuthMiddleware::requireAdmin();
    (new UserController())->update($id); 
});

$router->delete('/api/users/:id', function($id) { 
    AuthMiddleware::requireAdmin();
    (new UserController())->delete($id); 
});

// Chat Routes (authentication required)
$router->get('/api/chat/history', function() { 
    AuthMiddleware::authenticate();
    (new ChatController())->index(); 
});

$router->get('/api/chat/history/:session_id', function($sessionId) { 
    AuthMiddleware::authenticate();
    (new ChatController())->show($sessionId); 
});

$router->post('/api/chat/send', function() { 
    AuthMiddleware::authenticate();
    (new ChatController())->sendMessage(); 
});

// Sync Routes (authentication required)
$router->put('/api/sync-colaboradores', function() { 
    AuthMiddleware::authenticate();
    (new SyncController())->syncColaboradores(); 
});

