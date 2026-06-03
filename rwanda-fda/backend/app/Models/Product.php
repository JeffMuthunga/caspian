<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'manufacturer_id', 'name', 'generic_name',
        'dosage_form', 'strength', 'registration_number', 'internal_cost',
    ];

    protected $hidden = ['internal_cost'];

    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class); }
}
