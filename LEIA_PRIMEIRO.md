# PetMatch LAB — PHP + PostgreSQL/pgvector + Ollama (100% local)

Um laboratório **didático**, com frontend e backend em PHP, para visualizar embeddings, buscar animais por similaridade semântica e consultar documentos com RAG. Funciona **sem Python, sem Composer, sem Laravel e sem serviços pagos**, desde que os modelos já estejam baixados no Ollama.

## O que você recebe

- **Visão geral**: saúde das conexões e contagem de embeddings preparados.
- **Busca semântica**: digite "Quero um cachorro tranquilo para crianças" e veja a classificação dos animais, com similaridade de cosseno.
- **Chat RAG**: pesquise documentos fictícios de adoção e gere respostas com Gemma 3, mostrando **quais documentos foram recuperados**.
- **Laboratório**: gere embeddings em lotes, teste a transformação de um texto em 768 números, veja a dimensão armazenada no banco e limpe somente os embeddings gerados pela IA.

## Requisitos

1. Windows com **PHP 8.2+** (inclusive PHP do XAMPP), extensões **pdo_pgsql** e **curl** habilitadas.
2. PostgreSQL local acessível, com a extensão `vector` do pgvector **instalada no servidor**.
3. Ollama instalado e aberto na **mesma máquina**, em `http://127.0.0.1:11434`.
4. Modelos baixados no Ollama:

   ```powershell
   ollama pull embeddinggemma
   ollama pull gemma3:1b
   ollama list
   ```

   `embeddinggemma` gera embeddings de 768 dimensões. `gemma3:1b` gera texto para o RAG. A geração de respostas pode ser lenta em máquinas modestas.

## 1. Preparar PHP (se já usa XAMPP, aproveite o PHP dele)

No PowerShell:

```powershell
php -v
php --ini
php -m
```

Se `php` não for reconhecido, use diretamente `C:\xampp\php\php.exe` nos comandos ou inicie com `INICIAR_WINDOWS.bat` (detecta o XAMPP mais comum).

**Ative no php.ini em uso** (local indicado por `php --ini`):

```ini
extension=pdo_pgsql
extension=curl
```

Descomente as linhas caso estejam com `;` na frente. Confirme novamente com `php -m`, que deve listar `pdo_pgsql` e `curl`. A extensão `pgsql` é opcional neste projeto, mas pode ser habilitada também. Feche/reabra o terminal após a alteração, se necessário.

> Se usar o servidor embutido do PHP, Apache, Composer e extensões de framework não são necessários.

## 2. Configurar banco local

Escolha um nome para o banco. No exemplo usamos `db_petmatch`.

- No **pgAdmin**, crie um banco novo com o nome `db_petmatch`, ou use um já existente que contenha a base PetMatch.
- No Query Tool **conectado a esse banco**, execute `database/01_criar_base_petmatch.sql` se ainda não tiver executado.
- O SQL cria a extensão (que precisa estar instalada), schema `petmatch`, 24 animais e 10 documentos fictícios. Não apaga registros existentes, pois usa `IF NOT EXISTS` e `ON CONFLICT DO NOTHING`.
- Se já usou o PetMatch na primeira aula, **não precisa recriar**. A estrutura é a mesma.

Se `CREATE EXTENSION vector` falhar, instale a extensão no PostgreSQL local primeiro; a biblioteca PHP não instala pgvector no servidor.

## 3. Configurar as conexões

Copie `.env.example` para `.env` (ou abra o `INICIAR_WINDOWS.bat`, que cria o `.env` automaticamente na primeira vez):

```ini
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=db_petmatch
DB_USER=postgres
DB_PASSWORD=sua_senha
OLLAMA_URL=http://127.0.0.1:11434
EMBEDDING_MODEL=embeddinggemma
CHAT_MODEL=gemma3:1b
```

**Não suba o `.env` para GitHub e não compartilhe a senha do banco.** Se a senha contiver `#`, `=`, espaços ou caracteres especiais, coloque o valor entre aspas duplas no arquivo.

## 4. Executar a aplicação

### Modo mais simples no Windows

Clique em `INICIAR_WINDOWS.bat` e abra:

**http://127.0.0.1:8000**

### Pelo terminal, na pasta do projeto

```powershell
php -S 127.0.0.1:8000 -t public
```

Se estiver utilizando PHP do XAMPP sem `php` no PATH:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

Pare com **Ctrl+C**. Evite utilizar `0.0.0.0`; este é um projeto local sem autenticação.

## 5. Passos para testar as três telas

**A. Laboratório:** veja os animais listados com vetor manual preenchido e coluna de IA `NULL`. Clique em **Gerar embeddings**. O sistema envia as descrições ao Ollama em lotes de três, valida os 768 números e salva no banco. O botão pode ser usado de novo: ele prepara somente registros faltantes ou de outro modelo.

