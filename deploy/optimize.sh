#!/bin/sh
# Run this once after every deployment to make every page load faster.
# It builds Laravel's config, route, event and view caches and an optimized class map.
# Do NOT run it on a development machine where you still edit .env or views: clear it with
#   php artisan optimize:clear
set -eu
examcell_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$examcell_root"
examcell_php=${EXAMCELL_PHP_BINARY:-php}
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi
"$examcell_php" artisan optimize
"$examcell_php" artisan storage:link >/dev/null 2>&1 || true
echo "Done. For the best speed, make sure PHP OPcache is on (opcache.enable=1, opcache.validate_timestamps=0 on production)."
