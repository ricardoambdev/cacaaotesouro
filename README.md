# Caça ao Tesouro

Sistema web de **painel de administração** + **API do jogo** "Caça ao Tesouro",
construído com **Slim 4** e **PHP 8.3**. Pensado para rodar em
**servidor compartilhado** (Apache/cPanel) e consumido por um **aplicativo
móvel** (Flutter).

## Requisitos

- PHP 8.1+ (recomendado 8.3) com extensões: `pdo`, `pdo_sqlite`,
  `pdo_mysql`, `session`, `mbstring`, `openssl`
- Composer 2
- MySQL (recomendado) ou SQLite (desenvolvimento local)

## Desenvolvimento local

```bash
composer install
npm install             # instala Tailwind CSS (devDependency)
npm run build:css       # compila public/assets/css/tailwind.css
composer start          # php -S localhost:8080 -t public
```

Para desenvolvimento com rebuild automático do CSS:

```bash
npm run watch:css       # observa mudanças no input.css e recompila
```

Acesse http://localhost:8080. Por padrão a aplicação usa **MySQL** (configurações
em `config.php`, ex.: `localhost`, usuário `root`). O banco `cacaaotesouro` e as
tabelas (`users`, `settings`, `treasures`, `teams`, `team_treasure_progress`,
`points_log`) são criados automaticamente no primeiro acesso — basta que o
usuário tenha permissão para criar banco. Para usar SQLite, altere `db.driver`
para `sqlite` em `config.php`.

O login do painel é feito com **usuário + senha** (não usa e-mail). A credencial
padrão de instalação é `admin` / `admin1234` — **troque a senha no primeiro
acesso** (via fluxo de recuperação).

---

## Conceito do jogo

A história do **Quico e sua bola quadrada**. Duas equipes — **Preta** e
**Laranja** — começam com **100 pontos**. Cada tesouro encontrado vale **+20**,
a selfie no local vale **+5**, errar a charada custa **-5** e a **senha final**
vale **+100** (errar custa **-20**). A primeira equipe a terminar **encerra a
partida** e é declarada vencedora.

### Fluxo de cada tesouro

1. O admin **cadastra o tesouro** no painel (código, nome, descrição, dica,
   duas charadas com respostas numéricas). O **QR SVG** é gerado
   automaticamente e o tesouro fica **inativo**.
2. O admin, no app, **confirma a coordenada** real do local (`/api/admin/confirm-coordinate`),
   o que **ativa** o tesouro.
3. A equipe, no app:
   - vai ao local (**GPS ≤ 30 m** do tesouro);
   - lê o **QR code** (`qr_code` == `qr_content`);
   - opcionalmente envia a **selfie** (**+5**);
   - responde a **charada assinalada** (1 ou 2): a charada é **sorteada para a
     primeira equipe** que chegar, e a **outra equipe recebe a outra** charada;
   - **acertou** → **+20** e recebe a **dica do próximo tesouro**;
   - **errou** → **-5** e permanece travada no mesmo tesouro (pode tentar de novo).
4. Ao completar todos os tesouros, o **desafio final**: a equipe digita a
   **senha** (`settings.finalAnswer`) → **+100** e termina.

### Ordem dos tesouros

Configurável em **Configurações → Jogo** (`settings.treasureOrder`):

- `estabelecida` — segue o `sort_order` definido no painel (reordenável);
- `aleatorio` — permutação aleatória **fixa por equipe** (gerada e persistida
  em `teams.order_sequence` no primeiro acesso; cada equipe tem a sua).

### Estados de equipe

| Coluna            | Descrição |
|-------------------|-----------|
| `points`          | Pontuação (inicia em 100) |
| `status`          | `playing` / `finished` |
| `finished_at`     | Quando a equipe terminou |
| `current_step`    | Quantos tesouros já resolveu |
| `session_token`   | `device_id` do aparelho logado (conexão única) |
| `order_sequence`  | JSON com a ordem aleatória (modo `aleatorio`) |

