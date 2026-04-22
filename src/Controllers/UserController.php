<?php
namespace App\Controllers;

use App\Config\Database;
use App\Router;

class UserController {
    public function index() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, login, nome, email, perfil, status_ativo, data_cadastro FROM usuarios ORDER BY id ASC");
        $users = $stmt->fetchAll();
        
        Router::jsonResponse($users);
    }
    
    public function create() {
        $data = Router::getJsonPayload();
        
        // Basic validation
        if (empty($data['login']) || empty($data['nome']) || empty($data['senha']) || empty($data['email']) || empty($data['perfil'])) {
            Router::jsonResponse(['error' => 'Faltam campos obrigatórios'], 400);
        }
        
        $db = Database::getConnection();
        
        // Check uniqueness
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE login = ? OR email = ?');
        $stmt->execute([$data['login'], $data['email']]);
        if ($stmt->fetch()) {
            Router::jsonResponse(['error' => 'Login ou E-mail já existem'], 400);
        }
        
        $hashedPassword = password_hash($data['senha'], PASSWORD_BCRYPT);
        
        $stmt = $db->prepare('INSERT INTO usuarios (login, nome, senha, email, perfil, status_ativo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['login'],
            $data['nome'],
            $hashedPassword,
            $data['email'],
            $data['perfil'],
            $data['status_ativo'] ?? 'S'
        ]);
        
        Router::jsonResponse(['message' => 'Usuário criado com sucesso'], 201);
    }
    
    public function update($id) {
        $data = Router::getJsonPayload();
        $db = Database::getConnection();
        
        // Optional password update
        if (!empty($data['senha'])) {
            $hashedPassword = password_hash($data['senha'], PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE usuarios SET nome = ?, email = ?, perfil = ?, status_ativo = ?, senha = ? WHERE id = ?');
            $stmt->execute([$data['nome'], $data['email'], $data['perfil'], $data['status_ativo'], $hashedPassword, $id]);
        } else {
            $stmt = $db->prepare('UPDATE usuarios SET nome = ?, email = ?, perfil = ?, status_ativo = ? WHERE id = ?');
            $stmt->execute([$data['nome'], $data['email'], $data['perfil'], $data['status_ativo'], $id]);
        }
        
        Router::jsonResponse(['message' => 'Usuário atualizado com sucesso']);
    }
    
    public function delete($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE usuarios SET status_ativo = ? WHERE id = ?');
        $stmt->execute(['N', $id]); // Logical delete
        
        Router::jsonResponse(['message' => 'Usuário inativado com sucesso']);
    }
}