**B. Busca semântica:** digite "Quero um cachorro calmo que goste de crianças", selecione `Cachorro` e clique em **Pesquisar animais**. A pergunta será transformada em embedding pelo mesmo modelo e o banco classificará os animais pelo operador `<=>` (distância de cosseno). Os números mostrados são similares matemáticos, não certezas estatísticas.

**C. Chat com RAG:** pergunte "Preciso apresentar comprovante de residência para adotar?". Clique em **Só recuperar documentos** para ver a etapa de busca. Depois clique em **Perguntar à IA**: o backend envia os documentos recuperados para `gemma3:1b` junto da pergunta, e exibe a resposta e as fontes ao lado.

**Para repetir a aula com NULL:** no Laboratório clique em **Limpar embeddings**, confirme digitando `RESETAR` e observe que `vetor_perfil VECTOR(3)` continua preenchido. Essa ação apaga os embeddings existentes nas DUAS tabelas PetMatch — inclusive se foram feitos por Python antes. Use apenas na sua base didática.

## SQLs para acompanhar no pgAdmin

O arquivo `database/02_conferir.sql` contém consultas para visualizar os valores `NULL`, inspecionar `vector_dims` e comparar embeddings já cadastrados. Existe também `database/03_limpar_embeddings.sql` como alternativa manual ao botão da interface.

## Arquitetura simples

```text
NAVEGADOR (HTML, CSS, JS)
          |
          v
PHP (servidor 127.0.0.1:8000)
  |                         |
  v                         v
OLLAMA (127.0.0.1:11434)   POSTGRESQL LOCAL (porta 5432)
  |                         |
  +-- embeddinggemma        +-- schema petmatch
  +-- gemma3:1b             +-- pgvector VECTOR(768)
```

**Busca semântica:** PHP -> EmbeddingGemma (pergunta -> vetor) -> pgvector (recuperação) -> navegador (cards).

**RAG:** PHP -> EmbeddingGemma -> pgvector (documentos) -> Gemma 3 (resposta) -> navegador (resposta + fontes).

## Erros comuns

| Mensagem / problema | Como resolver |
|---|---|
| `pdo_pgsql ... nao habilitada` | Verifique `extension=pdo_pgsql` no `php.ini` usado pelo PHP que inicia o servidor. |
| `curl ... nao habilitada` | Habilite `extension=curl` e reinicie o servidor PHP. |
| `Falha na conexao PostgreSQL` | Confira host, porta, banco, usuario e senha no `.env` e veja se o PostgreSQL está ligado. |
| `Base PetMatch nao encontrada` | Execute `database/01_criar_base_petmatch.sql` no banco escolhido no `.env`. |
| `Ollama nao respondeu` | Abra o Ollama e execute `ollama list`. Confirme a porta 11434. |
| `model ... not found` | Execute `ollama pull embeddinggemma` e/ou `ollama pull gemma3:1b`. |
| `Esperava 768 dimensoes` | Use `embeddinggemma` (configuração esperada), mantenha o banco em `VECTOR(768)`. |
| Busca vazia | Primeiro gere embeddings. Se mudou de modelo, gere novamente. |
| Chat muito lento | Gemma 3 roda localmente; CPU/RAM podem limitar a velocidade. Experimente somente a recuperação de documentos. |
| Porta 8000 ocupada | Inicie `php -S 127.0.0.1:8001 -t public` e abra `http://127.0.0.1:8001`. |

## Cuidados / limitações

- Aplicação **educacional**, sem login; inicie apenas em `127.0.0.1`. Não publique na internet ou em redes públicas.
- A base contém **animais fictícios** e procedimentos de adoção **simulados**; não são regras oficiais.
- A IA pode recuperar o trecho errado ou produzir respostas incorretas. Confira os documentos.
- O projeto pressupõe **um modelo de embedding de 768 dimensões**. Trocar para outro modelo pode exigir alterar a coluna SQL e reprocessar os documentos.
- Os animais são pequenos exemplos, então não precisamos de índice HNSW para desempenho. A busca calcula a distância diretamente.
- Se os textos forem alterados depois de gerar embeddings, é preciso reprocessá-los. Para a aula, use o botão de reset e gere novamente. O aplicativo não sincroniza alterações feitas diretamente no SQL de maneira automática.

## Estrutura do projeto

```text
PetMatch_PHP_Local/
  .env.example
  INICIAR_WINDOWS.bat
  LEIA_PRIMEIRO.md
  database/
    01_criar_base_petmatch.sql
    02_conferir.sql
    03_limpar_embeddings.sql
  public/
    index.php
    api.php
    assets/
      style.css
      app.js
  src/
    bootstrap.php
    Database.php
    OllamaClient.php
    PetMatchService.php
  tests/
    testes_basicos.php
```

## Documentação técnica

- Ollama API embeddings: https://docs.ollama.com/api/embed
- Ollama API chat: https://docs.ollama.com/api/chat
- PHP PDO PostgreSQL: https://www.php.net/manual/en/ref.pdo-pgsql.php
- pgvector: https://github.com/pgvector/pgvector
