<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Cell;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        AuditEvent|string $event,
        ?Cell $cell = null,
        ?string $description = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'cell_id' => $cell?->id,
            'event' => $event instanceof AuditEvent ? $event->value : $event,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}