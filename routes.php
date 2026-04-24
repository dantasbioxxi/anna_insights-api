<?php
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ChatController;
use App\Controllers\SyncController;

global $router;

// Auth Routes
$router->post('/api/login', function() { (new AuthController())->login(); });
$router->post('/api/password-recovery', function() { (new AuthController())->recoverPassword(); });

// User CRUD Routes (Admin only ideally, but keeping simple for base arch)
$router->get('/api/users', function() { (new UserController())->index(); });
$router->post('/api/users', function() { (new UserController())->create(); });
$router->put('/api/users/:id', function($id) { (new UserController())->update($id); });
$router->delete('/api/users/:id', function($id) { (new UserController())->delete($id); });

// Chat Routes
$router->get('/api/chat/history', function() { (new ChatController())->index(); }); // List unique sessions
$router->get('/api/chat/history/:session_id', function($sessionId) { (new ChatController())->show($sessionId); }); // Get messages for session
$router->post('/api/chat/send', function() { (new ChatController())->sendMessage(); }); // Send message

//sync colaboradores
$router->put('/api/sync-colaboradores', function() { (new SyncController())->syncColaboradores(); }); 

