<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

use PetMatch\PetMatchService;

function assertSameTest(mixed $a, mixed $b, string $description): void
{
    if ($a !== $b) { throw new RuntimeException("FALHOU: {$description}"); }
    echo "[OK] {$description}\n";
}

assertSameTest(strlen(PetMatchService::validateText('  cachorro feliz  ')), strlen('cachorro feliz'), 'Texto e limpo');
assertSameTest(count(json_decode(PetMatchService::vectorLiteral(array_fill(0, 768, .125)), true)), 768, 'Formato vetorial 768D');
try { PetMatchService::vectorLiteral([1.0, 2.0]); throw new RuntimeException('Dimensao 2D aceita indevidamente'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(), '768')) throw $e; echo "[OK] Vetor de tamanho invalido rejeitado\n"; }
try { PetMatchService::validateText(''); throw new RuntimeException('Texto vazio aceito indevidamente'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Digite')) throw $e; echo "[OK] Texto vazio rejeitado\n"; }
echo "4 testes concluidos. (Sem conexao real com banco ou Ollama.)\n";
