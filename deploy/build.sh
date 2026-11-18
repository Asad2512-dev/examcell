#!/bin/sh
# Hostinger shared hosting has a small process/thread budget.
set -eu
examcell_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$examcell_root"
export PATH=/opt/alt/alt-nodejs24/root/usr/bin:$PATH
export RAYON_NUM_THREADS=1
export TOKIO_WORKER_THREADS=1
export UV_THREADPOOL_SIZE=1
exec npm run build
