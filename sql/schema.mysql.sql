-- ============================================================
-- Caça ao Tesouro - Esquema MySQL (servidor compartilhado / cPanel)
-- ------------------------------------------------------------
-- Importe este arquivo no phpMyAdmin (ou via CLI) para criar o
-- banco manualmente. Este passo é OPCIONAL: se o usuário do banco
-- tiver permissão de CREATE, a aplicação cria as tabelas e semeia
-- as configurações automaticamente no primeiro acesso
-- (auto-create via Database::ensureSchema).
--
-- Recomendado: charset utf8mb4 (acentuação e emojis).
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)  NOT NULL,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    role          VARCHAR(20)   NOT NULL DEFAULT 'admin',
    email         VARCHAR(190)  NULL,
    password_hash VARCHAR(255)  NOT NULL,
    reset_token   VARCHAR(64)   NULL,
    reset_expires DATETIME      NULL,
    created_at    DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) PRIMARY KEY,
    value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão (ignoradas se já existirem)
INSERT INTO settings (`key`, `value`) VALUES
    ('siteName',         'Caça ao Tesouro'),
    ('description',      'Encontre os tesouros escondidos!'),
    ('supportEmail',     ''),
    ('soundEnabled',     '1'),
    ('animationEnabled', '1'),
    ('itemsPerPage',     '10'),
    ('apiBaseUrl',       ''),
    ('apiDevMode',       '1'),
    ('treasureOrder',    'estabelecida'),
    ('adminUsername',    'admin'),
    ('adminPassword',    'admin1234'),
    ('historyContent',   ''),
    ('finalClue',        ''),
    ('finalAnswer',      ''),
    ('gameActive',       '0'),
    ('winnerTeamId',     '')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ============================================================
-- Tesouros
-- ------------------------------------------------------------
-- Cada tesouro guarda o conteúdo do QR code (qr_content, 20 chars
-- aleatórios), o caminho web do SVG (qr_svg_path), a coordenada
-- real (lat/lng, confirmada pelo admin no app), a ordem de jogo
-- (sort_order) e se está ativo. `clue` é a dica do PRÓXIMO local;
-- riddle1/answer1 e riddle2/answer2 são as DUAS charadas possíveis
-- (uma é sorteada para cada equipe).
-- ============================================================

CREATE TABLE IF NOT EXISTS treasures (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)   NOT NULL UNIQUE,
    name        VARCHAR(190)  NOT NULL,
    description TEXT          NOT NULL,
    clue        TEXT          NOT NULL,
    riddle1     TEXT          NOT NULL,
    answer1     VARCHAR(8)    NOT NULL,
    riddle2     TEXT          NOT NULL,
    answer2     VARCHAR(8)    NOT NULL,
    qr_content  VARCHAR(32)   NOT NULL DEFAULT '',
    qr_svg_path VARCHAR(255)  NOT NULL DEFAULT '',
    lat         DECIMAL(10,7) NULL,
    lng         DECIMAL(10,7) NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    active      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL,
    updated_at  DATETIME      NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed dos 10 tesouros (QR SVG pré-gerado em public/uploads/qr).
-- ATENÇÃO: qr_content abaixo são exemplos; num banco real os códigos
-- são gerados por random_alnum() no cadastro (20 chars).
INSERT INTO treasures
    (code, name, description, clue, riddle1, answer1, riddle2, answer2,
     qr_content, qr_svg_path, sort_order, active, created_at)
VALUES
    ('T01', 'Praça do Quico', 'O lugar onde o Quico perdeu a bola quadrada.',
     'Vá até o barril da vila e encontre a próxima pista.',
     'Quantos bancos cercam a praça do Quico?', '1984',
     'Em que ano o Chaves estreou na TV?', '1973',
     'ABC123XYZW789456Q', '/uploads/qr/tesouro_1.svg', 1, 1, NOW()),
    ('T02', 'Barril da Vila', 'O famoso barril onde o Seu Barriga costuma aparecer.',
     'Procure o portão do colégio para a próxima dica.',
     'Quantas tábuas tem o barril da vila?', '4321',
     'Qual o número da casa do Seu Madruga?', '42',
     'DEF456UVWX123890R', '/uploads/qr/tesouro_2.svg', 2, 1, NOW()),
    ('T03', 'Portão do Colégio', 'O colégio onde estudam as crianças da vila.',
     'A pipoca da Chiquinha está esperando por você.',
     'Que horas abre o colégio?', '0700',
     'Quantos degraus tem a escada do colégio?', '10',
     'GHI789QRST456012S', '/uploads/qr/tesouro_3.svg', 3, 1, NOW()),
    ('T04', 'Pipoca da Chiquinha', 'A barraquinha da pipoca de coco.',
     'Siga para a muralha do pátio.',
     'Qual o preço da pipoca de coco?', '0500',
     'Em que ano a Chiquinha apareceu pela primeira vez?', '1973',
     'JKL012MNOP789234T', '/uploads/qr/tesouro_4.svg', 4, 1, NOW()),
    ('T05', 'Muralha do Patio', 'O muro onde as crianças jogam bola.',
     'A caixa do correio tem uma carta para você.',
     'Quantos tijolos tem a muralha do pátio?', '1234',
     'Qual o placar da última partida?', '2030',
     'MNO345QRST012345U', '/uploads/qr/tesouro_5.svg', 5, 1, NOW()),
    ('T06', 'Caixa do Correio', 'O correio da vila, sempre cheio de cartas.',
     'A fonte dos desejos guarda o próximo segredo.',
     'Quantas cartas chegam por dia?', '1122',
     'Qual o CEP da vila?', '01001',
     'PQR678UVWX345678V', '/uploads/qr/tesouro_6.svg', 6, 1, NOW()),
    ('T07', 'Fonte dos Desejos', 'A fonte onde todos jogam uma moeda.',
     'A venda do Seu Barriga tem o que você procura.',
     'Quantas moedas há na fonte?', '3344',
     'Em que ano a fonte foi construída?', '1968',
     'STU901YZAB678901W', '/uploads/qr/tesouro_7.svg', 7, 1, NOW()),
    ('T08', 'Venda do Seu Barriga', 'A venda onde o Seu Barriga cobra o aluguel.',
     'A bicicleta da Chiquinha está no caminho.',
     'Quanto custa o picolé na venda?', '0100',
     'Qual o número do apartamento 12?', '12',
     'VWX234CDEF901234X', '/uploads/qr/tesouro_8.svg', 8, 1, NOW()),
    ('T09', 'Bicicleta da Chiquinha', 'A bicicleta rosa da Chiquinha.',
     'O esconderijo do Quico é o último lugar.',
     'Quantos raios tem a roda da bicicleta?', '3636',
     'Em que ano a Chiquinha ganhou a bicicleta?', '1985',
     'YZA567GHIJ123456Y', '/uploads/qr/tesouro_9.svg', 9, 1, NOW()),
    ('T10', 'Esconderijo do Quico', 'O esconderijo secreto do Quico.',
     'Desafio final: vá até o colégio e abra o cofre.',
     'Quantos segredos o Quico guarda?', '7777',
     'Qual o ano da bola quadrada?', '2024',
     'BCD890KLMN234567Z', '/uploads/qr/tesouro_10.svg', 10, 1, NOW())
