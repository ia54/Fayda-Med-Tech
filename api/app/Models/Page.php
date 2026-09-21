<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Page",
 *     title="Page",
 *     description="Page model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="About Us"),
 *     @OA\Property(property="slug", type="string", example="about-us"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Page extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug'];

    public function seo()
    {
        return $this->hasOne(Seo::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }
}
