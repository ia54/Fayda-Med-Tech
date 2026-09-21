<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'post',
    ];

    protected $casts = [
        'post' => 'integer',
    ];

    /**
     * Get all blogs that belong to this category
     */
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'category', 'category');
    }
}
