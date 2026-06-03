<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecall extends Model
{
    protected $fillable = [
        'batch_id', 'manufacturer_id', 'recall_number', 'reason',
        'classification', 'qc_summary', 'status', 'date_issued', 'scope',
        'internal_investigation_notes', 'inspector_id',
    ];

    protected $hidden = ['internal_investigation_notes', 'inspector_id'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
