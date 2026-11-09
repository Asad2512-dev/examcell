#!/bin/sh
set -eu
umask 077
examcell_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$examcell_root"
exec >> "$examcell_root/storage/logs/scheduler-cron.log" 2>&1
exec /usr/bin/flock -n "$examcell_root/storage/framework/schedule.lock" /opt/alt/php83/usr/bin/php artisan schedule:run
