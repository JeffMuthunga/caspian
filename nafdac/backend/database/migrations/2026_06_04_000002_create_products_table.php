<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('product_name');
            $table->string('inn_name');
            $table->string('formulation', 100);
            $table->string('potency', 100);
            $table->string('market_auth_number', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('products'); }
};
