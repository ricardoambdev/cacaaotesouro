#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  Caça ao Tesouro — Gerador de pacote de deploy
#  Rode localmente (Git Bash no Windows ou Linux) para criar um pacote
#  pronto para upload em servidor compartilhado (cPanel).
#
#  Uso:
#    ./deploy.sh                      # pacote básico (sem APK, sem DB)
#    ./deploy.sh yes                  # inclui o APK do aplicativo
#    ./deploy.sh yes yes              # inclui APK + backup do banco MySQL
#
#  Variáveis para o backup do banco (opcional):
#    DB_HOST, DB_USER, DB_PASS, DB_NAME
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

INCLUDE_APK="${1:-no}"
INCLUDE_DB="${2:-no}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="$ROOT/deploy"
PACKAGE="$OUT_DIR/cacaaotesouro-${STAMP}.tar.gz"

echo "════════════════════════════════════════════════"
echo "  Caça ao Tesouro — Pacote de deploy"
echo "════════════════════════════════════════════════"

# ── 1. Dependências PHP ──────────────────────────────────────────
echo "==> [1/5] Dependências PHP (composer)..."
if [ ! -d vendor ]; then
  composer install --no-dev --optimize-autoloader --no-interaction
else
  echo "    vendor/ já existe (use 'composer install --no-dev' se mudou)."
fi

# ── 2. CSS (Tailwind) ────────────────────────────────────────────
echo "==> [2/5] Tailwind CSS..."
if [ ! -d node_modules ]; then
  npm install --silent
fi
npm run build:css

# ── 3. APK do aplicativo (opcional) ──────────────────────────────
if [ "$INCLUDE_APK" = "yes" ]; then
  echo "==> [3/5] Copiando APK do aplicativo..."
  mkdir -p public/uploads/apk
  if [ -f mobile/build/app/outputs/flutter-apk/app-release.apk ]; then
    cp -f mobile/build/app/outputs/flutter-apk/app-release.apk \
      public/uploads/apk/cacaaotesouro.apk
    echo "    APK incluído no pacote (public/uploads/apk/cacaaotesouro.apk)."
  else
    echo "    ! APK não encontrado. Rode 'cd mobile && flutter build apk --release'."
  fi
fi

# ── 4. Backup do banco (opcional) ────────────────────────────────
if [ "$INCLUDE_DB" = "yes" ]; then
  echo "==> [4/5] Exportando banco MySQL..."
  : "${DB_HOST:=localhost}" "${DB_USER:=root}" "${DB_NAME:=cacaaotesouro}"
  PASS_ARGS=()
  [ -n "${DB_PASS:-}" ] && PASS_ARGS+=("-p${DB_PASS}")
  mysqldump -h "$DB_HOST" -u "$DB_USER" "${PASS_ARGS[@]}" "$DB_NAME" \
    --default-character-set=utf8mb4 > sql/backup.sql
  echo "    Banco exportado em sql/backup.sql"
fi

# ── 5. Monta o pacote ────────────────────────────────────────────
echo "==> [5/5] Compactando pacote..."
rm -rf "$OUT_DIR"
mkdir -p "$OUT_DIR"

tar --force-local -czf "$PACKAGE" \
  app \
  public \
  sql \
  vendor \
  config.php \
  config.production.example.php \
  composer.json \
  composer.lock \
  README.md \
  DEPLOY.md

echo ""
echo "════════════════════════════════════════════════"
echo "  PACOTE PRONTO: $PACKAGE"
echo "════════════════════════════════════════════════"
echo ""
echo "Próximos passos no servidor (veja DEPLOY.md):"
echo "  1. Envie o pacote ao cPanel e extraia (File Manager)."
echo "  2. Aponte o document root para a pasta /public."
echo "  3. Crie o banco MySQL e importe sql/schema.mysql.sql"
echo "     (ou deixe a aplicação criar as tabelas no 1º acesso)."
echo "  4. Configure config.php (APP_ENV=prod + credenciais do banco)."
echo "  5. Garanta permissão de escrita em public/uploads/."