---

## Estrutura

```
app/
  bootstrap.php           Cria e configura a App Slim (rotas + middleware)
  database.php            Conexão PDO (singleton) + criação do esquema
  helpers.php             Funções globais: e(), redirect(), flash, CSRF, setting()
  middleware.php          startSession, csrf, authRequired, guestOnly
  View.php                Renderizador de templates PHP puros
  controllers/
    AuthController.php    Login/registro/recuperação do painel
    DashboardController.php Painel inicial
    TreasureController.php  CRUD de tesouros + reordenação
    SettingsController.php  Configurações gerais + jogo + equipes
    GameController.php      Páginas História, Desafio Final e Jogo
    ApiController.php       API JSON do jogo (equipes + admin + legado)
  repositories/
    UserRepository.php
    SettingsRepository.php
    TeamRepository.php
    TreasureRepository.php
    GameRepository.php      Ordem do jogo, progresso, charadas, pontos
  services/
    QrService.php           Geração/remoção de QR codes (SVG)
  views/                  Templates PHP (sem CSS)
public/
  index.php               Front controller
  .htaccess               Roteamento Apache
  uploads/qr/             QRs SVG dos tesouros (tesouro_<id>.svg)
  uploads/selfies/        Selfies enviadas pelas equipes
  assets/                 CSS/JS/imagens
sql/
  schema.mysql.sql        Esquema MySQL completo para importação manual
data/                     Banco SQLite local (não versionado)
config.php                Configuração global
```

### Equipes (Laranja e Preta)

Tabela `teams` (criada e semeada automaticamente pelo `Database::ensureSchema`,
ou manualmente via `sql/schema.mysql.sql`):

| Coluna         | Tipo          | Descrição                                   |
|----------------|---------------|---------------------------------------------|
| `id`           | INT PK        | Identificador                               |
| `name`         | VARCHAR(120)  | Nome de exibição (ex.: "Equipe Laranja")    |
| `color`        | VARCHAR(30)   | Identificador da equipe (`laranja`/`preta`) |
| `username`     | VARCHAR(50)   | UNIQUE — usuário de login da equipe         |
| `password`     | VARCHAR(100)  | Senha em **texto puro** (para o admin visualizar/editar) |
| `password_hash`| VARCHAR(255)  | Hash da senha (`password_hash()` do PHP) — usado na API |
| `points`       | INT           | Pontos (inicia em 100)                      |
| `session_token`| VARCHAR(64)   | `device_id` do aparelho conectado           |
| `status`       | VARCHAR(20)   | `playing` / `finished`                      |
| `finished_at`  | DATETIME      | Fim da equipe                               |
| `current_step` | INT           | Tesouros resolvidos                         |
| `order_sequence`| TEXT         | Permutação aleatória (JSON)                 |

Credenciais iniciais:

| Equipe | color     | username        | senha       |
|--------|-----------|-----------------|-------------|
| Laranja| `laranja` | `equipe_laranja`| `laranja123`|
| Preta  | `preta`   | `equipe_preta`  | `preta123`  |

> **Armazenamento da senha:** por exigência do admin, a senha de cada equipe
> é guardada em texto puro na coluna `password` para ser exibida/editada no
> formulário de Configurações. A coluna `password_hash` é quem autentica a API
> (`POST /api/team/login`). Sempre que a senha é alterada, ambos são gravados
> em sincronia. **Recomenda-se trocar as senhas periodicamente.**

---

## Painel web (admin)

Rotas com `authRequired` + CSRF. Login: `admin` / `admin1234`.

