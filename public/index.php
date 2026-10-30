<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
ensureLocalRequest();
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(24)); }
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <title>PetMatch LAB — IA + pgvector</title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><div class="brand-icon">🐾</div><div><strong>PetMatch</strong><small>LABORATÓRIO DE IA</small></div></div>
      <div class="side-caption">EXPERIMENTOS</div>
      <nav class="nav">
        <button type="button" class="nav-btn active" data-page="inicio"><span>⌂</span> Visão geral</button>
        <button type="button" class="nav-btn" data-page="busca"><span>⌕</span> Busca semântica</button>
        <button type="button" class="nav-btn" data-page="rag"><span>✧</span> Chat com RAG</button>
        <button type="button" class="nav-btn" data-page="laboratorio"><span>▦</span> Laboratório</button>
      </nav>
      <div class="side-bottom">
        <div class="side-note">Projeto didático local<br>PHP · Ollama · PostgreSQL</div>
        <div id="sideHealth" class="side-health">● Verificando serviços...</div>
      </div>
    </aside>
    <main class="main">
      <header class="topbar"><div class="topbar-path">BANCO DE DADOS II <span>/</span> PROJETO DE IA</div><button id="refreshButton" class="btn small ghost" type="button">↻ Atualizar status</button></header>
      <div id="message" class="message hidden" role="status"></div>

      <section class="page active" id="page-inicio">
        <div class="eyebrow">EXPERIMENTO LOCAL · PGVECTOR</div>
        <h1>IA e banco vetorial,<br><em>funcionando juntos.</em></h1>
        <p class="lead">Transforme descrições de animais em embeddings com o Ollama, encontre perfis semelhantes no PostgreSQL e faça perguntas a documentos com RAG.</p>
        <div class="metric-grid">
          <div class="metric"><div class="metric-label">ANIMAIS</div><div id="metricAnimals" class="metric-number">—</div><small>cadastrados na base</small></div>
          <div class="metric"><div class="metric-label">EMBEDDINGS PRONTOS</div><div id="metricEmbedded" class="metric-number">—</div><small>animais e documentos</small></div>
          <div class="metric"><div class="metric-label">MODELO</div><div id="metricModel" class="metric-number compact">embeddinggemma</div><small>geração de vetores</small></div>
        </div>
        <div class="two-cols">
          <div class="panel">
            <div class="panel-kicker">PASSO A PASSO</div>
            <h2>Comece por aqui</h2>
            <div class="step"><span class="step-num">1</span><div><strong>Confirme as conexões</strong><p>PostgreSQL e Ollama precisam estar ativos no computador.</p></div></div>
            <div class="step"><span class="step-num">2</span><div><strong>Gere os embeddings</strong><p>No Laboratório, crie vetores de 768 dimensões para animais e documentos.</p></div></div>
            <div class="step"><span class="step-num">3</span><div><strong>Faça perguntas naturais</strong><p>Compare os resultados de busca semântica com a resposta do RAG.</p></div></div>
            <div class="actions"><button class="btn primary" data-open="laboratorio" type="button">Abrir laboratório <span>→</span></button></div>
          </div>
          <div class="panel">
            <div class="panel-kicker">ESTADO DOS SERVIÇOS</div>
            <h2>Ambiente</h2>
            <div class="service"><span>Banco de dados</span><strong id="dbStatus">Verificando...</strong></div>
            <div class="service"><span>Ollama</span><strong id="ollamaStatus">Verificando...</strong></div>
            <div class="service"><span>Modelo de embeddings</span><strong id="embedModelStatus">—</strong></div>
            <div class="service"><span>Modelo generativo</span><strong id="chatModelStatus">—</strong></div>
            <div class="tip"><strong>Como isso funciona?</strong><p>O modelo transforma texto em números. O PostgreSQL armazena esses números e faz a busca. Um modelo generativo pode usar os documentos encontrados para responder.</p></div>
          </div>
        </div>
      </section>

      <section class="page" id="page-busca">
        <div class="eyebrow">EXPERIMENTO 01</div><h1>Busca <em>semântica</em></h1>
        <p class="lead">Descreva o animal que você procura. O resultado não depende de palavras exatamente iguais: o banco compara os embeddings.</p>
        <div class="panel form-panel">
          <form id="animalForm">
            <label for="animalQuery">O que você procura?</label>
            <textarea id="animalQuery" rows="3" maxlength="1100" placeholder="Ex.: Quero um cachorro calmo que goste de crianças e de ficar em casa." required></textarea>
            <div class="form-row"><div class="field"><label for="species">Espécie</label><select id="species"><option value="">Todas</option><option value="Cachorro">Cachorro</option><option value="Gato">Gato</option></select></div><button type="submit" class="btn primary" id="animalSubmit">Pesquisar animais <span>→</span></button></div>
          </form>
          <div class="chips"><span>Experimente:</span><button type="button" data-animal-example="Quero um cachorro tranquilo que conviva bem com crianças" data-species="Cachorro">🐶 Calmo e familiar</button><button type="button" data-animal-example="Gato que gosta de brincar, pular e explorar" data-species="Gato">🐱 Gato ativo</button><button type="button" data-animal-example="Animal idoso que prefira uma rotina sossegada">🐾 Animal idoso</button></div>
        </div>
        <div class="result-heading"><h2>Resultados por similaridade</h2><span id="animalCount" class="muted">Faça uma pesquisa para começar.</span></div>
        <div id="animalResults" class="card-grid"><div class="empty">Nenhuma busca realizada ainda.</div></div>
        <p class="fineprint">A pontuação é similaridade de cosseno, não probabilidade de compatibilidade ou garantia de adoção adequada.</p>
      </section>

      <section class="page" id="page-rag">
        <div class="eyebrow">EXPERIMENTO 02</div><h1>Chat com <em>RAG</em></h1>
        <p class="lead">A pergunta vira embedding, o pgvector recupera documentos e o modelo Gemma 3 utiliza o contexto para produzir uma resposta.</p>
        <div class="two-cols rag-cols">
          <div class="panel">
            <div class="panel-kicker">CONVERSE COM OS DOCUMENTOS</div>
            <form id="ragForm">
              <label for="ragQuery">Sua pergunta</label>
              <textarea id="ragQuery" rows="4" maxlength="1100" placeholder="Ex.: Quais documentos eu preciso para adotar um animal?" required></textarea>
              <div class="rag-buttons"><button type="button" id="documentsButton" class="btn secondary">Só recuperar documentos</button><button type="submit" id="ragSubmit" class="btn primary">Perguntar à IA <span>✧</span></button></div>
            </form>
            <div class="chips"><span>Experimente:</span><button type="button" data-rag-example="Preciso de comprovante de residência?">Comprovante</button><button type="button" data-rag-example="Posso adotar se eu moro em apartamento?">Apartamento</button><button type="button" data-rag-example="Existe alguma taxa de adoção?">Taxa de adoção</button></div>
            <div id="ragAnswer" class="answer-box"><span class="muted">A resposta da IA aparecerá aqui após a consulta.</span></div>
          </div>
          <div class="panel"><div class="panel-kicker">RETRIEVAL · POSTGRESQL</div><h2>Documentos recuperados</h2><p class="muted compact-text">Veja quais textos foram enviados como contexto ao modelo generativo.</p><div id="docResults" class="doc-list"><div class="empty">Nenhum documento recuperado ainda.</div></div></div>
        </div>
        <p class="fineprint">As regras de adoção desta base são inteiramente fictícias. Modelos generativos podem errar; confira as fontes e suas pontuações.</p>
      </section>

      <section class="page" id="page-laboratorio">
        <div class="eyebrow">EXPERIMENTO 03</div><h1>Laboratório de <em>embeddings</em></h1>
        <p class="lead">Acompanhe a criação e o armazenamento dos vetores de IA. A coluna manual de 3 dimensões é preservada.</p>
        <div class="two-cols">
          <div class="panel"><div class="panel-kicker">PREPARAÇÃO DO BANCO</div><h2>Gerar embeddings</h2><p class="muted">Crie os vetores com EmbeddingGemma e grave na base PetMatch. O processo roda em lotes pequenos.</p>
            <div id="progressLabel" class="progress-label">Aguardando execução</div><div class="progress-bar"><div id="progressBar" class="progress-fill"></div></div>
            <div class="actions"><button id="generateButton" type="button" class="btn primary">Gerar embeddings <span>✧</span></button><button id="resetButton" type="button" class="btn danger-outline">Limpar embeddings</button></div>
            <p class="fineprint">Limpar embeddings apaga apenas as colunas geradas pela IA. Os animais, documentos e os vetores manuais permanecem.</p>
          </div>
          <div class="panel"><div class="panel-kicker">TESTE SEM GRAVAR NO BANCO</div><h2>Inspecionar um embedding</h2><form id="previewForm"><label for="previewText">Texto de exemplo</label><textarea id="previewText" rows="2" maxlength="1000" required>Cachorro tranquilo e carinhoso</textarea><button id="previewSubmit" class="btn secondary" type="submit">Transformar em vetor</button></form><div id="previewOutput" class="code-preview muted">Os primeiros números do vetor aparecerão aqui.</div></div>
        </div>
        <div class="panel data-panel"><div class="table-heading"><div><div class="panel-kicker">POSTGRESQL · PETMATCH.ANIMAIS</div><h2>Registros do banco</h2></div><button type="button" class="btn small ghost" id="recordsRefresh">↻ Recarregar</button></div><div class="table-scroll"><table><thead><tr><th>Animal</th><th>Espécie</th><th>Vetor manual</th><th>Embedding IA</th><th>Modelo</th></tr></thead><tbody id="animalTable"><tr><td colspan="5" class="muted">Carregando dados...</td></tr></tbody></table></div><p class="fineprint">A tabela mostra apenas a dimensão do embedding, para não exibir 768 números por linha.</p></div>
        <div class="panel data-panel"><div class="table-heading"><div><div class="panel-kicker">POSTGRESQL · PETMATCH.DOCUMENTOS_ADOCAO</div><h2>Base de conhecimento RAG</h2></div></div><div class="table-scroll"><table><thead><tr><th>Documento</th><th>Embedding IA</th><th>Modelo</th></tr></thead><tbody id="docTable"><tr><td colspan="3" class="muted">Carregando dados...</td></tr></tbody></table></div></div>
      </section>
    </main>
  </div>
</body></html>
