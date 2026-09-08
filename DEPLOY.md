# 🏴☠️ Plano de Implantação — Caça ao Tesouro

**Ambiente-alvo:** servidor compartilhado (Apache + PHP + MySQL via cPanel)
**Stack:** PHP 8.1+ / Slim 4 / MySQL 5.7+ ou 8 / Apache (.htaccess)

---

## 1. Resumo do fluxo

```
[Computador de desenvolvimento]          [Servidor compartilhado (cPanel)]
  1. composer install --no-dev      ──►  6. Upload + extração (File Manager)
  2. npm run build:css              ──►  7. Document root → /public
  3. (opcional) flutter build apk   ──►  8. Criar banco MySQL
  4. ./deploy.sh                    ──►  9. Importar sql/schema.mysql.sql
  5. Pacote deploy/*.tar.gz         ──► 10. Configurar config.php
                                      11. Permissões + e-mail
                                      12. Testar (web + app)
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

## 3. Gerando o pacote (passo a passo, na sua máquina)

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

1. Acesse o cPanel → **File Manager** (ou use FTP/FileZilla).
2. Entre na pasta do site (ex.: `public_html/cacaaotesouro/`).
3. Envie o `.tar.gz` e **extraia** ali (botão "Extract").
4. Confirme que a estrutura ficou: `public_html/cacaaotesouro/{app,public,vendor,...}`.

---

## 5. Configurando o servidor

### 5.1 Document root → `public`

No cPanel: **Domains → Manage → Document Root** — aponte para
`public_html/cacaaotesouro/public`.

- ✅ Segurança: código em `app/` e `config.php` ficam FORA do acesso web.
- ✅ O `.htaccess` já roteia tudo para `index.php` (Slim).
- ⚠️ Se NÃO puder mudar o document root: adicione um `.htaccess` na raiz
  que redirecione tudo para `public/` (veja seção 9).

### 5.2 Banco de dados

1. cPanel → **MySQL Databases**: crie o banco + usuário e vincule.
2. phpMyAdmin → importe **`sql/schema.mysql.sql`** (cria tabelas e seeds).
   - **Alternativa**: se o usuário tiver permissão `CREATE`, basta abrir o
     site uma vez — a aplicação cria as tabelas sozinha
     (`Database::ensureSchema`). Mesmo assim, importe o schema para o
     estado completo (settings + equipes + 10 tesouros de exemplo).
3. **Importante sobre os tesouros de exemplo:** os `qr_content` do seed
   são exemplos. Na prática, **crie/exclua tesouros pela tela admin** —
   os QR SVGs são gerados automaticamente com código aleatório. Se quiser
   começar do zero, apague os tesouros de exemplo em /tesouros.

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

## 9. Se NÃO puder alterar o document root

Coloque este `.htaccess` na RAIZ (junto de `app/`, `public/`):

```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ /public/$1 [L]
```

---

## 10. Checklist final

- [ ] PHP 8.1+ com extensões (`pdo_mysql`, `gd`, `mbstring`, `openssl`)
- [ ] Document root → `public`
- [ ] Banco criado + schema importado
- [ ] `config.php` com `APP_ENV=prod` e credenciais do banco
- [ ] `public/uploads` com permissão de escrita
- [ ] Admin alterou senhas padrão
- [ ] História + desafio final configurados
- [ ] Coordenadas dos tesouros confirmadas **no local** via app admin
- [ ] APK disponível em `/uploads/apk/cacaaotesouro.apk`
- [ ] Testes web + API + app ok