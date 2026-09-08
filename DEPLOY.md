# 🏴☠️ Plano de Implantação — Caça ao Tesouro

**Ambiente-alvo:** servidor compartilhado (Apache + PHP + MySQL via cPanel)
**Stack:** PHP 8.1+ / Slim 4 / MySQL 5.7+ ou 8 / Apache (.htaccess)

---

## 1. Resumo do fluxo

```
[Computador de desenvolvimento]          [Servidor compartilhado (cPanel)]
  ─ MÉTODO A (recomendado): git clone ─►  6. git clone (Git Version Control)
                                          7. Document root → /public
                                          8. Criar banco MySQL
                                          9. Importar sql/schema.mysql.sql
                                         10. Configurar config.php
                                         11. Permissões + e-mail
                                         12. Testar (web + app)

  ─ MÉTODO B: pacote de deploy ──────────►  6. Upload + extração (File Manager)
                                          7–12. Mesmos passos
```

---

## 2. Pré-requisitos no servidor (cPanel)

| Requisito | Detalhe |
|---|---|
| **PHP 8.1+** (ideal 8.3) | Seletor de versão do PHP (MultiPHP Manager) |
| **Extensões PHP** | `pdo_mysql`, `gd`, `mbstring`, `openssl`, `session`, `json`, `fileinfo`, `ctype`, `curl` |
| **MySQL** | Banco + usuário criados no cPanel (*MySQL Databases*) |
| **Apache** | Padrão do cPanel (suporta `.htaccess`) |
| **Domínio** | Aponte o **document root** para a pasta `public` |

---

## 3. Gerando o pacote (Método B — se a hospedagem NÃO tiver git)

> Se sua hospedagem tiver **Git Version Control** (seção 4, Método A), pule
> este passo — basta clonar o repositório.

```bash
# 1. Dependências do backend
composer install --no-dev --optimize-autoloader

# 2. CSS Tailwind (gera public/assets/css/tailwind.css)
npm install
npm run build:css

# 3. (OPCIONAL) APK atualizado do aplicativo
cd mobile && flutter build apk --release && cd ..

# 4. Gerar o pacote de deploy
./deploy.sh                # básico
./deploy.sh yes            # com APK
./deploy.sh yes yes        # com APK + backup do banco (requer DB_* )
```

O comando cria **`deploy/cacaaotesouro-<data>.tar.gz`** com:

```
app/                  Código da aplicação (controllers, repos, views)
public/               Document root (index.php, .htaccess, assets, uploads)
sql/                  schema.mysql.sql (e backup.sql se exportado)
vendor/               Dependências Composer (Slim, QRCode)
config.php            Configuração (editar no servidor)
composer.json/lock
README.md · DEPLOY.md
```

> O pacote **não** inclui: `.git`, `node_modules`, `mobile/` (código-fonte),
> `data/` (sqlite), builds do Flutter. Os QR SVGs já gerados e o APK
> (quando incluído) vão junto em `public/uploads/`.

---

## 4. Enviando para o cPanel

### Método A — git clone (RECOMENDADO e mais simples)

Se a hospedagem tiver **Git Version Control** no cPanel (ou SSH com git):

1. No cPanel: **Git Version Control → Create**.
2. Preencha:
   - **Clone URL:** `https://github.com/ricardoambdev/cacaaotesouro.git`
   - **Repository Path:** `public_html/cacaaotesouro`
3. O repositório é **privado** → você precisa autorizar o acesso. Opções:
   - **GitHub Personal Access Token** (clássico, com permissão `repo`):
     clone URL vira `https://<TOKEN>@github.com/ricardoambdev/cacaaotesouro.git`
     (o token fica no histórico do cPanel — revogue quando terminar, ou use o
     **Deploy Key** abaixo);
   - **Deploy Key** (mais seguro): em GitHub → repo → *Settings → Deploy keys*,
     adicione uma chave pública SSH e use o URL SSH
     `git@github.com:ricardoambdev/cacaaotesouro.git`.
4. Clique **Create** — o cPanel clona o projeto.

> O repositório já contém **`vendor/`** (dependências Composer) e o
> **`tailwind.css` compilado** — **não precisa de Composer nem build no
> servidor**. Para atualizar depois: *Git Version Control → Update from Remote*.

