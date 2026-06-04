<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'supplier_id', 'lot_number',
        'production_date', 'expiry_date', 'units_manufactured',
    ];

    protected $hidden = ['units_manufactured'];

    public function product(): BelongsTo      { return $this->belongsTo(Product::class); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
    public function productRecalls(): HasMany  { return $this->hasMany(ProductRecall::class, 'lot_id'); }
}
