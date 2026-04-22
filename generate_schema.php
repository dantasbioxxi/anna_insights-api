<?php
require 'src/Config/Database.php';
$db = \App\Config\Database::getConnection();

$tables = ['usuarios'];

foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("
            SELECT column_name, data_type, character_maximum_length, is_nullable
            FROM information_schema.columns
            WHERE table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($cols) > 0) {
            echo "TABLE: $table\n";
            print_r($cols);
            echo "\n";
        }
    } catch (Exception $e) {}
}
