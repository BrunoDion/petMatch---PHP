<?php
declare(strict_types=1);

spl_autoload_register(static function (string $name): void {
    $prefix = 'PetMatch\\';
    if (!str_starts_with($name, $prefix)) {
        return;
    }
    $class = substr($name, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function config(string $key, ?string $fallback = null): ?string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }
                $values[$name] = $value;
            }
        }
    }
    $process = getenv($key);
    return ($process !== false ? $process : ($values[$key] ?? $fallback));
}

function ensureLocalRequest(): void
{
    // Este projeto e apenas para aprendizado LOCAL. Nunca publique sem autenticacao.
    $server = $_SERVER['SERVER_NAME'] ?? '127.0.0.1';
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? $server)[0]);
    if (!in_array($host, ['127.0.0.1', 'localhost', '[::1]'], true)) {
        http_response_code(403);
        exit('Este projeto deve ser acessado apenas via localhost.');
    }
}
