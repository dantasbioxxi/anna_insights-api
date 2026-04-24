<?php

namespace App\Controllers;

use App\Config\Database;
use App\Router;
use Exception;

class SyncController
{
    public function syncColaboradores()
    {
        $payload = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (
            !isset($payload['data']) ||
            !is_array($payload['data'])
        ) {
            Router::jsonResponse([
                'erro' => 'Payload inválido'
            ], 400);
            return;
        }

        $db = Database::getConnection();

        try {

            $db->beginTransaction();

            $sql = "
                INSERT INTO dados_funcionarios_contato (
                    codcoligada,
                    chapa,
                    nome,
                    telefone1,
                    telefone2,
                    codsituacao,
                    secao_descricao,
                    funcao_nome,
                    email
                )
                VALUES (
                    :codcoligada,
                    :chapa,
                    :nome,
                    :telefone1,
                    :telefone2,
                    :codsituacao,
                    :secao_descricao,
                    :funcao_nome,
                    :email
                )

                ON CONFLICT (codcoligada, chapa)

                DO UPDATE SET
                    nome = EXCLUDED.nome,
                    telefone1 = EXCLUDED.telefone1,
                    telefone2 = EXCLUDED.telefone2,
                    codsituacao = EXCLUDED.codsituacao,
                    secao_descricao = EXCLUDED.secao_descricao,
                    funcao_nome = EXCLUDED.funcao_nome,
                    email = EXCLUDED.email
            ";

            $stmt = $db->prepare($sql);

            $processados = 0;

            foreach ($payload['data'] as $row) {

                $stmt->execute([
                    ':codcoligada' => (int)$row['CODCOLIGADA'],
                    ':chapa' => $row['CHAPA'],
                    ':nome' => $row['NOME'],
                    ':telefone1' => $row['TELEFONE1'],
                    ':telefone2' => $row['TELEFONE2'],
                    ':codsituacao' => $row['CODSITUACAO'],
                    ':secao_descricao' => $row['SECAO_DESCRICAO'],
                    ':funcao_nome' => $row['FUNCAO_DNOME'],
                    ':email' => $row['EMAIL']
                ]);

                $processados++;
            }

            $db->commit();

            Router::jsonResponse([
                'sucesso' => true,
                'processados' => $processados
            ]);

        } catch (Exception $e) {

            $db->rollBack();

            Router::jsonResponse([
                'erro' => $e->getMessage()
            ], 500);
        }
    }
}