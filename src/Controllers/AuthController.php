<?php
namespace App\Controllers;

use App\Config\Database;
use App\Router;
use App\Services\EmailService;
use App\Utils\JWT;
use App\Middleware\AuthMiddleware;

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
            
            // Generate JWT token
            $env = require __DIR__ . '/../../config/env.php';
            $payload = [
                'id' => $user['id'],
                'login' => $user['login'],
                'nome' => $user['nome'],
                'perfil' => $user['perfil']
            ];
            $token = JWT::generate($payload, $env['JWT_SECRET']);
            
            // Retorna dados do usuário (sem a senha)
            unset($user['senha']);
            Router::jsonResponse([
                'message' => 'Login realizado com sucesso',
                'user' => $user,
                'token' => $token
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
        $stmt = $db->prepare('SELECT id, nome, email FROM usuarios WHERE email = ? AND status_ativo = ?');
        $stmt->execute([$data['email'], 'S']);
        $user = $stmt->fetch();
        
        // Always return generic message for security (avoid email enumeration)
        $env = require __DIR__ . '/../../config/env.php';
        
        if ($user) {
            // Generate secure reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + $env['PASSWORD_RESET_LINK_TTL']);
            
            // Store token in database
            $stmtInsert = $db->prepare(
                'INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
            );
            $stmtInsert->execute([$user['id'], $token, $expiresAt]);
            
            // Generate reset link
            $resetLink = $env['FRONTEND_URL'] . '/reset-password?token=' . $token;
            
            // Send email
            $emailService = new EmailService();
            $emailSent = $emailService->sendPasswordRecoveryEmail($user['email'], $user['nome'], $resetLink);
            
            if (!$emailSent) {
                error_log("Failed to send password recovery email to: {$user['email']}");
            }
        }
        
        // Generic response for security
        Router::jsonResponse(['message' => 'Se o e-mail existir na base, enviamos as instruções de recuperação.']);
    }
    
    public function validateResetToken() {
        $data = Router::getJsonPayload();
        
        if (empty($data['token'])) {
            Router::jsonResponse(['error' => 'Token é obrigatório'], 400);
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, user_id, expires_at, used 
             FROM password_reset_tokens 
             WHERE token = ? AND used = FALSE'
        );
        $stmt->execute([$data['token']]);
        $token = $stmt->fetch();
        
        if (!$token) {
            Router::jsonResponse(['error' => 'Token inválido ou já utilizado'], 400);
        }
        
        // Check if token is expired
        if (strtotime($token['expires_at']) < time()) {
            Router::jsonResponse(['error' => 'Token expirado'], 400);
        }
        
        Router::jsonResponse(['valid' => true, 'message' => 'Token válido']);
    }
    
    public function resetPassword() {
        $data = Router::getJsonPayload();
        
        // Validate input
        if (empty($data['token']) || empty($data['password']) || empty($data['password_confirmation'])) {
            Router::jsonResponse(['error' => 'Token, senha e confirmação são obrigatórios'], 400);
        }
        
        if ($data['password'] !== $data['password_confirmation']) {
            Router::jsonResponse(['error' => 'As senhas não conferem'], 400);
        }
        
        if (strlen($data['password']) < 8) {
            Router::jsonResponse(['error' => 'A senha deve ter no mínimo 8 caracteres'], 400);
        }
        
        $db = Database::getConnection();
        
        // Fetch and validate token
        $stmt = $db->prepare(
            'SELECT id, user_id, expires_at, used 
             FROM password_reset_tokens 
             WHERE token = ? AND used = FALSE'
        );
        $stmt->execute([$data['token']]);
        $token = $stmt->fetch();
        
        if (!$token) {
            Router::jsonResponse(['error' => 'Token inválido ou já utilizado'], 400);
        }
        
        // Check if token is expired
        if (strtotime($token['expires_at']) < time()) {
            Router::jsonResponse(['error' => 'Token expirado'], 400);
        }
        
        // Update user password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmtUpdate = $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        
        try {
            $stmtUpdate->execute([$hashedPassword, $token['user_id']]);
            
            // Mark token as used
            $stmtMarkUsed = $db->prepare('UPDATE password_reset_tokens SET used = TRUE WHERE id = ?');
            $stmtMarkUsed->execute([$token['id']]);
            
            Router::jsonResponse(['message' => 'Senha atualizada com sucesso. Faça login com a sua nova senha.']);
        } catch (\Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            Router::jsonResponse(['error' => 'Erro ao atualizar senha. Tente novamente.'], 500);
        }
    }
}
