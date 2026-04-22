<?php
namespace App\Controllers;

use App\Config\Database;
use App\Router;

class AuthController {
    public function login() {
        $data = Router::getJsonPayload();
        
        if (empty($data['login']) || empty($data['senha'])) {
            Router::jsonResponse(['error' => 'Login e senha são obrigatórios'], 400);
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, login, nome, senha, perfil, status_ativo FROM usuarios WHERE login = ?');
        $stmt->execute([$data['login']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($data['senha'], $user['senha'])) {
            if ($user['status_ativo'] !== 'S') {
                Router::jsonResponse(['error' => 'Usuário inativo'], 403);
            }
            // Retorna dados do usuário (na prática geraria um JWT aqui)
            unset($user['senha']);
            Router::jsonResponse([
                'message' => 'Login realizado com sucesso',
                'user' => $user,
                'token' => 'mocked-jwt-token-replace-in-production'
            ]);
        }
        
        Router::jsonResponse(['error' => 'Credenciais inválidas'], 401);
    }
    
    public function recoverPassword() {
        $data = Router::getJsonPayload();
        
        if (empty($data['email'])) {
            Router::jsonResponse(['error' => 'E-mail é obrigatório'], 400);
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Simula envio de e-mail de recuperação
            Router::jsonResponse(['message' => 'E-mail de recuperação enviado com instruções.']);
        }
        
        // Resposta genérica por segurança
        Router::jsonResponse(['message' => 'Se o e-mail existir na base, enviamos as instruções.']);
    }
}
