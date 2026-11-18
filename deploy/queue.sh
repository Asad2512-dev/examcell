#!/bin/sh
set -eu
umask 077
examcell_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$examcell_root"
exec >> "$examcell_root/storage/logs/queue-cron.log" 2>&1
exec /usr/bin/flock -n "$examcell_root/storage/framework/queue.lock" /opt/alt/php83/usr/bin/php artisan queue:work database --stop-when-empty --max-time=240 --timeout=300 --tries=1 --memory=256
