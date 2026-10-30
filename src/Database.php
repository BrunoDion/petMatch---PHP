<?php
declare(strict_types=1);

namespace PetMatch;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('A extensao pdo_pgsql do PHP nao esta habilitada. Veja o LEIA_PRIMEIRO.md.');
        }
        $host = \config('DB_HOST', '127.0.0.1');
        $port = \config('DB_PORT', '5432');
        $name = \config('DB_NAME', 'db_petmatch');
        $user = \config('DB_USER', 'postgres');
        $password = \config('DB_PASSWORD', '');
        if (!ctype_digit((string)$port) || (int)$port < 1 || (int)$port > 65535) {
            throw new RuntimeException('DB_PORT invalida no arquivo .env.');
        }
        try {
            $pdo = new PDO(
                "pgsql:host={$host};port={$port};dbname={$name};connect_timeout=5",
                (string)$user,
                (string)$password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("SET client_encoding TO 'UTF8'");
            return $pdo;
        } catch (PDOException $error) {
            // Nunca imprimir a DSN (com hostname e outros dados) no frontend.
            throw new RuntimeException('Falha na conexao PostgreSQL. Confira DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASSWORD no .env.', 0, $error);
        }
    }

    public static function checkSchema(PDO $db): void
    {
        $hasTable = $db->query("SELECT CASE WHEN to_regclass('petmatch.animais') IS NOT NULL THEN 1 ELSE 0 END AS ok")->fetchColumn();
        $hasDocs = $db->query("SELECT CASE WHEN to_regclass('petmatch.documentos_adocao') IS NOT NULL THEN 1 ELSE 0 END AS ok")->fetchColumn();
        if (!$hasTable || !$hasDocs) {
            throw new RuntimeException('Base PetMatch nao encontrada. Execute database/01_criar_base_petmatch.sql no banco configurado.');
        }
    }
}
