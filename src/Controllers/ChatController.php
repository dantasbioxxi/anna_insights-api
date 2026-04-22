<?php
namespace App\Controllers;

use App\Config\Database;
use App\Router;

class ChatController {
    // Listar conversas agrupadas por session_id
    public function index() {
        $db = Database::getConnection();
        // Aproveita JSNOB para trazer a última mensagem e session_id
        $query = "
            SELECT 
                rh_chat_histories.session_id, 
                MAX(rh_chat_histories.created_at) as last_message_date,
                (
                    SELECT message 
                    FROM rh_chat_histories rch2 
                    WHERE rch2.session_id = rh_chat_histories.session_id 
                    ORDER BY rch2.created_at DESC LIMIT 1
                ) as last_message_data,
                MAX(dfc.nome) as nome_funcionario,
                MAX(dfc.chapa) as func_chapa,
                MAX(dfc.telefone1) as func_telefone1,
                MAX(dfc.telefone2) as func_telefone2,
                MAX(dfc.codsituacao) as func_situacao,
                MAX(dfc.secao_descricao) as func_secao,
                MAX(dfc.funcao_nome) as func_funcao
            FROM rh_chat_histories
            LEFT JOIN dados_funcionarios_contato dfc 
                ON dfc.telefone1 = rh_chat_histories.session_id 
                OR dfc.telefone2 = rh_chat_histories.session_id
            GROUP BY rh_chat_histories.session_id
            ORDER BY last_message_date DESC
        ";
        
        $stmt = $db->query($query);
        $sessions = $stmt->fetchAll();
        
        foreach ($sessions as &$s) {
            if (!empty($s['last_message_data'])) {
                $s['last_message_data'] = json_decode($s['last_message_data'], true);
            }
        }
        
        Router::jsonResponse($sessions);
    }
    
    // Obter histórico de mensagens de uma sessão específica
    public function show($sessionId) {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, session_id, message, created_at FROM rh_chat_histories WHERE session_id = ? ORDER BY created_at ASC');
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll();
        
        foreach ($messages as &$m) {
            if (!empty($m['message'])) {
                $m['message'] = json_decode($m['message'], true);
            }
        }
        
        Router::jsonResponse($messages);
    }
    
    // Enviar mensagem do RH
    public function sendMessage() {
        $data = Router::getJsonPayload();
        
        if (empty($data['session_id']) || empty($data['texto_mensagem'])) {
            Router::jsonResponse(['error' => 'session_id e texto_mensagem são obrigatórios'], 400);
        }
        
        $sessionId = $data['session_id'];
        $messageText = $data['texto_mensagem'];
        
        // 1. Salvar no Banco como origem "HUMAN" ou "RH"
        $db = Database::getConnection();
        
        $messagePayload = json_encode([
            'type' => 'IA', // Indicando que foi operador
            'content' => $messageText
        ]);
        
        $stmt = $db->prepare('INSERT INTO rh_chat_histories (session_id, message, created_at) VALUES (?, ?, NOW()) RETURNING id, created_at');
        $stmt->execute([$sessionId, $messagePayload]);
        $msgRecord = $stmt->fetch();
        
        // 2. Disparar Webhook para o n8n
        $n8n = new \App\Services\N8nWebhookService();
        $success = $n8n->sendToN8n($sessionId, $messageText);
        
        if ($success) {
            Router::jsonResponse([
                'message' => 'Mensagem enviada com sucesso',
                'data' => [
                    'id' => $msgRecord['id'],
                    'message' => json_decode($messagePayload),
                    'created_at' => $msgRecord['created_at']
                ]
            ]);
        } else {
            Router::jsonResponse(['error' => 'Mensagem salva, mas falhou ao notificar o n8n'], 500);
        }
    }
}
