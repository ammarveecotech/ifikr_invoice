<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocSubmission extends Model
{
    protected $fillable = [
        'doc_id',
        'submission_UID',
        'status',
        'submitted_at',
        'response',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'response' => 'array',
    ];

    /**
     * Get the document that owns the submission.
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }
}