### Método B — pacote de deploy (sem git na hospedagem)

1. Acesse o cPanel → **File Manager** (ou use FTP/FileZilla).
2. Entre na pasta do site (ex.: `public_html/cacaaotesouro/`).
3. Envie o `.tar.gz` e **extraia** ali (botão "Extract").
4. Confirme que a estrutura ficou: `public_html/cacaaotesouro/{app,public,vendor,...}`.

---

## 5. Configurando o servidor

### 5.1 Acesso pela raiz (sem mudar document root)

A raiz do projeto já contém um **`.htaccess`** que redireciona tudo para
`public/` automaticamente. Ou seja:

- **Não é preciso** apontar o document root para `public/` no cPanel.
- Acessando `https://SEU-DOMINIO.com/` o sistema abre direto (o `.htaccess`
  roteia para `public/index.php`, serve os assets de `public/` e protege
  arquivos sensíveis como `config.php` e `composer.json`).

> Se preferir a configuração clássica, pode aponhar o document root para
> `public/` — o `.htaccess` da raiz fica inativo nesse caso (nunca é
> alcançado) e o `.htaccess` interno de `public/` assume.

### 5.2 Banco de dados — INSTALADOR AUTOMÁTICO (recomendado)

O sistema tem um **instalador no primeiro acesso**: se o banco ainda não
estiver configurado (`data/install.php` ausente) ou a conexão falhar, a tela
**"Instalação"** aparece pedindo as configurações do banco (driver, host,
porta, nome, usuário, senha, nome do site e URL).

1. Acesse `https://SEU-DOMINIO.com/` → abre o instalador.
2. Preencha as credenciais do banco criado no cPanel.
3. Clique **Instalar sistema**.
4. Ao conectar, o instalador **cria o banco (se puder), as tabelas e os
   dados primários**: admin, equipes Laranja/Preta, configurações e os 10
   tesouros iniciais (com QR SVG gerado).
5. Redireciona para o login.

**Importação manual (alternativa):** se preferir, pode importar
`sql/schema.mysql.sql` no phpMyAdmin antes de acessar — o instalador
perceberá que o banco já está pronto (apenas pede as credenciais) e não
duplica dados.

### 5.3 Configuração (config.php ou variáveis de ambiente)

Use o template **`config.production.example.php`** (renomeie para
`config.php` no servidor) e preencha:

| Chave | Valor de produção |
|---|---|
| `app.env` | `prod` (nunca `dev` em produção) |
| `app.url` | `https://SEU-DOMINIO.com` |
| `db.driver` | `mysql` |
| `db.host/name/user/pass` | credenciais do cPanel |

**Recomendado (sem expor credenciais no código):** defina variáveis de
ambiente no **MultiPHP INI Editor** do cPanel:

```
APP_ENV=prod
APP_URL=https://SEU-DOMINIO.com
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=usuario_cacaaotesouro
DB_USER=usuario_cacaaotesouro
DB_PASS=SuaSenha
```

> O `config.php` enviado no pacote já lê essas variáveis com
> `getenv()`. Se elas existirem, sobrepõem os valores padrão.

### 5.4 Permissões

| Caminho | Permissão |
|---|---|
| `public/uploads/` (qr, selfies, apk) | **escrita** para o PHP (ex.: `755` / `775`) |
| `public/uploads/qr` | escrita (geração de QR no cadastro) |
| `public/uploads/selfies` | escrita (selfies das equipes) |
| Demais pastas | somente leitura (ex.: `755`) |

### 5.5 E-mail (recuperação de senha)

Em produção, a recuperação usa `mail()` do PHP (funciona de imediato no
cPanel). Para melhor entregabilidade, configure **SPF/DKIM** no domínio e
use um remetente como `no-reply@seu-dominio.com.br`.

---

## 6. Credenciais iniciais (ALTERAR após o primeiro acesso!)

| Acesso | Usuário | Senha inicial |
|---|---|---|
| Web admin | `admin` | `admin1234` |
| App admin | `admin` | `admin1234` |
| Equipe Laranja | `equipe_laranja` | `laranja123` |
| Equipe Preta | `equipe_preta` | `preta123` |
| Senha do desafio final | — | `123ABC#` (editar em Desafio Final) |