| Rota               | Método | Descrição |
|--------------------|--------|-----------|
| `/`                | GET    | Painel |
| `/tesouros`        | GET    | Lista tesouros com progresso das equipes e estado do jogo |
| `/tesouros/novo`   | GET/POST | Cadastra tesouro (gera QR SVG; `active=0`) |
| `/tesouros/{id}/editar` | GET/POST | Edita tesouro |
| `/tesouros/{id}/excluir` | POST | Exclui tesouro + QR SVG |
| `/tesouros/reorder`| POST   | Reordena `{ ids: [5,2,9,...] }` (JSON; exige `X-CSRF-Token`) |
| `/historia`        | GET/POST | História do jogo (`historyContent`, HTML) |
| `/desafio-final`   | GET/POST | Dica + senha final (`finalClue`, `finalAnswer`) |
| `/jogo`            | GET    | Estado geral (pontos, encontrados, vencedora) |
| `/configuracoes`   | GET/POST | Configurações gerais + API + equipes + jogo |

**Campos do formulário de tesouro:** `code` (único, 2–50), `name`,
`description`, `clue` (dica do próximo local), `riddle1`/`answer1`,
`riddle2`/`answer2` (respostas: **4 a 8 dígitos**, `/^\d{4,8}$/`). O
`qr_content` é **gerado automaticamente** (`random_alnum(20)`) junto com o
**QR SVG** em `public/uploads/qr/tesouro_<id>.svg`.

**Reordenação:** a view `tesouros.php` inclui um botão "Salvar nova ordem" que
faz `fetch` para `/tesouros/reorder` enviando o token CSRF no header
`X-CSRF-Token` (o `csrf_verify()` aceita o token em `$_POST['_token']` **ou** no
header `X-CSRF-Token`).

---

## API do jogo (JSON)

Todas as rotas ficam em `/api`, usam **JSON** e **não exigem token CSRF** (o
middleware CSRF ignora paths `/api`). A autenticação é manual:

- **Equipes**: sessão `$_SESSION['team']` + header **`X-Device-Id`** batendo
  com `teams.session_token`. Sem um dos dois → `401 {"error":"Não autenticado."}`.
- **Admin app**: sessão `$_SESSION['admin']` (login com as credenciais de
  `settings.adminUsername` / `settings.adminPassword`).

### Equipes

| Método | Rota | Body / Header | Descrição |
|--------|------|---------------|-----------|
| POST | `/api/team/login` | `{ username, password, device_id }` | Login; `device_id` ≥ 8 chars vira `session_token`. Se outro aparelho já logado → `409`. |
| POST | `/api/team/logout` | `X-Device-Id` | Limpa `session_token` (se bater) e destrói a sessão. |
| GET | `/api/team/state` | `X-Device-Id` | Estado completo: pontos, `current_step`, `gameActive`, história, tesouro atual, `final_available`, `final_clue` (se disponível), `leaderboard`. |
| POST | `/api/team/checkin` | `{ treasure_id, lat, lng, qr_code }` | Valida QR, GPS (≤ 30 m) e ordem de jogo; assinala a charada (1/2) e devolve a pergunta. |
| POST | `/api/team/selfie` | multipart `{ treasure_id, image }` | Envia selfie (jpg/png/webp ≤ 5 MB) → **+5**. |
| POST | `/api/team/answer` | `{ treasure_id, answer }` | Responde charada: acertou → **+20** e avança; errou → **-5** e `attempts++`. |
| GET | `/api/team/current` | `X-Device-Id` | Tesouro atual `{id, name, clue}` ou `final_available`. |
| POST | `/api/team/final-answer` | `{ answer }` | Senha final: correta → **+100**, `finished`, encerra a partida (primeira equipe vence); errada → **-20**. |
| GET | `/api/team/points` | `X-Device-Id` | `{ points, status }`. |

