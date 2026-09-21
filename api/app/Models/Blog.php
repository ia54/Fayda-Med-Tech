<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'url_slug',
        'author',
        'category',
        'excerpt',
        'content',
        'tags',
        'status',
        'blog_images',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'blog_images' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['blog_images_urls'];

    /**
     * Get the full URLs for blog images.
     *
     * @return array
     */
    public function getBlogImagesUrlsAttribute(): array
    {
        if ($this->blog_images && is_array($this->blog_images)) {
            return array_map(function($image) {
                return url('storage/' . $image);
            }, $this->blog_images);
        }
        return [];
    }
}