ON DUPLICATE KEY UPDATE `code` = `code`;

-- ============================================================
-- Equipes (Laranja e Preta)
-- ------------------------------------------------------------
-- Cada equipe tem credenciais próprias (username + senha) para
-- fazer login na API móvel (POST /api/team/login). O `username` é
-- único e `color` identifica a equipe ('laranja' | 'preta').
--
-- Estado de jogo: points (inicia com 100), status
-- ('playing' | 'finished'), finished_at, current_step (quantos
-- tesouros já foram resolvidos) e order_sequence (permutação
-- aleatória fixa quando treasureOrder = 'aleatorio').
--
-- `password` guarda a senha em TEXTO PURO para o admin visualizar/
-- editar no formulário de Configurações. `password_hash`
-- (password_hash() do PHP) é usada pela autenticação da API e é
-- mantida sincronizada com `password`.
-- ============================================================

CREATE TABLE IF NOT EXISTS teams (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(120)  NOT NULL,
    color          VARCHAR(30)   NOT NULL UNIQUE,
    username       VARCHAR(50)   NOT NULL UNIQUE,
    password       VARCHAR(100)  NOT NULL DEFAULT '',
    password_hash  VARCHAR(255)  NOT NULL,
    points         INT           NOT NULL DEFAULT 100,
    session_token  VARCHAR(64)   NULL,
    status         VARCHAR(20)   NOT NULL DEFAULT 'playing',
    finished_at    DATETIME      NULL,
    current_step   INT           NOT NULL DEFAULT 0,
    order_sequence TEXT          NULL,
    created_at     DATETIME      NOT NULL,
    updated_at     DATETIME      NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credenciais iniciais (ignoradas se a equipe já existir).
INSERT INTO teams (name, color, username, password, password_hash, points, created_at) VALUES
    ('Equipe Laranja', 'laranja', 'equipe_laranja', 'laranja123', '$2y$10$anh6jAfn/msIxB2Ym3EcEOztExFBFYIP6ioLTHFwUqA0zRDzQngsC', 100, NOW()),
    ('Equipe Preta',   'preta',   'equipe_preta',   'preta123',   '$2y$10$fDP4.YS2yl8x63TUJjxWZuDeTLCXUNw0OgGKM1.UbVw1X6XT35Ilm', 100, NOW())
ON DUPLICATE KEY UPDATE `color` = `color`;

-- ============================================================
-- Progresso de cada equipe em cada tesouro
-- ------------------------------------------------------------
-- assigned_riddle: 1 ou 2 (charada sorteada; a outra equipe recebe
-- a outra). riddle_correct: acertou a charada. attempts: tentativas
-- erradas. selfie_path/selfie_points: selfie enviada (+5). 
-- gps_confirmed: check-in realizado no local (GPS <= 30m + QR).
-- found_at: quando a charada foi respondida corretamente.
-- ============================================================

CREATE TABLE IF NOT EXISTS team_treasure_progress (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    team_id          INT          NOT NULL,
    treasure_id      INT          NOT NULL,
    assigned_riddle  TINYINT      NULL,
    riddle_correct   TINYINT(1)   NOT NULL DEFAULT 0,
    attempts         INT          NOT NULL DEFAULT 0,
    selfie_path      VARCHAR(255) NULL,
    selfie_points    TINYINT(1)   NOT NULL DEFAULT 0,
    gps_confirmed    TINYINT(1)   NOT NULL DEFAULT 0,
    found_at         DATETIME     NULL,
    points_awarded   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NULL,
    UNIQUE (team_id, treasure_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Histórico de pontuação (auditoria)
-- ============================================================

CREATE TABLE IF NOT EXISTS points_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    team_id    INT          NOT NULL,
    delta      INT          NOT NULL DEFAULT 0,
    reason     VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;