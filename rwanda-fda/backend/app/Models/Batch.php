<?php
// app/Models/Batch.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'manufacturer_id', 'batch_number',
        'manufacture_date', 'expiry_date', 'quantity_produced', 'internal_lot_code',
    ];

    protected $hidden = ['quantity_produced', 'internal_lot_code'];

    public function product(): BelongsTo      { return $this->belongsTo(Product::class); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class); }
    public function productRecalls(): HasMany  { return $this->hasMany(ProductRecall::class, 'batch_id'); }
}
