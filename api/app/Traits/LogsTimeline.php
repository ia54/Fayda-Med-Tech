<?php

namespace App\Traits;

use App\Models\CaseTimeline;

trait LogsTimeline
{
    /**
     * Log an event to the case timeline.
     */
    public function logTimeline(int $caseId, string $eventType, string $title, string $description, ?array $metadata = null)
    {
        return CaseTimeline::create([
            'case_id' => $caseId,
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
