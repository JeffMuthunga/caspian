<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('lot_number', 100)->unique();
            $table->date('production_date');
            $table->date('expiry_date');
            $table->integer('units_manufactured')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('batches'); }
};
