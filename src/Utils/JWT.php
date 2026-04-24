<?php
namespace App\Utils;

class JWT {
    private static string $algorithm = 'HS256';
    
    /**
     * Generate a JWT token
     */
    public static function generate(array $payload, string $secret, int $expiresIn = 86400): string {
        $header = self::base64UrlEncode(json_encode([
            'alg' => self::$algorithm,
            'typ' => 'JWT'
        ]));
        
        // Add issued at and expiration to payload
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$header.$payload_encoded", $secret, true);
        $signature_encoded = self::base64UrlEncode($signature);
        
        return "$header.$payload_encoded.$signature_encoded";
    }
    
    /**
     * Verify and decode a JWT token
     */
    public static function verify(string $token, string $secret): ?array {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }
            
            list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
            
            // Verify signature
            $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
            $signature_from_token = self::base64UrlDecode($signature_encoded);
            
            if (!hash_equals($signature, $signature_from_token)) {
                error_log("JWT signature verification failed");
                return null;
            }
            
            // Decode payload
            $payload = json_decode(self::base64UrlDecode($payload_encoded), true);
            
            if (!$payload) {
                error_log("Failed to decode JWT payload");
                return null;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                error_log("JWT token expired");
                return null;
            }
            
            return $payload;
        } catch (\Exception $e) {
            error_log("JWT verification error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract token from Authorization header
     */
    public static function getTokenFromHeader(): ?string {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $authHeader = $headers['Authorization'];
        
        // Check for Bearer token
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode(string $data): string {
        $padded = $data . str_repeat('=', 4 - (strlen($data) % 4));
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
