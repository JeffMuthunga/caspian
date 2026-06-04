<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecall extends Model
{
    protected $fillable = [
        'lot_id', 'supplier_id', 'alert_reference', 'recall_reason',
        'severity_grade', 'laboratory_findings', 'recall_status',
        'issue_date', 'affected_regions',
    ];

    public function batch(): BelongsTo        { return $this->belongsTo(Batch::class, 'lot_id'); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
}
