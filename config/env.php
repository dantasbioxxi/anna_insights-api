<?php

return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '5432',
    'DB_NAME' => 'n8n',
    'DB_USER' => 'ti',
    'DB_PASS' => 'senha',
    'N8N_WEBHOOK_URL' => 'http://localhost:5678',
    'JWT_SECRET' => 'chave_secreta_jwt_super_segura_123',
    
    // Email Configuration
    'MAIL_DRIVER' => 'smtp', // 'smtp' or 'mail'
    'MAIL_HOST' => '192.168.51.212', // Your SMTP server
    'MAIL_PORT' => '1025',
    'MAIL_USERNAME' => 'lucas', // Your email
    'MAIL_PASSWORD' => '', // Gmail App Password or SMTP password
    'MAIL_FROM' => 'anna_insights@cmexx.com',
    'MAIL_FROM_NAME' => 'Anna Insights',
    
    // Frontend URL for password reset link
    'FRONTEND_URL' => 'http://localhost:5173',
    'PASSWORD_RESET_LINK_TTL' => 3600 // 1 hour in seconds

];
