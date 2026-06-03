<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = [
        'name', 'country', 'registration_number', 'license_status',
        'internal_vendor_rating', 'contract_terms',
    ];

    protected $hidden = ['internal_vendor_rating', 'contract_terms'];

    public function products(): HasMany  { return $this->hasMany(Product::class); }
    public function batches(): HasMany   { return $this->hasMany(Batch::class); }
    public function productRecalls(): HasMany { return $this->hasMany(ProductRecall::class); }
}
