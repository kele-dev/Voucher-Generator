#!/usr/bin/env bash
# Example zero-surprise deploy for a single VPS. Layout:
#   /var/www/voucher/releases/<timestamp>   code
#   /var/www/voucher/shared/{.env,database/,storage/,backups/}   persistent
#   /var/www/voucher/current -> releases/<timestamp>
# Run as the deploy user with sudo rights for supervisorctl/php-fpm reload.
set -euo pipefail

APP=/var/www/voucher
REPO=${REPO:?set REPO to the git URL}
REF=${REF:-main}
RELEASE=$APP/releases/$(date +%Y%m%d%H%M%S)
PREVIOUS=$(readlink -f "$APP/current" 2>/dev/null || true)

git clone --depth 1 --branch "$REF" "$REPO" "$RELEASE"
cd "$RELEASE"

ln -sfn "$APP/shared/.env" .env
rm -rf storage && ln -sfn "$APP/shared/storage" storage

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules

# Safety net before schema changes; remember its name for rollback.
php artisan voucher:backup --no-prune
BACKUP=$(ls -1t "$APP/shared/backups" | head -n 1)

php artisan down --retry=60 || true

# From here on the site is in maintenance. Any failure must say so and how
# to recover, instead of exiting silently with the site down.
on_error() {
    cat >&2 <<EOF

DEPLOY GAGAL setelah maintenance mode — situs MASIH OFFLINE (maintenance).
Rilis baru : $RELEASE
Rilis lama : ${PREVIOUS:-tidak ada}
Backup pra-deploy: $BACKUP

Pulihkan salah satu:
  a) Perbaiki penyebab, jalankan ulang skrip ini.
  b) Kembali ke rilis lama:
       ln -sfn "$PREVIOUS" "$APP/current" && sudo systemctl reload php8.4-fpm
       cd "$PREVIOUS" && php artisan voucher:restore $BACKUP --force   # bila migrasi sudah jalan
       php artisan queue:restart && php artisan up
EOF
}
trap on_error ERR

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

ln -sfn "$RELEASE" "$APP/current"
sudo systemctl reload php8.4-fpm
# Workers finish their current job (stopwaitsecs) and pick up new code.
php artisan queue:restart

# Only go live if the go-live checks pass; otherwise stay in maintenance
# (the ERR trap prints recovery steps).
php artisan voucher:preflight
trap - ERR
php artisan up

# Keep the 5 newest releases.
ls -1dt "$APP"/releases/* | tail -n +6 | xargs -r rm -rf
echo "Deployed $RELEASE"
