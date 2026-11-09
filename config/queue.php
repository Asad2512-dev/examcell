<?php

return ['default' => env('QUEUE_CONNECTION', 'database'), 'connections' => ['sync' => ['driver' => 'sync'], 'database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 900), 'after_commit' => true]], 'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'mysql'), 'table' => 'failed_jobs']];
