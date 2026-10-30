<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
ensureLocalRequest();

use PetMatch\Database;
use PetMatch\OllamaClient;
use PetMatch\PetMatchService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string)($_GET['action'] ?? 'status');
    if ($method === 'POST') {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
        $expected = $_SESSION['csrf'] ?? '';
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$expected || !hash_equals($expected, $token)) {
            respond(['ok' => false, 'error' => 'Sessao invalida. Atualize a pagina e tente novamente.'], 403);
        }
        $raw = file_get_contents('php://input');
        $input = json_decode($raw ?: '{}', true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new RuntimeException('Corpo JSON invalido.');
        }
        set_time_limit(210);
    } else {
        $input = [];
    }
    $allowed = $method === 'GET'
        ? ['status', 'records']
        : ['generate', 'reset', 'preview', 'animals', 'documents', 'rag'];
    if (!in_array($action, $allowed, true)) {
        respond(['ok' => false, 'error' => 'Rota nao encontrada.'], 404);
    }
    $service = new PetMatchService(Database::connect(), new OllamaClient());
    $result = match ($action) {
        'status' => $service->status(),
        'records' => $service->records(),
        'generate' => $service->generateBatch(3),
        'reset' => (($input['confirmacao'] ?? '') === 'RESETAR'
            ? $service->resetEmbeddings()
            : throw new RuntimeException('Confirmacao para limpar embeddings invalida.')),
        'preview' => $service->previewEmbedding((string)($input['texto'] ?? '')),
        'animals' => $service->searchAnimals(
            (string)($input['texto'] ?? ''),
            ($input['especie'] ?? '') === '' ? null : (string)$input['especie'],
            6
        ),
        'documents' => $service->searchDocuments((string)($input['texto'] ?? ''), 4),
        'rag' => $service->rag((string)($input['texto'] ?? '')),
    };
    respond(['ok' => true, 'data' => $result]);
} catch (\JsonException $error) {
    respond(['ok' => false, 'error' => 'O corpo JSON enviado nao e valido.'], 400);
} catch (\PDOException $error) {
    error_log('PetMatch SQL: ' . $error->getMessage());
    respond(['ok' => false, 'error' => 'Erro ao consultar o PostgreSQL. Confira o schema PetMatch e a extensao vector.'], 500);
} catch (\RuntimeException $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (\Throwable $error) {
    error_log('PetMatch: ' . $error->getMessage());
    respond(['ok' => false, 'error' => 'Erro inesperado. Verifique o terminal que esta executando o PHP.'], 500);
}
