<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an auditable action.
     *
     * @param  class-string<Model>|Model  $entity
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        ?User $actor,
        string $action,
        Model|string $entity,
        int|string|null $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        if ($entity instanceof Model) {
            $entityId ??= $entity->getKey();
            $entityType = $entity::class;
        } else {
            $entityType = $entity;
        }

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    protected function ipAddress(): ?string
    {
        return request()?->ip();
    }

    protected function userAgent(): ?string
    {
        return request()?->userAgent();
    }
}
