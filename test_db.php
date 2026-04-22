<?php
require 'src/Config/Database.php';
$db = \App\Config\Database::getConnection();
$stmt = $db->query("SELECT * FROM dados_funcionarios_contato LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