### Admin (app de gerenciamento)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/api/admin/login` | `{ username, password }` (settings) → sessão `admin`. |
| POST | `/api/admin/logout` | Encerra sessão de admin. |
| GET | `/api/admin/treasures` | Lista `{id, code, name, lat, lng, has_coord, active, qr_content}`. |
| POST | `/api/admin/confirm-coordinate` | `{ treasure_id, lat, lng, qr_code }` → grava coordenada e **ativa** o tesouro. |
| POST | `/api/admin/disconnect-all` | Limpa `session_token` de todas as equipes. |
| GET | `/api/admin/status` | Estado geral: `game`, `teams[]` (com `found_count`) e `treasures_progress[]` (`found_at` por equipe). |

### Públicas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/story` | `{ success, story }` (história do jogo). |
| GET | `/api/config` | Config pública: `appName`, `apiBaseUrl`, `devMode`. |

### Endpoints legados (compatibilidade com o app antigo)

`POST /api/login`, `POST /api/logout`, `GET /api/me`,
`GET /api/tesouros`, `POST /api/tesouros/{id}/coordenada`.

### Exemplos

**Login de equipe:**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/team/login \
  -H "Content-Type: application/json" \
  -d '{"username":"equipe_laranja","password":"laranja123","device_id":"DEV-AAA-001"}' \
  -c cookies.txt
```

```json
{
  "success": true,
  "team": { "id": 23, "name": "Equipe Laranja", "color": "laranja",
            "username": "equipe_laranja", "points": 100 }
}
```

Segundo login com outro aparelho → `409`:

```json
{ "success": false, "error": "Outro membro da equipe já está logado no aplicativo." }
```

**Estado:**

```bash
curl http://cacaaotesouro.sentapua/api/team/state \
  -b cookies.txt -H "X-Device-Id: DEV-AAA-001"
```

```json
{
  "success": true,
  "team": { "points": 100, "status": "playing", "current_step": 0 },
  "gameActive": "1",
  "story": "A história do Quico e sua bola quadrada...",
  "current_treasure": { "id": 12, "name": "Praça do Quico",
                        "clue": "Vá até o barril da vila...", "has_location": true },
  "final_available": false,
  "leaderboard": [ { "team": {"id":23,"name":"Equipe Laranja","color":"laranja"},
                     "points": 100, "status": "playing" } ]
}
```

**Check-in (GPS ≤ 30 m + QR):**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/team/checkin \
  -H "Content-Type: application/json" -H "X-Device-Id: DEV-AAA-001" -b cookies.txt \
  -d '{"treasure_id":12,"lat":-22.9068,"lng":-43.1729,"qr_code":"BRZUSVHZ5RLU2PMGVQNF"}'
```

```json
{
  "success": true,
  "message": "Check-in confirmado! Resolva a charada.",
  "assigned_riddle": 1,
  "riddle": "Quantas pernas tem o total de personagens da vila?",
  "selfie_question": true
}
```

Fora dos 30 m → `400` com `distance_m`; QR errado → `400` `"Código QR inválido."`;
tesouro fora da ordem → `400` `"Este não é o próximo tesouro."`.

**Selfie (multipart):**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/team/selfie \
  -H "X-Device-Id: DEV-AAA-001" -b cookies.txt \
  -F "treasure_id=12" -F "image=@selfie.jpg;type=image/jpeg"
```

```json
{ "success": true, "message": "+5 pontos pela selfie!", "points": 105 }
```

**Resposta:**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/team/answer \
  -H "Content-Type: application/json" -H "X-Device-Id: DEV-AAA-001" -b cookies.txt \
  -d '{"treasure_id":12,"answer":"0412"}'
```

```json
{
  "success": true, "correct": true, "message": "Resposta correta! +20 pontos.",
  "points": 125,
  "next": { "treasure": { "id": 2, "name": "Barril da Vila", "clue": "...",
                          "has_location": true },
            "final_available": false }
}
```

**Desafio final:**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/team/final-answer \
  -H "Content-Type: application/json" -H "X-Device-Id: DEV-AAA-001" -b cookies.txt \
  -d '{"answer":"123ABC#"}'
