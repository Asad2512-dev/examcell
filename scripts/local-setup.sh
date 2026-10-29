#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v php >/dev/null || { echo 'Select your MAMP PHP binary in PATH first.'; exit 1; }
command -v composer >/dev/null || { echo 'Install Composer and select your MAMP PHP binary.'; exit 1; }
composer install --no-interaction
if [ ! -f .env ]; then
  cp .env.example .env
  echo 'Created .env. Edit it with your actual MAMP database settings, then run this script again.'
  exit 0
fi
php artisan config:clear
php artisan examcell:setup-key
php artisan examcell:preflight
php artisan migrate
printf 'Administrator email (leave blank if already provisioned): '
read -r admin_email
if [ -n "$admin_email" ]; then php artisan examcell:admin "$admin_email"; fi
echo 'Ready. Open separate terminals for:'
echo '  php artisan serve'
echo '  php artisan queue:work --tries=1 --timeout=120'
echo '  php artisan schedule:work'
