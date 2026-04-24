<?php
namespace App\Services;

use Exception;

class EmailService {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $from;
    private string $fromName;

    public function __construct() {
        $env = require __DIR__ . '/../../config/env.php';
        
        $this->host = $env['MAIL_HOST'];
        $this->port = (int)$env['MAIL_PORT'];
        $this->username = $env['MAIL_USERNAME'] ?? '';
        $this->password = $env['MAIL_PASSWORD'] ?? '';
        $this->from = $env['MAIL_FROM'];
        $this->fromName = $env['MAIL_FROM_NAME'];
    }

    /**
     * Send email using SMTP
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        try {
            return $this->sendViaSMTP($to, $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via native SMTP (no external dependencies)
     */
    private function sendViaSMTP(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        // Create SMTP connection
        $socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);
        
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
        }

        try {
            // Read SMTP greeting
            $response = fgets($socket, 512);
            if (strpos($response, '220') === false) {
                throw new Exception("SMTP greeting failed: $response");
            }

            // Send HELO command
            $this->sendCommand($socket, "HELO " . gethostname());

            // Send AUTH if credentials are provided
            if (!empty($this->username) && !empty($this->password)) {
                $this->authenticateSMTP($socket);
            }

            // Send MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$this->from}>");

            // Send RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$to}>");

            // Send DATA
            $this->sendCommand($socket, "DATA");

            // Build email headers and body
            $headers = [
                "From: {$this->fromName} <{$this->from}>",
                "To: {$to}",
                "Subject: {$subject}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit"
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;

            // Send message
            fputs($socket, $message . "\r\n.\r\n");
            $response = fgets($socket, 512);

            if (strpos($response, '250') === false) {
                throw new Exception("Failed to send message: $response");
            }

            // Send QUIT
            $this->sendCommand($socket, "QUIT");

            fclose($socket);
            return true;

        } catch (Exception $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            throw $e;
        }
    }

    /**
     * Authenticate with SMTP server (LOGIN method)
     */
    private function authenticateSMTP($socket): void {
        // Request AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);

        if (strpos($response, '334') === false) {
            throw new Exception("AUTH LOGIN not supported: $response");
        }

        // Send username (base64 encoded)
        fputs($socket, base64_encode($this->username) . "\r\n");
        $response = fgets($socket, 512);

        if (strpos($response, '334') === false) {
            throw new Exception("Username rejected: $response");
        }

        // Send password (base64 encoded)
        fputs($socket, base64_encode($this->password) . "\r\n");
        $response = fgets($socket, 512);

        if (strpos($response, '235') === false) {
            throw new Exception("Authentication failed: $response");
        }
    }

    /**
     * Send SMTP command and verify response
     */
    private function sendCommand($socket, string $command): string {
        fputs($socket, $command . "\r\n");
        $response = fgets($socket, 512);

        // Check for success codes (2xx)
        if (preg_match('/^[45]/', $response)) {
            throw new Exception("SMTP Error for command '$command': $response");
        }

        return $response;
    }

    /**
     * Send password recovery email
     */
    public function sendPasswordRecoveryEmail(string $to, string $userName, string $resetLink): bool {
        $subject = 'Recuperação de Senha - Anna Insights';
        
        $htmlBody = "
        <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #007bff; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
                    .content { background-color: #f9f9f9; padding: 20px; border-bottom: 1px solid #ddd; }
                    .button { display: inline-block; background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { background-color: #f0f0f0; padding: 10px; text-align: center; font-size: 12px; color: #666; }
                    .warning { color: #d9534f; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Recuperação de Senha</h1>
                    </div>
                    <div class='content'>
                        <p>Olá <strong>{$userName}</strong>,</p>
                        <p>Recebemos uma solicitação para redefinir a senha da sua conta Anna Insights.</p>
                        <p>Para continuar, clique no botão abaixo:</p>
                        <a href='{$resetLink}' class='button'>Redefinir Senha</a>
                        <p>Ou copie e cole este link no seu navegador:</p>
                        <p style='word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 3px;'>{$resetLink}</p>
                        <p class='warning'>⚠️ Este link expira em 1 hora.</p>
                        <p class='warning'>Se você não solicitou essa recuperação, ignore este e-mail.</p>
                        <p>Se você tiver problemas ao clicar no botão, copie e cole o link acima em seu navegador.</p>
                    </div>
                    <div class='footer'>
                        <p>© 2026 Anna Insights. Todos os direitos reservados.</p>
                    </div>
                </div>
            </body>
        </html>";

        $textBody = "Olá {$userName},\n\nRecebemos uma solicitação para redefinir a senha da sua conta Anna Insights.\n\nClique no link abaixo para continuar:\n{$resetLink}\n\nEste link expira em 1 hora.\n\nSe você não solicitou essa recuperação, ignore este e-mail.\n\n© 2026 Anna Insights.";

        return $this->send($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Example method for using PHPMailer (install via composer)
     * Uncomment and use this for production SMTP support
     */
    /*
    private function sendViaPHPMailer(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->port;

            // Recipients
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending error: {$mail->ErrorInfo}");
            return false;
        }
    }
    */
}