- Troque a senha do admin web na sua conta (cadastro novo + exclusão do
  padrão, ou mantenha se ambiente interno).
- Ajuste admin/equipes em **Configurações → abas Jogo e Equipes**.
- Em produção, `APP_ENV=prod` **esconde** o link de recuperação da tela.

---

## 7. Testes pós-implantação

1. `https://SEU-DOMINIO.com/login` → abre e redireciona para login.
2. Login admin → painel, tesouros, história, desafio final, config.
3. Crie um tesouro → QR SVG gerado + botão **Baixar QR**.
4. Configurações → aba Jogo: ordene tesouros, defina história e desafio.
5. API: `https://SEU-DOMINIO.com/api/config` → JSON com `devMode:false`.
6. App: informe o endereço `https://SEU-DOMINIO.com` (o app acrescenta
   `/api` e, com `devMode:false`, **não pede mais** o endereço).

---

## 8. Configurando o aplicativo para produção

1. `mobile/lib/services/api_service.dart` → `baseUrl` =
   `https://SEU-DOMINIO.com/api`.
2. `cd mobile && flutter build apk --release`.
3. Coloque o APK em `public/uploads/apk/cacaaotesouro.apk`
   (ou use `./deploy.sh yes`).
4. No cPanel, a tela admin terá o link **Baixar APK**.

> O app em produção com `devMode:false` bloqueia a troca de endereço —
> ideal para evitar que os participantes apontem para outro servidor.

---

## 9. Usando o document root clássico (`/public`)

Se preferir (ou se a hospedagem exigir), o document root pode apontar para
`public/`. Nesse caso o `.htaccess` da raiz é ignorado (nunca alcançado)
e o `.htaccess` interno de `public/` faz o roteamento do Slim normalmente.
Nenhum ajuste adicional é necessário.

---

## 10. Checklist final

- [ ] **Método A (git)** — repositório clonado via Git Version Control (token/deploy key)
  **OU** **Método B (pacote)** — `.tar.gz` enviado e extraído
- [ ] PHP 8.1+ com extensões (`pdo_mysql`, `gd`, `mbstring`, `openssl`)
- [ ] Acesso pela raiz OK (`.htaccess` da raiz) **ou** document root → `public`
- [ ] Banco criado no cPanel (ou o instalador cria) + schema/dados semeados
- [ ] `data/` com permissão de escrita (o instalador grava `install.php` ali)
- [ ] `config.php` com `APP_ENV=prod` e credenciais do banco
- [ ] `public/uploads` com permissão de escrita
- [ ] Admin alterou senhas padrão
- [ ] História + desafio final configurados
- [ ] Coordenadas dos tesouros confirmadas **no local** via app admin
- [ ] APK disponível em `/uploads/apk/cacaaotesouro.apk`
- [ ] Testes web + API + app ok
---

## 11. Sincronizar tesouros/QRs com o servidor de produção

O app das equipes prioriza o **servidor de produção** (se estiver no ar).
Para que os QR codes impressos funcionem, o servidor precisa ter os MESMOS
tesouros/QRs do ambiente onde foram gerados:

1. Gere o SQL de sincronização (na máquina de origem):
   ```bash
   php "C:/Users/ricar/AppData/Local/Temp/opencode/gen-sync.php"
   ```
   (ou rode um dump da tabela `treasures`). O arquivo sai em `sql/sync-treasures.sql`.
2. **Importe** `sql/sync-treasures.sql` no banco de produção (phpMyAdmin ou CLI).
3. **Envie os arquivos** `public/uploads/qr/*.svg` para a pasta
   `public/uploads/qr/` do servidor.
4. Se necessário, o admin confirma as coordenadas REAIS no local pelo app admin.
5. O telão usa `GET /api/telao` (público) e a página `GET /telao`.

> O app móvel exibe o status **ONLINE/OFFLINE** das equipes no telão com
> base na última localização (enviada a cada 5s). O mapa do telão NÃO mostra
> pontos de tesouro — apenas as posições das equipes.
