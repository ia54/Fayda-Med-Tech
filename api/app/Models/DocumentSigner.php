<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'name',
        'email',
        'signing_order',
        'status',
        'docusign_recipient_id',
        'signed_at',
    ];

    protected $casts = [
        'signing_order' => 'integer',
        'signed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }
}
