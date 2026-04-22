<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $env = require __DIR__ . '/../../config/env.php';
            
            $host = $env['DB_HOST'];
            $port = $env['DB_PORT'];
            $db   = $env['DB_NAME'];
            $user = $env['DB_USER'];
            $pass = $env['DB_PASS'];
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
            
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Return a JSON error for the API
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
                exit;
            }
        }
        
        return self::$instance;
    }
}
