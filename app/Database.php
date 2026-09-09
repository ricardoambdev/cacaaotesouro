<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Conexão com o banco de dados (singleton) e criação do esquema.
 *
 * Suporta SQLite (desenvolvimento) e MySQL (servidor compartilhado),
 * conforme a configuração em config.php (db.driver).
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static bool $schemaEnsured = false;

    /**
     * Retorna a conexão PDO única do processo.
     */
    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $driver = app_config('db.driver', 'sqlite');
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            if ($driver === 'sqlite') {
                $path = (string) app_config('db.sqlite_path');

                // Defensivo: o config.php do skeleton resolve o caminho do SQLite
                // com dirname(__DIR__), o que aponta UMA PASTA ACIMA do projeto.
                // Normaliza para dentro da pasta data/ do projeto para garantir
                // que o banco fique versionável/transportável com o sistema.
                $projectRoot = str_replace('\\', '/', dirname(__DIR__));
                $normalizedPath = str_replace('\\', '/', $path);

                if ($normalizedPath !== '' && !str_starts_with($normalizedPath, $projectRoot . '/')) {
                    $path = dirname(__DIR__) . '/data/' . basename($path);
                }

                $dir = dirname($path);

                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $dsn = 'sqlite:' . $path;
            } else {
                $host = (string) app_config('db.host', '127.0.0.1');
                $port = (string) app_config('db.port', '3306');
                $name = (string) app_config('db.name', 'cacaaotesouro');
                $user = (string) app_config('db.user', '');
                $pass = (string) app_config('db.pass', '');

                // MySQL: desativa prepared statements emulados e exige tipos reais.
                $options[PDO::ATTR_EMULATE_PREPARES] = false;

                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

                try {
                    self::$pdo = new PDO($dsn, $user, $pass, $options);
                } catch (PDOException $e) {
                    // Banco de dados inexistente (erro 1049 do driver MySQL):
                    // tenta criar o banco automaticamente e reconecta.
                    $driverCode = (int) ($e->errorInfo[1] ?? 0);

                    if ($driverCode !== 1049) {
                        throw $e;
                    }

                    $escapedName = str_replace('`', '``', $name);
                    $serverPdo = new PDO(
                        "mysql:host=$host;port=$port;charset=utf8mb4",
                        $user,
                        $pass,
                        $options
                    );
                    $serverPdo->exec(
                        "CREATE DATABASE IF NOT EXISTS `$escapedName` "
                        . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                    );
                    $serverPdo = null;

                    self::$pdo = new PDO($dsn, $user, $pass, $options);
                }
            }

            // SQLite: o branch acima apenas monta o DSN (não tem usuário/senha);
            // a conexão é criada aqui. Corrige o modo SQLite de desenvolvimento.
            if (self::$pdo === null && $driver === 'sqlite') {
                self::$pdo = new PDO($dsn, null, null, $options);
            }
        }

        return self::$pdo;
    }

    /**
     * Cria as tabelas (se não existirem) e semeia as configurações padrão.
     * Sintaxe compatível com SQLite e MySQL.
     *
     * NOTA: não altera tabelas já existentes (CREATE TABLE IF NOT EXISTS).
     * O banco atual (migrado) mantém seus dados intactos; apenas tabelas
     * novas (ex.: points_log) são criadas.
     */
    public static function ensureSchema(): void
    {
        $pdo = self::get();
        $driver = app_config('db.driver', 'sqlite');

        $autoIncrement = $driver === 'mysql'
            ? 'INT AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users ('
            . 'id ' . $autoIncrement . ', '
            . 'name VARCHAR(120) NOT NULL, '
            . 'username VARCHAR(50) NOT NULL UNIQUE, '
            . 'role VARCHAR(20) NOT NULL DEFAULT \'admin\', '
            . 'email VARCHAR(190) NULL, '
            . 'password_hash VARCHAR(255) NOT NULL, '
            . 'reset_token VARCHAR(64) NULL, '
            . 'reset_expires DATETIME NULL, '
            . 'created_at DATETIME NOT NULL'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS settings ('
            . '`key` VARCHAR(64) PRIMARY KEY, '
            . 'value TEXT NULL'
            . ')'
        );

        // Equipes (Laranja e Preta) com credenciais e estado de jogo.
        // `password` guarda a senha em TEXTO PURO para o admin visualizar/
        // editar; `password_hash` é usado na autenticação (API).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS teams ('
            . 'id ' . $autoIncrement . ', '
            . 'name VARCHAR(120) NOT NULL, '
            . 'color VARCHAR(30) NOT NULL UNIQUE, '
            . 'username VARCHAR(50) NOT NULL UNIQUE, '
            . 'password VARCHAR(100) NOT NULL DEFAULT \'\', '
            . 'password_hash VARCHAR(255) NOT NULL, '
            . 'points INT NOT NULL DEFAULT 100, '
            . 'session_token VARCHAR(64) NULL, '
            . 'status VARCHAR(20) NOT NULL DEFAULT \'playing\', '
            . 'finished_at DATETIME NULL, '
            . 'current_step INT NOT NULL DEFAULT 0, '
            . 'order_sequence TEXT NULL, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME NULL'
            . ')'
        );

        self::ensureTeamPasswordColumn($pdo, $driver);
        self::ensureDisqualifiedColumn($pdo, $driver);

        self::seedDefaultTeams($pdo, $driver);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS treasures ('
            . 'id ' . $autoIncrement . ', '
            . 'code VARCHAR(50) NOT NULL UNIQUE, '
            . 'name VARCHAR(190) NOT NULL, '
            . 'description TEXT NOT NULL, '
            . 'clue TEXT NOT NULL, '
            . 'riddle1 TEXT NOT NULL, '
            . 'answer1 VARCHAR(8) NOT NULL, '
            . 'riddle2 TEXT NOT NULL, '
            . 'answer2 VARCHAR(8) NOT NULL, '
            . 'qr_content VARCHAR(32) NOT NULL DEFAULT \'\', '
            . 'qr_svg_path VARCHAR(255) NOT NULL DEFAULT \'\', '
            . 'lat DECIMAL(10,7) NULL, '
            . 'lng DECIMAL(10,7) NULL, '
            . 'sort_order INT NOT NULL DEFAULT 0, '
            . 'active TINYINT(1) NOT NULL DEFAULT 0, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME NULL'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS team_treasure_progress ('
            . 'id ' . $autoIncrement . ', '
            . 'team_id INT NOT NULL, '
            . 'treasure_id INT NOT NULL, '
            . 'assigned_riddle TINYINT NULL, '
            . 'riddle_correct TINYINT(1) NOT NULL DEFAULT 0, '
            . 'attempts INT NOT NULL DEFAULT 0, '
            . 'selfie_path VARCHAR(255) NULL, '
            . 'selfie_points TINYINT(1) NOT NULL DEFAULT 0, '
            . 'gps_confirmed TINYINT(1) NOT NULL DEFAULT 0, '
            . 'found_at DATETIME NULL, '
            . 'points_awarded TINYINT(1) NOT NULL DEFAULT 0, '
            . 'disqualified TINYINT(1) NOT NULL DEFAULT 0, '
            . 'created_at DATETIME NOT NULL, '
            . 'updated_at DATETIME NULL, '
            . 'UNIQUE (team_id, treasure_id)'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS points_log ('
            . 'id ' . $autoIncrement . ', '
            . 'team_id INT NOT NULL, '
            . 'delta INT NOT NULL DEFAULT 0, '
            . 'reason VARCHAR(100) NOT NULL DEFAULT \'\', '
            . 'created_at DATETIME NOT NULL'
            . ')'
        );

        // Log de localização das equipes (o app envia a cada ~5s).
        // A última linha de cada equipe alimenta o telão (/api/telao).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS team_locations ('
            . 'id ' . $autoIncrement . ', '
            . 'team_id INT NOT NULL, '
            . 'lat DECIMAL(10,7) NOT NULL, '
            . 'lng DECIMAL(10,7) NOT NULL, '
            . 'accuracy DECIMAL(10,2) NULL, '
            . 'created_at DATETIME NOT NULL'
            . ')'
        );

        self::ensureTeamLocationsIndex($pdo, $driver);

        // Mensagens do admin para as equipes (lidas pelo app no /api/team/state).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS team_messages ('
            . 'id ' . $autoIncrement . ', '
            . 'team_id INT NOT NULL, '
            . 'message TEXT NOT NULL, '
            . 'read_at DATETIME NULL, '
            . 'created_at DATETIME NOT NULL'
            . ($driver === 'mysql' ? ', KEY idx_team_time (team_id, created_at)' : '')
            . ')'
        );

        // SQLite não suporta KEY inline; o índice é criado em separado (o
        // nome precisa ser distinto porque índices SQLite são globais).
        if ($driver !== 'mysql') {
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS idx_team_messages_time '
                . 'ON team_messages (team_id, created_at)'
            );
        }

        self::seedDefaultSettings($pdo, $driver);
    }

    /**
     * Garante que o esquema seja criado uma única vez por processo.
     */
    public static function boot(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        self::ensureSchema();
        self::$schemaEnsured = true;
    }

    /**
     * Garante que a coluna `password` (senha em texto puro, exibida/editada
     * pelo admin) exista na tabela `teams`.
     *
     * O CREATE TABLE IF NOT EXISTS acima não altera tabelas já existentes;
     * em bancos criados por versões antigas do sistema a coluna é adicionada
     * aqui de forma idempotente (roda a cada boot até existir). Se o usuário
     * do banco não tiver privilégio de ALTER, a falha é apenas registrada no
     * log — o restante do sistema continua funcionando (somente a edição de
     * senha de equipes fica indisponível).
     */
    private static function ensureTeamPasswordColumn(PDO $pdo, string $driver): void
    {
        try {
            if ($driver === 'sqlite') {
                $found = false;

                foreach ($pdo->query('PRAGMA table_info(teams)') as $column) {
                    if (strtolower((string) $column['name']) === 'password') {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $pdo->exec(
                        "ALTER TABLE teams ADD COLUMN password VARCHAR(100) NOT NULL DEFAULT ''"
                    );
                }

                return;
            }

            // MySQL/MariaDB.
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema = DATABASE() AND table_name = :table '
                . 'AND column_name = :column'
            );
            $stmt->execute([':table' => 'teams', ':column' => 'password']);

            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    "ALTER TABLE teams ADD COLUMN password VARCHAR(100) NOT NULL DEFAULT '' "
                    . 'AFTER username'
                );
            }
        } catch (PDOException $e) {
            error_log('Database::ensureTeamPasswordColumn: ' . $e->getMessage());
        }
    }

    /**
     * Garante o índice idx_team_time (team_id, created_at) na tabela
     * team_locations — usado pelo telão para buscar a última localização
     * de cada equipe.
     *
     * - SQLite: CREATE INDEX IF NOT EXISTS (sintaxe nativa).
     * - MySQL:  não suporta IF NOT EXISTS em CREATE INDEX; verifica
     *   information_schema.statistics antes (idempotente).
     */
    private static function ensureTeamLocationsIndex(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS idx_team_time '
                . 'ON team_locations (team_id, created_at)'
            );

            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table '
            . 'AND index_name = :index'
        );
        $stmt->execute([':table' => 'team_locations', ':index' => 'idx_team_time']);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE INDEX idx_team_time ON team_locations (team_id, created_at)'
            );
        }
    }

    /**
     * Garante que a coluna `disqualified` exista em `team_treasure_progress`
     * (migração idempotente para bancos criados por versões antigas — sem
     * isso, o /api/telao e o painel quebram com 500 "Unknown column").
     */
    private static function ensureDisqualifiedColumn(PDO $pdo, string $driver): void
    {
        try {
            if ($driver === 'sqlite') {
                $found = false;

                foreach ($pdo->query('PRAGMA table_info(team_treasure_progress)') as $column) {
                    if (strtolower((string) $column['name']) === 'disqualified') {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $pdo->exec(
                        "ALTER TABLE team_treasure_progress ADD COLUMN disqualified TINYINT(1) NOT NULL DEFAULT 0"
                    );
                }

                return;
            }

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema = DATABASE() AND table_name = :table '
                . 'AND column_name = :column'
            );
            $stmt->execute([':table' => 'team_treasure_progress', ':column' => 'disqualified']);

            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE team_treasure_progress '
                    . 'ADD COLUMN disqualified TINYINT(1) NOT NULL DEFAULT 0 AFTER points_awarded'
                );
            }
        } catch (PDOException $e) {
            error_log('Database::ensureDisqualifiedColumn: ' . $e->getMessage());
        }
    }

    private static function seedDefaultSettings(PDO $pdo, string $driver): void
    {
        $defaults = [
            // Gerais
            'siteName'         => 'Caça ao Tesouro',
            'description'      => 'Encontre os tesouros escondidos!',
            'supportEmail'     => '',
            'soundEnabled'     => '1',
            'animationEnabled' => '1',
            'itemsPerPage'     => '10',
            'apiBaseUrl'       => '',
            'apiDevMode'       => '1',
            // Jogo
            'treasureOrder'    => 'estabelecida',
            'adminUsername'    => 'admin',
            'adminPassword'    => 'admin1234',
            'historyContent'   => '',
            'storyVersion'     => '0',
            'finalClue'        => '',
            'finalAnswer'      => '',
            'finalCorrectPoints'=> '100',
            'finalWrongPenalty' => '20',
            'gameActive'       => '0',
            'winnerTeamId'     => '',
            // Regras do jogo (enforcement via ApiController::gameBlock)
            'gameStatus'       => 'playing', // 'playing' | 'paused' | 'finished'
            'gameStartDate'    => '',        // YYYY-MM-DD (vazio = sem restrição)
            'gameStartTime'    => '08:00',
            'gameEndTime'      => '17:00',
        ];

        $sql = $driver === 'mysql'
            ? 'INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (:key, :value)'
            : 'INSERT OR IGNORE INTO `settings` (`key`, `value`) VALUES (:key, :value)';

        $stmt = $pdo->prepare($sql);

        foreach ($defaults as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
    }

    /**
     * Semeia as equipes padrão (Laranja e Preta) com credenciais iniciais.
     *
     * `password` (texto puro) e `password_hash` são gravados em sincronia com
     * a mesma senha. Insere uma equipe apenas quando NENHUMA equipe com a
     * mesma `color` existe ainda (a coluna color pode não ter constraint
     * UNIQUE em todos os ambientes — ex.: MySQL importado manualmente). Assim
     * a seed é idempotente e nunca sobrescreve credenciais já alteradas pelo
     * admin.
     */
    private static function seedDefaultTeams(PDO $pdo, string $driver): void
    {
        $teams = [
            [
                'name'          => 'Equipe Laranja',
                'color'         => 'laranja',
                'username'      => 'equipe_laranja',
                'password'      => 'laranja123',
                'password_hash' => password_hash('laranja123', PASSWORD_DEFAULT),
            ],
            [
                'name'          => 'Equipe Preta',
                'color'         => 'preta',
                'username'      => 'equipe_preta',
                'password'      => 'preta123',
                'password_hash' => password_hash('preta123', PASSWORD_DEFAULT),
            ],
        ];

        $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE color = :color');

        foreach ($teams as $team) {
            $existsStmt->execute([':color' => $team['color']]);
            $count = (int) $existsStmt->fetchColumn();

            if ($count > 0) {
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO teams '
                . '(name, color, username, password, password_hash, points, status, '
                . 'current_step, created_at) '
                . 'VALUES (:name, :color, :username, :password, :password_hash, '
                . '100, \'playing\', 0, :created_at)'
            );

            $stmt->execute([
                ':name'          => $team['name'],
                ':color'         => $team['color'],
                ':username'      => $team['username'],
                ':password'      => $team['password'],
                ':password_hash' => $team['password_hash'],
                ':created_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Semeia os dados PRIMÁRIOS do sistema (usado pelo instalador no
     * primeiro acesso): usuário admin e os 10 tesouros iniciais.
     *
     * Idempotente: só insere quando as tabelas estão vazias.
     */
    public static function seedPrimaryData(): void
    {
        $pdo = self::get();
        $driver = app_config('db.driver', 'mysql');

        // ── Usuário administrador ─────────────────────────────
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        if ($count === 0) {
            $pdo->prepare(
                'INSERT INTO users (name, username, role, email, password_hash, created_at) '
                . 'VALUES (:name, :username, :role, :email, :password_hash, :created_at)'
            )->execute([
                ':name'          => 'Admin',
                ':username'      => 'admin',
                ':role'          => 'admin',
                ':email'         => 'admin@cacaaotesouro.com',
                ':password_hash' => password_hash('admin1234', PASSWORD_DEFAULT),
                ':created_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        // ── Tesouros iniciais (apenas se não houver nenhum) ───
        $count = (int) $pdo->query('SELECT COUNT(*) FROM treasures')->fetchColumn();

        if ($count > 0) {
            return;
        }

        $treasures = [
            ['code' => 'T01', 'name' => 'Praça do Quico', 'description' => 'A praça onde o Quico brinca de bola com a Chiquinha.', 'clue' => 'Procure o banco onde o Quico senta para esperar a Chiquinha.', 'riddle1' => 'Quantas pernas tem o total de personagens da vila?', 'answer1' => '0412', 'riddle2' => 'Qual o número da casa da bruxa do 71?', 'answer2' => '0071'],
            ['code' => 'T02', 'name' => 'Barril da Vila', 'description' => 'Um barril antigo na entrada da vila.', 'clue' => 'Atrás do barril com o buraco redondo.', 'riddle1' => 'Quantas rodas tem o carro do Sr. Madruga?', 'answer1' => '0004', 'riddle2' => 'Quantas letras tem CHAVES?', 'answer2' => '0006'],
            ['code' => 'T03', 'name' => 'Portão do Colégio', 'description' => 'O portão da escola onde todos estudam.', 'clue' => 'No portão, do lado de fora, há um número pintado.', 'riddle1' => 'Qual o ano em que a série começou?', 'answer1' => '1972', 'riddle2' => 'Quantos dedos tem a mão do Professor Girafales?', 'answer2' => '0005'],
            ['code' => 'T04', 'name' => 'Pipoca da Chiquinha', 'description' => 'A barraquinha de pipoca da Chiquinha.', 'clue' => 'Atrás da barraquinha há uma sacola de pipoca amarela.', 'riddle1' => 'Quantos grãos de milho tem uma espiga média?', 'answer1' => '0800', 'riddle2' => 'Quantos minutos tem uma hora?', 'answer2' => '0060'],
            ['code' => 'T05', 'name' => 'Muralha do Pátio', 'description' => 'O muro onde as crianças se escondem.', 'clue' => 'Entre o muro e a árvore grande.', 'riddle1' => 'Quantos dias tem uma semana?', 'answer1' => '0007', 'riddle2' => 'Quantos lados tem um quadrado?', 'answer2' => '0004'],
            ['code' => 'T06', 'name' => 'Caixa do Correio', 'description' => 'A caixa de correio da vila.', 'clue' => 'Do lado da caixa azul, próximo ao poste.', 'riddle1' => 'Quantas cores tem o arco-íris?', 'answer1' => '0007', 'riddle2' => 'Quantos zeros tem 100?', 'answer2' => '0002'],
            ['code' => 'T07', 'name' => 'Fonte dos Desejos', 'description' => 'A fonte no centro da praça.', 'clue' => 'Na borda da fonte, onde a água não alcança.', 'riddle1' => 'Quantas pernas tem uma cadeira?', 'answer1' => '0004', 'riddle2' => 'Quantos palitos tem uma caixa de fósforos comum?', 'answer2' => '0030'],
            ['code' => 'T08', 'name' => 'Venda do Seu Barriga', 'description' => 'A despensa do Seu Barriga.', 'clue' => 'Do lado direito da porta da venda.', 'riddle1' => 'Quantos dias tem um ano?', 'answer1' => '0365', 'riddle2' => 'Quantas semanas tem um mês médio?', 'answer2' => '0004'],
            ['code' => 'T09', 'name' => 'Bicicleta da Chiquinha', 'description' => 'O local onde a Chiquinha guarda sua bicicleta.', 'clue' => 'Perto do corrimão, embaixo da escada.', 'riddle1' => 'Quantos pneus tem uma bicicleta?', 'answer1' => '0002', 'riddle2' => 'Quantas letras tem SANCHEZ?', 'answer2' => '0008'],
            ['code' => 'T10', 'name' => 'Esconderijo do Quico', 'description' => 'O esconderijo secreto do Quico.', 'clue' => 'No canto do pátio, atrás das caixas empilhadas.', 'riddle1' => 'Quantos lados tem um triângulo?', 'answer1' => '0003', 'riddle2' => 'Quantas cordas tem um violão?', 'answer2' => '0006'],
        ];

        $sort = 0;
        $insert = $pdo->prepare(
            'INSERT INTO treasures '
            . '(code, name, description, clue, riddle1, answer1, riddle2, answer2, '
            . 'qr_content, qr_svg_path, sort_order, active, created_at) '
            . 'VALUES (:code, :name, :description, :clue, :riddle1, :answer1, '
            . ':riddle2, :answer2, :qr_content, :qr_svg_path, :sort_order, 0, :created_at)'
        );

        $updateQr = $pdo->prepare(
            'UPDATE treasures SET qr_content = :qr_content, qr_svg_path = :qr_svg_path WHERE id = :id'
        );

        foreach ($treasures as $t) {
            $sort++;
            $code = random_alnum(20);

            $insert->execute([
                ':code'          => $t['code'],
                ':name'          => $t['name'],
                ':description'   => $t['description'],
                ':clue'          => $t['clue'],
                ':riddle1'       => $t['riddle1'],
                ':answer1'       => $t['answer1'],
                ':riddle2'       => $t['riddle2'],
                ':answer2'       => $t['answer2'],
                ':qr_content'    => $code,
                ':qr_svg_path'   => '',
                ':sort_order'    => $sort,
                ':created_at'    => date('Y-m-d H:i:s'),
            ]);

            $id = (int) $pdo->lastInsertId();
            $qrPath = '';

            try {
                $qrPath = \App\Services\QrService::generateSvg($code, $id);
            } catch (\Throwable $e) {
                error_log('Database::seedPrimaryData QR: ' . $e->getMessage());
            }

            $updateQr->execute([
                ':qr_content'    => $code,
                ':qr_svg_path'   => $qrPath,
                ':id'            => $id,
            ]);
        }
    }
}