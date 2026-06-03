<?php
// database/migrations/2026_06_04_000003_create_batches_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('batch_number', 100);
            $table->date('manufacture_date');
            $table->date('expiry_date');
            $table->integer('quantity_produced')->nullable();
            $table->string('internal_lot_code', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('batches'); }
};
