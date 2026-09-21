<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Media",
 *     title="Media",
 *     description="Media model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="original_name", type="string", example="image.jpg"),
 *     @OA\Property(property="width", type="integer", example=800),
 *     @OA\Property(property="height", type="integer", example=600),
 *     @OA\Property(property="filename", type="string", example="hash.jpg"),
 *     @OA\Property(property="url", type="string", example="http://site.com/storage/hash.jpg"),
 *     @OA\Property(property="path", type="string", example="uploads/hash.jpg"),
 *     @OA\Property(property="placeholder", type="string", example="base64..."),
 *     @OA\Property(property="ext", type="string", example="jpg"),
 *     @OA\Property(property="size", type="string", example="1024"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Media extends Model
{
    use HasFactory;

    protected $table = 'medias';

    protected $fillable = [
        'original_name',
        'width',
        'height',
        'filename',
        'url',
        'path',
        'placeholder',
        'ext',
        'size',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];
}
