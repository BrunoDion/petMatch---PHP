<?php
declare(strict_types=1);

namespace PetMatch;

use PDO;
use RuntimeException;

final class PetMatchService
{
    public function __construct(private PDO $db, private OllamaClient $ollama)
    {
        Database::checkSchema($db);
    }

    public function status(): array
    {
        $model = $this->ollama->embeddingModel();
        $stmt = $this->db->prepare(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM petmatch.animais) AS animais_total,
                (SELECT COUNT(*) FROM petmatch.animais WHERE embedding_descricao IS NOT NULL) AS animais_com_vetor,
                (SELECT COUNT(*) FROM petmatch.animais WHERE embedding_descricao IS NOT NULL AND modelo_embedding = :modelo_animais) AS animais_atuais,
                (SELECT COUNT(*) FROM petmatch.documentos_adocao) AS documentos_total,
                (SELECT COUNT(*) FROM petmatch.documentos_adocao WHERE embedding_conteudo IS NOT NULL) AS documentos_com_vetor,
                (SELECT COUNT(*) FROM petmatch.documentos_adocao WHERE embedding_conteudo IS NOT NULL AND modelo_embedding = :modelo_docs) AS documentos_atuais
            SQL);
        $stmt->execute(['modelo_animais' => $model, 'modelo_docs' => $model]);
        $counts = $stmt->fetch();
        foreach ($counts as $key => $value) {
            $counts[$key] = (int)$value;
        }
        try {
            $models = $this->ollama->models();
            $ollamaStatus = ['ok' => true, 'models' => $models];
        } catch (\Throwable $error) {
            $ollamaStatus = ['ok' => false, 'models' => [], 'message' => $error->getMessage()];
        }
        return [
            'database' => 'Conectado ao PostgreSQL local',
            'embedding_model' => $model,
            'chat_model' => $this->ollama->chatModel(),
            'counts' => $counts,
            'ollama' => $ollamaStatus,
        ];
    }

    public function records(): array
    {
        $animals = $this->db->query(<<<'SQL'
            SELECT codigo, nome, especie, idade_anos, porte, descricao, disponivel,
                   vetor_perfil::text AS vetor_manual,
                   modelo_embedding,
                   CASE WHEN embedding_descricao IS NULL THEN NULL ELSE vector_dims(embedding_descricao) END AS dimensoes,
                   CASE WHEN embedding_descricao IS NULL THEN NULL ELSE LEFT(embedding_descricao::text, 115) END AS amostra
              FROM petmatch.animais ORDER BY id_animal
            SQL)->fetchAll();
        $docs = $this->db->query(<<<'SQL'
            SELECT codigo, titulo, conteudo, modelo_embedding,
                   CASE WHEN embedding_conteudo IS NULL THEN NULL ELSE vector_dims(embedding_conteudo) END AS dimensoes,
                   CASE WHEN embedding_conteudo IS NULL THEN NULL ELSE LEFT(embedding_conteudo::text, 115) END AS amostra
              FROM petmatch.documentos_adocao ORDER BY id_documento
            SQL)->fetchAll();
        return ['animais' => $animals, 'documentos' => $docs];
    }

    /** Retorna os primeiros valores, sem persistir no banco */
    public function previewEmbedding(string $text): array
    {
        $text = self::validateText($text);
        $vector = $this->ollama->embed([$text])[0];
        return ['dimensoes' => count($vector), 'amostra' => array_slice($vector, 0, 12), 'modelo' => $this->ollama->embeddingModel()];
    }

    /** Gera ate $batch registros de cada tabela a cada chamada, permitindo barra de progresso. */
    public function generateBatch(int $batch = 3): array
    {
        $batch = max(1, min($batch, 5));
        $model = $this->ollama->embeddingModel();
        $generated = ['animais' => 0, 'documentos' => 0];

        $jobs = [
            [
                'table' => 'animais', 'id' => 'id_animal', 'column' => 'embedding_descricao',
                'select' => 'SELECT id_animal, nome, especie, descricao FROM petmatch.animais WHERE embedding_descricao IS NULL OR modelo_embedding IS DISTINCT FROM :model ORDER BY id_animal LIMIT :limit',
                'text' => static fn(array $row): string =>
                    "Animal para adocao. Especie: {$row['especie']}. Nome: {$row['nome']}. Descricao: {$row['descricao']}",
            ],
            [
                'table' => 'documentos_adocao', 'id' => 'id_documento', 'column' => 'embedding_conteudo',
                'select' => 'SELECT id_documento, titulo, conteudo FROM petmatch.documentos_adocao WHERE embedding_conteudo IS NULL OR modelo_embedding IS DISTINCT FROM :model ORDER BY id_documento LIMIT :limit',
                'text' => static fn(array $row): string => "{$row['titulo']}. {$row['conteudo']}",
            ],
        ];
        foreach ($jobs as $job) {
            $read = $this->db->prepare($job['select']);
            $read->bindValue(':model', $model);
            $read->bindValue(':limit', $batch, PDO::PARAM_INT);
            $read->execute();
            $rows = $read->fetchAll();
            if (!$rows) {
                continue;
            }
            $texts = array_map($job['text'], $rows);
            $vectors = $this->ollama->embed($texts);
            // Tabelas/colunas sao CONSTANTES do codigo, nao recebidas por HTTP.
            $update = $this->db->prepare("UPDATE petmatch.{$job['table']} SET {$job['column']} = CAST(:vetor AS vector), modelo_embedding = :modelo, embedding_atualizado_em = CURRENT_TIMESTAMP WHERE {$job['id']} = :id");
            $this->db->beginTransaction();
            try {
                foreach ($rows as $i => $row) {
                    $update->execute([
                        'vetor' => self::vectorLiteral($vectors[$i]),
                        'modelo' => $model,
                        'id' => $row[$job['id']],
                    ]);
                    $generated[$job['table'] === 'animais' ? 'animais' : 'documentos']++;
                }
                $this->db->commit();
            } catch (\Throwable $error) {
                $this->db->rollBack();
                throw $error;
            }
        }
        $status = $this->status();
        $c = $status['counts'];
        $status['gerados_neste_lote'] = $generated;
        $status['concluido'] = $c['animais_atuais'] === $c['animais_total'] && $c['documentos_atuais'] === $c['documentos_total'];
        return $status;
    }

    public function resetEmbeddings(): array
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec('UPDATE petmatch.animais SET embedding_descricao = NULL, modelo_embedding = NULL, embedding_atualizado_em = NULL');
            $this->db->exec('UPDATE petmatch.documentos_adocao SET embedding_conteudo = NULL, modelo_embedding = NULL, embedding_atualizado_em = NULL');
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $this->status();
    }

    public function searchAnimals(string $text, ?string $species, int $limit = 5): array
    {
        $text = self::validateText($text);
        if (!in_array($species, [null, 'Cachorro', 'Gato'], true)) {
            throw new RuntimeException('Especie invalida. Escolha Cachorro, Gato ou Todas.');
        }
        $limit = max(1, min($limit, 10));
        $vector = self::vectorLiteral($this->ollama->embed([$text])[0]);
        $stmt = $this->db->prepare(<<<'SQL'
            SELECT codigo, nome, especie, porte, idade_anos, descricao, vetor_perfil::text AS vetor_manual,
                   ROUND((1 - (embedding_descricao <=> CAST(:vetor_sim AS vector)))::numeric, 4) AS similaridade
              FROM petmatch.animais
             WHERE disponivel IS TRUE AND embedding_descricao IS NOT NULL
               AND modelo_embedding = :modelo
               AND (CAST(:especie_filtro AS text) IS NULL OR especie = :especie_valor)
             ORDER BY embedding_descricao <=> CAST(:vetor_ordem AS vector)
             LIMIT :limite
            SQL);
        $stmt->bindValue(':vetor_sim', $vector);
        $stmt->bindValue(':vetor_ordem', $vector);
        $stmt->bindValue(':modelo', $this->ollama->embeddingModel());
        $stmt->bindValue(':especie_filtro', $species, $species === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':especie_valor', $species, $species === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return ['consulta' => $text, 'resultados' => $stmt->fetchAll()];
    }

    public function searchDocuments(string $text, int $limit = 4): array
    {
        $text = self::validateText($text);
        $limit = max(1, min($limit, 8));
        $vector = self::vectorLiteral($this->ollama->embed([$text])[0]);
        $stmt = $this->db->prepare(<<<'SQL'
            SELECT codigo, titulo, conteudo,
                   ROUND((1 - (embedding_conteudo <=> CAST(:vetor_sim AS vector)))::numeric, 4) AS similaridade
              FROM petmatch.documentos_adocao
             WHERE embedding_conteudo IS NOT NULL AND modelo_embedding = :modelo
             ORDER BY embedding_conteudo <=> CAST(:vetor_ordem AS vector)
             LIMIT :limite
            SQL);
        $stmt->bindValue(':vetor_sim', $vector);
        $stmt->bindValue(':modelo', $this->ollama->embeddingModel());
        $stmt->bindValue(':vetor_ordem', $vector);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return ['pergunta' => $text, 'documentos' => $stmt->fetchAll()];
    }

    public function rag(string $text): array
    {
        $retrieved = $this->searchDocuments($text, 4);
        if ($retrieved['documentos'] === []) {
            return ['resposta' => 'Nao encontrei documentos preparados para fundamentar uma resposta. Gere os embeddings primeiro.', 'documentos' => []];
        }
        return [
            'resposta' => $this->ollama->chat($text, $retrieved['documentos']),
            'documentos' => $retrieved['documentos'],
            'aviso' => 'Regras ficticias para demonstracao. A resposta da IA deve ser conferida nos documentos.',
        ];
    }

    public static function validateText(string $text): string
    {
        $text = trim($text);
        if ($text === '' || strlen($text) > 1200) {
            throw new RuntimeException('Digite entre 1 e 1200 bytes de texto para a pesquisa.');
        }
        return $text;
    }

    public static function vectorLiteral(array $vector): string
    {
        if (count($vector) !== 768) {
            throw new RuntimeException('O vetor nao possui 768 dimensoes. Confira EMBEDDING_MODEL.');
        }
        foreach ($vector as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
                throw new RuntimeException('O vetor possui coordenadas invalidas.');
            }
        }
        return json_encode(array_values($vector), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
