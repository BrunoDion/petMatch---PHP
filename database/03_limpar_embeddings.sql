-- OPCIONAL: remove APENAS embeddings gerados pelo modelo, sem apagar registros.
-- Use somente na base didatica de sua propriedade.
BEGIN;
UPDATE petmatch.animais SET embedding_descricao=NULL, modelo_embedding=NULL, embedding_atualizado_em=NULL;
UPDATE petmatch.documentos_adocao SET embedding_conteudo=NULL, modelo_embedding=NULL, embedding_atualizado_em=NULL;
COMMIT;
