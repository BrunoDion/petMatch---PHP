-- PETMATCH - AULAS 1 e 2 | BANCO DE DADOS II
-- Base educacional: animais e politicas de um abrigo FICTICIO.
-- Execute no SQL Editor do Neon ou no Query Tool do pgAdmin.
-- Pode ser executado novamente: as insercoes usam ON CONFLICT DO NOTHING.
-- ATENCAO: os perfis VECTOR(3) sao notas atribuidas manualmente,
-- NAO embeddings gerados por IA.

CREATE EXTENSION IF NOT EXISTS vector;
CREATE SCHEMA IF NOT EXISTS petmatch;

CREATE TABLE IF NOT EXISTS petmatch.animais (
    id_animal INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo VARCHAR(6) NOT NULL UNIQUE,
    nome VARCHAR(60) NOT NULL,
    especie VARCHAR(10) NOT NULL CHECK (especie IN ('Cachorro', 'Gato')),
    porte VARCHAR(10) NOT NULL CHECK (porte IN ('Pequeno', 'Medio', 'Grande')),
    idade_anos INTEGER NOT NULL CHECK (idade_anos BETWEEN 0 AND 25),
    descricao TEXT NOT NULL,
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    -- Dimensoes DIDATICAS: [tranquilidade, energia, sociabilidade], de 0 a 1
    vetor_perfil VECTOR(3) NOT NULL,
    -- Dimensoes REAIS geradas por Ollama/embeddinggemma (aula 2)
    embedding_descricao VECTOR(768),
    modelo_embedding VARCHAR(100),
    embedding_atualizado_em TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS petmatch.documentos_adocao (
    id_documento INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo VARCHAR(6) NOT NULL UNIQUE,
    titulo VARCHAR(140) NOT NULL,
    conteudo TEXT NOT NULL,
    embedding_conteudo VECTOR(768),
    modelo_embedding VARCHAR(100),
    embedding_atualizado_em TIMESTAMPTZ
);

-- Os valores dos vetores sao didaticos e nao descrevem clinicamente os animais.
-- Uma recomendacao real exigiria visita e avaliacao humana.
INSERT INTO petmatch.animais
    (codigo, nome, especie, porte, idade_anos, descricao, disponivel, vetor_perfil)
VALUES
    ('A001', 'Luna', 'Cachorro', 'Grande', 4,
     'Cadela muito tranquila e carinhosa. Convive bem com criancas, prefere passeios leves e gosta de descansar perto da familia. Boa companhia para um lar sossegado.', TRUE, '[0.95,0.18,0.95]'),
    ('A002', 'Thor', 'Cachorro', 'Medio', 2,
     'Cachorro ativo que adora correr, brincar de buscar bolinhas e fazer caminhadas longas. Precisa de espaco para atividades e de uma rotina com exercicios.', TRUE, '[0.15,0.95,0.70]'),
    ('A003', 'Mel', 'Cachorro', 'Pequeno', 8,
     'Cadela idosa e delicada que aprecia companhia, colo e passeios curtos. Tem comportamento calmo e se adapta a ambientes silenciosos.', TRUE, '[0.94,0.13,0.82]'),
    ('A004', 'Bento', 'Cachorro', 'Medio', 3,
     'Cao amigavel, brincalhao e muito sociavel. Gosta de conhecer pessoas e conviver com outros cachorros. Tem energia moderada.', TRUE, '[0.64,0.52,0.98]'),
    ('A005', 'Nina', 'Cachorro', 'Pequeno', 5,
     'Cadela quieta e timida. Precisa de aproximacao paciente, prefere ambientes calmos e nao gosta de muito movimento. Aos poucos cria vinculos de confianca.', TRUE, '[0.87,0.19,0.25]'),
    ('A006', 'Toby', 'Cachorro', 'Medio', 1,
     'Cachorro jovem e cheio de energia. Adora brincar ao ar livre, aprender comandos e interagir com a familia. Ideal para tutores ativos.', TRUE, '[0.20,0.92,0.93]'),
    ('A007', 'Bob', 'Cachorro', 'Grande', 10,
     'Cao idoso e paciente. Prefere descansar, receber carinho e fazer caminhadas lentas. E gentil com visitantes e gosta de rotina tranquila.', TRUE, '[0.96,0.11,0.76]'),
    ('A008', 'Kiara', 'Cachorro', 'Medio', 4,
     'Cadela curiosa e independente. Aprecia trilhas, jogos de farejar e passeios diários. Convive bem com adultos e tem energia elevada.', TRUE, '[0.41,0.79,0.73]'),
    ('A009', 'Max', 'Cachorro', 'Grande', 3,
     'Cao atletico e territorial. Precisa de socializacao cuidadosa, orientacao e atividades fisicas. Prefere ser o unico pet da casa.', TRUE, '[0.33,0.84,0.27]'),
    ('A010', 'Amora', 'Cachorro', 'Pequeno', 6,
     'Cadela equilibrada e muito afetuosa. Costuma conviver bem com gatos e criancas e gosta de brincar sem excessos.', TRUE, '[0.73,0.42,0.93]'),
    ('A011', 'Zeca', 'Cachorro', 'Pequeno', 7,
     'Cachorro pequeno e reservado. Gosta de ficar em ambientes silenciosos, passear pouco e receber carinho de pessoas conhecidas.', FALSE, '[0.84,0.24,0.56]'),
    ('A012', 'Pipoca', 'Cachorro', 'Pequeno', 1,
     'Cadela muito animada, gosta de correr atras de brinquedos e brincar com outros caes. Busca familia com tempo para atividades.', TRUE, '[0.22,0.94,0.86]'),
    ('A013', 'Mimi', 'Gato', 'Pequeno', 5,
     'Gata calma e afetuosa. Gosta de colo, sofas confortaveis e companhia humana. Passa boa parte do dia descansando.', TRUE, '[0.95,0.16,0.88]'),
    ('A014', 'Simba', 'Gato', 'Pequeno', 2,
     'Gato curioso e brincalhao. Gosta de explorar prateleiras, perseguir brinquedos e interagir com pessoas. Tem muita energia.', TRUE, '[0.36,0.88,0.81]'),
    ('A015', 'Lola', 'Gato', 'Pequeno', 6,
     'Gata reservada que prefere locais silenciosos e esconderijos confortaveis. Precisa de paciencia para ganhar confianca.', TRUE, '[0.91,0.16,0.28]'),
    ('A016', 'Chico', 'Gato', 'Pequeno', 3,
     'Gato sociavel e divertido. Recebe visitas com curiosidade, brinca com bolinhas e adora seguir os moradores pela casa.', TRUE, '[0.60,0.62,0.94]'),
    ('A017', 'Frida', 'Gato', 'Pequeno', 4,
     'Gata independente que aprecia observar o movimento pela janela. Gosta de atividades tranquilas e de ter um cantinho so seu.', TRUE, '[0.82,0.29,0.38]'),
    ('A018', 'Cookie', 'Gato', 'Pequeno', 1,
     'Gato jovem que adora correr, saltar e explorar ambientes com arranhadores e brinquedos. Procura um lar com enriquecimento ambiental.', TRUE, '[0.23,0.96,0.76]'),
    ('A019', 'Sol', 'Gato', 'Pequeno', 8,
     'Gata mais velha e muito meiga. Gosta de um ambiente acolhedor, de sonecas longas e de receber carinho com calma.', TRUE, '[0.97,0.10,0.79]'),
    ('A020', 'Nuvem', 'Gato', 'Pequeno', 2,
     'Gata doce e tranquila que aprecia companhia silenciosa. Se adapta bem a apartamentos preparados para gatos.', TRUE, '[0.90,0.22,0.83]'),
    ('A021', 'Frajola', 'Gato', 'Pequeno', 3,
     'Gato curioso, esperto e brincalhao que gosta de desafios, circuitos e brinquedos interativos. Tem nivel de atividade alto.', TRUE, '[0.33,0.86,0.66]'),
    ('A022', 'Jade', 'Gato', 'Pequeno', 5,
     'Gata carinhosa e sociavel. Gosta de contato humano e costuma se adaptar gradualmente a outros gatos com introducao adequada.', TRUE, '[0.78,0.36,0.92]'),
    ('A023', 'Oliver', 'Gato', 'Pequeno', 4,
     'Gato timido e sensivel a barulhos. Prefere uma casa tranquila e tutores que respeitem seu espaco e seus horarios.', FALSE, '[0.86,0.21,0.24]'),
    ('A024', 'Biscoito', 'Gato', 'Pequeno', 2,
     'Gato extrovertido e brincalhao que gosta de interagir com criancas mais velhas e de explorar brinquedos. Energia moderada.', TRUE, '[0.55,0.66,0.90]')
ON CONFLICT (codigo) DO NOTHING;

-- Documentos do ABRIGO FICTICIO PetMatch. Sao regras simuladas,
-- NAO normas oficiais e nem orientacoes juridicas reais.
INSERT INTO petmatch.documentos_adocao (codigo, titulo, conteudo)
VALUES
    ('D001', 'Documentos para adotar',
     'No abrigo ficticio PetMatch, o candidato apresenta um documento de identificacao com foto e informa dados de contato. A equipe confirma as informacoes durante o atendimento.'),
    ('D002', 'Comprovante de residencia',
     'Para cadastro de adocao no PetMatch ficticio, solicitamos um comprovante de residencia recente para confirmar o endereco do adotante. A equipe pode explicar alternativas durante a entrevista.'),
    ('D003', 'Visita para conhecer o animal',
     'No PetMatch ficticio, o candidato agenda uma visita presencial para conhecer o animal antes da adocao. A equipe apresenta o temperamento e responde as perguntas.'),
    ('D004', 'Processo de adocao',
     'O fluxo do PetMatch ficticio inclui cadastro do interessado, conversa com a equipe, visita ao animal, avaliacao de compatibilidade e assinatura de um termo de responsabilidade.'),
    ('D005', 'Adocao em apartamento',
     'Moradores de apartamento podem solicitar adocao no PetMatch ficticio. A equipe conversa sobre seguranca de janelas, espaco adequado, passeios e necessidades individuais do animal.'),
    ('D006', 'Adocao com criancas',
     'Familias com criancas recebem orientacoes do PetMatch ficticio sobre aproximacao supervisionada, respeito aos sinais de desconforto dos animais e escolha de perfis compativeis.'),
    ('D007', 'Convivencia com outros animais',
     'Se a familia ja possui pets, a equipe do PetMatch ficticio conversa sobre apresentacao gradual, ambientes separados no inicio e acompanhamento da adaptacao.'),
    ('D008', 'Custos e doacoes',
     'Nesta simulacao educacional, o PetMatch nao cobra taxa obrigatoria pela adocao. Doacoes voluntarias sao bem-vindas, mas nao condicionam a adocao.'),
    ('D009', 'Cuidados de rotina',
     'A pessoa que adota deve considerar alimentacao adequada, agua, abrigo, exercicios, enriquecimento ambiental e acompanhamento veterinario ao longo da vida do animal.'),
    ('D010', 'Pos-adocao e contato',
     'Na simulacao PetMatch, o abrigo disponibiliza um contato para duvidas sobre adaptacao nas primeiras semanas e pode solicitar um retorno sobre o bem-estar do animal.')
ON CONFLICT (codigo) DO NOTHING;

-- Confira a carga:
SELECT especie, COUNT(*) AS quantidade
FROM petmatch.animais
GROUP BY especie
ORDER BY especie;

SELECT COUNT(*) AS documentos FROM petmatch.documentos_adocao;
