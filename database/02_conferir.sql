-- Consultas para acompanhar o funcionamento no pgAdmin.
-- SELECT 1: estado antes/depois da geração dos embeddings
SELECT nome, especie, vetor_perfil, embedding_descricao IS NULL AS embedding_nulo,
       vector_dims(embedding_descricao) AS dimensoes, modelo_embedding
FROM petmatch.animais ORDER BY id_animal LIMIT 8;

-- SELECT 2: quantidade preparada
SELECT COUNT(*) AS animais_total,
       COUNT(embedding_descricao) AS animais_com_embedded
FROM petmatch.animais;

SELECT COUNT(*) AS documentos_total,
       COUNT(embedding_conteudo) AS documentos_com_embedding
FROM petmatch.documentos_adocao;

-- SELECT 3: primeira parte do embedding (nao imprimir 768 numeros)
SELECT nome, LEFT(embedding_descricao::text, 90) AS amostra
FROM petmatch.animais WHERE embedding_descricao IS NOT NULL LIMIT 5;

-- SELECT 4: vizinhos usando um vetor real que JA EXISTE no banco.
-- Isso apenas demonstra a distancia (na interface o vetor vem da pergunta).
SELECT nome, ROUND((1 - (embedding_descricao <=> (
   SELECT embedding_descricao FROM petmatch.animais WHERE codigo = 'A001'
)))::numeric, 4) AS similaridade_com_luna
FROM petmatch.animais
WHERE embedding_descricao IS NOT NULL
  AND modelo_embedding = (SELECT modelo_embedding FROM petmatch.animais WHERE codigo = 'A001')
ORDER BY embedding_descricao <=> (SELECT embedding_descricao FROM petmatch.animais WHERE codigo = 'A001')
LIMIT 5;
