<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Field",
 *     title="Field",
 *     description="Field model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="block_id", type="integer", example=1),
 *     @OA\Property(property="label", type="string", example="Headline"),
 *     @OA\Property(property="name", type="string", example="headline"),
 *     @OA\Property(property="type", type="string", example="text", enum={"text", "richtext", "media", "array", "group", "checkbox", "select"}),
 *     @OA\Property(property="options", type="object", example={"label": "Option 1", "value": "1"}),
 *     @OA\Property(property="value", type="string", example="Welcome"),
 *     @OA\Property(property="required", type="boolean", example=true),
 *     @OA\Property(property="meta", type="object"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Field extends Model
{
    use HasFactory;

    protected $fillable = ['block_id', 'label', 'name', 'type', 'options', 'value', 'required', 'meta'];

    protected $casts = [
        'options' => 'array',
        'value' => 'array', // Use array cast to handle json automatically, even if it's a string, object, array, or boolean in logic. Laravel's array cast handles JSON.
        'meta' => 'array',
        'required' => 'boolean',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }
}
