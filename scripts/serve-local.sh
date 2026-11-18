#!/usr/bin/env bash
# Concurrent local PHP requests keep pages responsive during slow AI/API calls.
set -euo pipefail
examcell_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
examcell_php=${EXAMCELL_PHP_BINARY:-php}
examcell_port=${EXAMCELL_PORT:-8000}
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4}
cd "$examcell_root/public"
exec "$examcell_php" -d memory_limit=256M -S "127.0.0.1:$examcell_port" \
    "$examcell_root/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
