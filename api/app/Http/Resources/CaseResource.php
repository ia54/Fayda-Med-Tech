<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    /**
     * Disable the 'data' wrapper for this resource.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'accident_date' => $this->accident_date ? $this->accident_date->format('Y-m-d') : null,
            'sol_date' => $this->sol_date ? $this->sol_date->format('Y-m-d') : null,
            'jurisdiction' => $this->jurisdiction,
            'total_case_value' => $this->total_case_value,
            'created_by' => [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name,
            ],
            'organization_id' => $this->organization_id,
            'metadata' => $this->metadata,
            'parties' => $this->parties,
            'timeline' => $this->whenLoaded('timeline'),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
