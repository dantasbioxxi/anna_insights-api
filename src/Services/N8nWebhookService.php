<?php
namespace App\Services;

class N8nWebhookService {
    public function sendToN8n(string $sessionId, string $message): bool {
        $env = require __DIR__ . '/../../config/env.php';
        $webhookUrl = $env['N8N_WEBHOOK_URL'];
        
        $payload = [
            'session_id' => $sessionId,
            'texto_mensagem' => $message,
            'is_human' => true
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
}
