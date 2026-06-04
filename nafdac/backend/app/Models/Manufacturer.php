<?php
// app/Models/Manufacturer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = ['company_name', 'origin_country', 'reg_no', 'authorization_status'];

    public function products(): HasMany       { return $this->hasMany(Product::class, 'supplier_id'); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class, 'supplier_id'); }
    public function productRecalls(): HasMany { return $this->hasMany(ProductRecall::class, 'supplier_id'); }
}
