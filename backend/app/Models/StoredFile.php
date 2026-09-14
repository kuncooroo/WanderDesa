<?php

namespace App\Models;

use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'checksum_sha256',
    'uploaded_by_user_id',
])]
class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;

    protected $table = 'files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
