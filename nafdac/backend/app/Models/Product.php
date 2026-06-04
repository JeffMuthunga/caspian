<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['supplier_id', 'product_name', 'inn_name', 'formulation', 'potency', 'market_auth_number'];

    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class); }
}