```

```json
{
  "success": true, "correct": true,
  "message": "Parabéns! +100 pontos. A caça terminou!",
  "points": 425,
  "winner": { "id": 23, "name": "Equipe Laranja", "color": "laranja" }
}
```

**Admin:**

```bash
curl -X POST http://cacaaotesouro.sentapua/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin1234"}' -c admin.cookies

curl -X POST http://cacaaotesouro.sentapua/api/admin/confirm-coordinate \
  -H "Content-Type: application/json" -b admin.cookies \
  -d '{"treasure_id":12,"lat":-22.9068,"lng":-43.1729,"qr_code":"BRZUSVHZ5RLU2PMGVQNF"}'
```

```json
{ "success": true, "message": "Coordenada confirmada. Tesouro ativado!", "lat": -22.9068, "lng": -43.1729 }
```

---

## Implantação em servidor compartilhado (cPanel)

1. **Instale as dependências localmente** e suba a pasta `vendor`:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   Envie todo o projeto para a hospedagem via FTP/Git, incluindo `vendor/`.

2. **Suba os arquivos** para a sua conta (ex.: `public_html/cacaaotesouro/`).

3. **Aponte o document root para `public/`** (cPanel → *Domains* → *Document
   Root* → caminho da pasta `public`). Assim `index.php` e os assets são
   servidos corretamente e o código (`app/`, `config.php`, `data/`) fica fora
   do acesso web.

4. **Configure o ambiente**:

   ```text
   APP_ENV=prod
   APP_URL=https://seu-dominio.com
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=seu_banco
   DB_USER=seu_usuario
   DB_PASS=sua_senha
   ```

5. **Crie o banco** no cPanel e importe `sql/schema.mysql.sql` (ou deixe a
   aplicação criar as tabelas automaticamente se o usuário tiver `CREATE`).

6. **Permissões**: `public/uploads/qr/` e `public/uploads/selfies/` precisam de
   escrita para o usuário do PHP (o QR SVG é gravado no cadastro e as selfies
   no upload).

## Segurança

- PDO com **prepared statements** em todas as consultas.
- Senhas com `password_hash()` / `password_verify()`.
- **CSRF**: token em sessão exigido em todo POST/PUT/DELETE/PATCH (exceto `/api`).
- `session_regenerate_id(true)` no login.
- Saída sempre escapada com `htmlspecialchars` (helper `e()`).
- Cookies de sessão HttpOnly + SameSite=Lax.

## Notas para o agente de frontend

Contrato de dados das views em `app/views/` — ver docblocks no topo de cada
template.

- `layout.php` → `$content`, `$user`, `$active`, `$siteName`, `$flash`
  (nav ativa: `painel`, `tesouros`, `jogo`, `historia`, `desafio-final`,
  `configuracoes`).
- `tesouros.php` → `$treasures` (com `progress[cor][found_at]`), `$teams`
  (indexadas por id), `$game` (`treasureOrder`, `gameActive`, `winner`).
- `tesouros_form.php` → `$title`, `$action`, `$treasure`, `$old`, `$errors`
  (campos: `code`, `name`, `description`, `clue`, `riddle1`, `answer1`,
  `riddle2`, `answer2`).
- `historia.php` → `$historyContent` (HTML; o frontend injeta o TinyMCE no
  `#historyContent`).
- `desafio_final.php` → `$finalClue`, `$finalAnswer`.
- `jogo.php` → `$teams[]` (com `points`, `status`, `found_count`,
  `current_step`, `finished_at`), `$game` (`gameActive`, `winner`, `treasureOrder`).
- `configuracoes.php` → `$config`, `$old`, `$errors`, `$lanIp`, `$systemUrl`,
  `$apiUrl`, `$hasApk`, `$devMode`, `$appUrl`, `$teams`, `$treasureOrder`,
  `$adminUsername` (abas: Geral, API & Desenvolvimento, Usuários das Equipes,
  Jogo).