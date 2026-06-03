<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('name');
            $table->string('generic_name');
            $table->string('dosage_form', 100);
            $table->string('strength', 100);
            $table->string('registration_number', 100)->unique();
            $table->decimal('internal_cost', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('products'); }
};
