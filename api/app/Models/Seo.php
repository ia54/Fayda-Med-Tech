<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Seo",
 *     title="SEO",
 *     description="SEO model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="page_id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Page Title"),
 *     @OA\Property(property="description", type="string", example="Description"),
 *     @OA\Property(property="image", type="string", example="http://site.com/image.jpg"),
 *     @OA\Property(property="keywords", type="string", example="keyword1, keyword2"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Seo extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'title', 'description', 'image', 'keywords'];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
