'use strict';
const $ = (id) => document.getElementById(id);
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let busy = false;
let state = null;

async function api(action, payload) {
  const options = payload === undefined
    ? { method: 'GET', credentials: 'same-origin' }
    : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payload) };
  let response;
  try { response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options); }
  catch (error) { throw new Error('Servidor PHP indisponível. Confira se ele está executando em 127.0.0.1:8000.'); }
  let body;
  try { body = await response.json(); }
  catch { throw new Error('O servidor retornou uma resposta inválida. Confira os erros no terminal do PHP.'); }
  if (!response.ok || !body.ok) throw new Error(body.error || 'Não foi possível concluir a operação.');
  return body.data;
}

function text(node, value) { node.textContent = String(value ?? ''); return node; }
function node(tag, className, value) {
  const el = document.createElement(tag);
  if (className) el.className = className;
  if (value !== undefined) text(el, value);
  return el;
}
function notification(message, ok=false) {
  const el = $('message'); el.textContent = message; el.classList.toggle('success', ok); el.classList.remove('hidden');
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function clearNotification() { $('message').classList.add('hidden'); }
function setBusy(button, active, busyText='Processando...') {
  if (!button) return;
  if (active) { button.dataset.original = button.innerHTML; button.disabled = true; button.textContent = busyText; }
  else { button.disabled = false; if (button.dataset.original) button.innerHTML = button.dataset.original; }
}
function changePage(name) {
  document.querySelectorAll('.page').forEach((p) => p.classList.toggle('active', p.id === `page-${name}`));
  document.querySelectorAll('.nav-btn').forEach((b) => b.classList.toggle('active', b.dataset.page === name));
  clearNotification();
  window.scrollTo({ top: 0, behavior: 'instant' });
  if (name === 'laboratorio') loadRecords();
}

function applyStatus(data) {
  state = data;
  const c = data.counts;
  const all = c.animais_total + c.documentos_total;
  const ready = c.animais_atuais + c.documentos_atuais;
  text($('metricAnimals'), c.animais_total);
  text($('metricEmbedded'), `${ready} / ${all}`);
  text($('metricModel'), data.embedding_model);
  text($('dbStatus'), '● Conectado'); $('dbStatus').className = 'ok';
  text($('ollamaStatus'), data.ollama.ok ? '● Online' : '● Indisponível');
  $('ollamaStatus').className = data.ollama.ok ? 'ok' : 'bad';
  const models = data.ollama.models || [];
  const hasModel = (name) => models.some(m => m === name || m.split(':')[0] === name.split(':')[0]);
  const embFound = data.ollama.ok && hasModel(data.embedding_model);
  const chatFound = data.ollama.ok && hasModel(data.chat_model);
  text($('embedModelStatus'), embFound ? `● ${data.embedding_model}` : `○ ${data.embedding_model} (não detectado)`);
  text($('chatModelStatus'), chatFound ? `● ${data.chat_model}` : `○ ${data.chat_model} (não detectado)`);
  $('embedModelStatus').className = embFound ? 'ok' : 'bad';
  $('chatModelStatus').className = chatFound ? 'ok' : 'bad';
  text($('sideHealth'), data.ollama.ok ? '● PostgreSQL + Ollama online' : '● Banco OK · Ollama offline');
  $('sideHealth').style.color = data.ollama.ok ? '#83c4a7' : '#ffb3a3';
  const fraction = all ? ready / all : 0;
  $('progressBar').style.width = `${Math.round(fraction * 100)}%`;
  text($('progressLabel'), `${c.animais_atuais}/${c.animais_total} animais · ${c.documentos_atuais}/${c.documentos_total} documentos preparados (${Math.round(fraction * 100)}%)`);
}
async function refreshStatus() {
  try { applyStatus(await api('status')); }
  catch (err) {
    text($('dbStatus'), '● Erro de conexão'); $('dbStatus').className = 'bad';
    text($('sideHealth'), '● Verifique PostgreSQL e PHP'); $('sideHealth').style.color = '#ffb3a3';
    notification(err.message);
  }
}

function scoreBar(value) {
  const raw = Number(value);
  const score = Number.isFinite(raw) ? raw : 0;
  const wrap = node('div');
  const line = node('div', 'score-title');
  line.append(node('span', '', 'Similaridade de cosseno'), node('strong', '', score.toFixed(4)));
  wrap.append(line);
  const track = node('div', 'score-track');
  const fill = node('div', 'score-fill');
  fill.style.width = `${Math.max(0, Math.min(100, score * 100))}%`;
  track.append(fill); wrap.append(track);
  return wrap;
}
function animalCard(item) {
  const card = node('article', 'animal-card');
  const header = node('div','animal-head');
  header.append(node('div','avatar',item.especie === 'Gato' ? '🐈' : '🐕'));
  const info = node('div'); info.append(node('h3','', item.nome),node('p','', `${item.especie} · ${item.porte} · ${item.idade_anos} ano(s)`));
  header.append(info);card.append(header,node('p','description',item.descricao),scoreBar(item.similaridade));
  return card;
}
async function doSearchAnimal(event) {
  event.preventDefault(); clearNotification();
  const button = $('animalSubmit');setBusy(button,true,'Comparando embeddings...');
  const out = $('animalResults'); out.replaceChildren(node('div','empty','Consultando o Ollama e o PostgreSQL...'));
  try {
    const data = await api('animals',{texto:$('animalQuery').value, especie:$('species').value});
    out.replaceChildren();
    if (!data.resultados.length) out.append(node('div','empty','Nenhum animal encontrado. Gere os embeddings no Laboratório e tente novamente.'));
    else data.resultados.forEach(a=>out.append(animalCard(a)));
    text($('animalCount'),`${data.resultados.length} resultado(s)`);
  } catch (err) {out.replaceChildren(node('div','empty','Não foi possível concluir a busca.'));notification(err.message);}
  finally {setBusy(button,false);}
}
function showDocs(items) {
  const out = $('docResults');out.replaceChildren();
  if (!items.length) {out.append(node('div','empty','Não há documentos recuperados. Gere os embeddings primeiro.'));return;}
  for (const item of items) {
    const card = node('article','doc-item');const head=node('div');
    head.append(node('strong','',`${item.codigo} · ${item.titulo}`),node('small','',Number(item.similaridade).toFixed(4)));
    card.append(head,node('p','',item.conteudo)); out.append(card);
  }
}
async function doRag(kind, event) {
  if (event) event.preventDefault();clearNotification();
  const textQuery = $('ragQuery').value.trim();
  if (!textQuery) {notification('Digite uma pergunta sobre adoção.');return;}
  const btn = kind === 'rag' ? $('ragSubmit') : $('documentsButton');
  setBusy(btn,true,kind === 'rag' ? 'IA elaborando resposta...' : 'Procurando trechos...');
  const out = $('ragAnswer');out.textContent = 'Recuperando documentos...';
  try {
    const data = await api(kind, {texto:textQuery});
    showDocs(data.documentos || []);
    out.textContent = kind === 'rag' ? data.resposta : 'Somente a recuperação foi executada. Os documentos encontrados estão ao lado. Clique em “Perguntar à IA” para enviar o contexto ao modelo generativo.';
  } catch (err) {out.textContent='A operação não foi concluída.';notification(err.message);}
  finally {setBusy(btn,false);}
}
async function loadRecords() {
  try {
    const data=await api('records');
    const animalTbody=$('animalTable');animalTbody.replaceChildren();
    for (const a of data.animais) {
      const tr=node('tr');
      tr.append(node('td','',a.nome),node('td','',a.especie),node('td','',a.vetor_manual));
      const dim=node('td');dim.append(node('span',`pill${a.dimensoes ? '' : ' off'}`,a.dimensoes ? `${a.dimensoes} dimensões` : 'NULL'));
      tr.append(dim,node('td','',a.modelo_embedding||'—'));animalTbody.append(tr);
    }
    const docTbody=$('docTable');docTbody.replaceChildren();
    for(const d of data.documentos) {
      const tr=node('tr');tr.append(node('td','',`${d.codigo} · ${d.titulo}`));
      const dim=node('td');dim.append(node('span',`pill${d.dimensoes ? '' : ' off'}`,d.dimensoes ? `${d.dimensoes} dimensões` : 'NULL'));
      tr.append(dim,node('td','',d.modelo_embedding||'—'));docTbody.append(tr);
    }
  } catch(err) {notification(err.message);}
}
async function generate() {
  if (busy)return;busy=true;clearNotification();
  setBusy($('generateButton'),true,'Gerando...');$('resetButton').disabled=true;
  try {
    let iterations=0;
    while (iterations < 40) {
      const data=await api('generate',{});
      applyStatus(data);iterations++;
      if (data.concluido) {notification('Embeddings gerados com sucesso! Você já pode pesquisar animais e usar RAG.',true);break;}
      if (!data.gerados_neste_lote.animais && !data.gerados_neste_lote.documentos) throw new Error('A geração não avançou. Confira a conexão com o Ollama.');
    }
    if (iterations >= 40) notification('Foram processados 40 lotes. Verifique o status e continue se necessário.');
    await loadRecords();
  }catch(err){notification(err.message);}finally{busy=false;setBusy($('generateButton'),false);$('resetButton').disabled=false;}
}
async function reset() {
  if (busy)return;
  if (!window.confirm('Limpar TODOS os embeddings gerados pela IA? Animais, documentos e vetores manuais serão preservados.')) return;
  const typed=window.prompt('Para confirmar, digite RESETAR:');
  if (typed !== 'RESETAR') return;
  setBusy($('resetButton'),true,'Limpando...');
  try {applyStatus(await api('reset',{confirmacao:'RESETAR'}));await loadRecords();notification('Os embeddings foram limpos. As colunas de IA voltaram a NULL.',true);}
  catch(err){notification(err.message);}finally{setBusy($('resetButton'),false);}
}
async function preview(event) {
  event.preventDefault(); const btn=$('previewSubmit');setBusy(btn,true,'Gerando...');
  try {
    const data=await api('preview',{texto:$('previewText').value});
    const values=data.amostra.map(n=>Number(n).toFixed(6)).join(', ');
    text($('previewOutput'),`Modelo: ${data.modelo}\nDimensões: ${data.dimensoes}\nPrimeiros 12 valores:\n[${values}, ...]`);
  }catch(err){notification(err.message);}finally{setBusy(btn,false);}
}

document.querySelectorAll('[data-page]').forEach(btn=>btn.addEventListener('click',()=>changePage(btn.dataset.page)));
document.querySelectorAll('[data-open]').forEach(btn=>btn.addEventListener('click',()=>changePage(btn.dataset.open)));
document.querySelectorAll('[data-animal-example]').forEach(btn=>btn.addEventListener('click',()=>{$('animalQuery').value=btn.dataset.animalExample;$('species').value=btn.dataset.species||'';}));
document.querySelectorAll('[data-rag-example]').forEach(btn=>btn.addEventListener('click',()=>{$('ragQuery').value=btn.dataset.ragExample;}));
$('animalForm').addEventListener('submit',doSearchAnimal);
$('ragForm').addEventListener('submit',evt=>doRag('rag',evt));
$('documentsButton').addEventListener('click',()=>doRag('documents'));
$('previewForm').addEventListener('submit',preview);
$('generateButton').addEventListener('click',generate);
$('resetButton').addEventListener('click',reset);
$('recordsRefresh').addEventListener('click',loadRecords);
$('refreshButton').addEventListener('click',refreshStatus);
refreshStatus();
