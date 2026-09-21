<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait AuditableTrait
{
    /**
     * Boot the trait and register model event listeners.
     */
    protected static function bootAuditableTrait(): void
    {
        static::created(function ($model) {
            $model->logAuditEvent('created');
        });

        static::updated(function ($model) {
            $model->logAuditEvent('updated', $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logAuditEvent('deleted');
        });
    }

    /**
     * Log an audit event for this model.
     */
    protected function logAuditEvent(string $event, array $changes = []): void
    {
        try {
            $user = Auth::user();
            
            AuditLog::create([
                'user_id' => $user?->id,
                'organization_id' => $user?->organization_id ?? $this->organization_id ?? null,
                'event' => $event,
                'auditable_type' => get_class($this),
                'auditable_id' => $this->id,
                'description' => "{$event} " . class_basename($this) . " #{$this->id}",
                'metadata' => json_encode([
                    'model' => get_class($this),
                    'id' => $this->id,
                    'changes' => $changes,
                    'original' => $this->getOriginal(),
                ]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fail silently to not break the main operation
            \Illuminate\Support\Facades\Log::error('Audit logging failed: ' . $e->getMessage());
        }
    }
}
