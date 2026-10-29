<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public function record(string $action, $entity, array $changes = []): void
    {
        unset($changes['password'],$changes['token'],$changes['api_key']);
        AuditLog::create(['actor_id' => auth()->id(), 'action' => $action, 'entity_type' => is_object($entity) ? get_class($entity) : $entity, 'entity_id' => is_object($entity) ? $entity->id : null, 'changes' => $changes]);
    }
}
