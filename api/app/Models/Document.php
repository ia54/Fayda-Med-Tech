<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\BelongsToTenant;

class Document extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'case_id',
        'storage_disk',
        'uploaded_by',
        'title',
        'original_name',
        'filename',
        'mime_type',
        'size',
        'path',
        'url',
        'document_status',
        'signature_status',
        'ocr_status',
        'docusign_envelope_id',
        'sent_at',
        'signed_at',
        'metadata',
    ];

    protected $hidden = ['path', 'docusign_dispatch_snapshot'];

    // Never serialize a legacy public storage URL. Download requires API authentication.
    public function getUrlAttribute(): string
    {
        return url('/api/documents/' . $this->id . '/preview');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user || !in_array($user->role, User::getAvailableRoles(), true)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->role === 'admin') {
            return $query;
        }
        if (!$user->organization_id) {
            return $query->whereRaw('1 = 0');
        }
        $query->where('documents.organization_id', $user->organization_id);
        if (in_array($user->role, ['client', 'attorney'], true)) {
            $cases = CaseModel::where('organization_id', $user->organization_id)
                ->whereHas('parties', fn ($q) => $q->where('user_id', $user->id))->select('id');
            $query->where(function ($q) use ($user, $cases) {
                $q->where('uploaded_by', $user->id)
                    ->orWhereHas('signers', fn ($signers) => $signers->where('user_id', $user->id))
                    ->orWhereIn('case_id', $cases)
                    ->orWhereIn('metadata->case_id', $cases);
            });
        }
        return $query;
    }

    public function disk(): string
    {
        return $this->storage_disk === 'documents' ? 'documents' : 'public';
    }

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
        'docusign_dispatch_snapshot' => 'array',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function signers()
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    public function ocrResults()
    {
        return $this->hasMany(OcrResult::class);
    }

    public function apiLogs()
    {
        return $this->hasMany(ApiLog::class);
    }

    public function categories()
    {
        return $this->belongsToMany(DocumentCategory::class, 'document_category_document');
    }
}
