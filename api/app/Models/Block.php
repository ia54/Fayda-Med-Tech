<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Block",
 *     title="Block",
 *     description="Block model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="page_id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Hero Section"),
 *     @OA\Property(property="slug", type="string", example="hero-section"),
 *     @OA\Property(property="order", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Block extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'title', 'slug', 'order'];

    protected $casts = [
        'order' => 'integer',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}
