<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->foreignId('manufacturer_id')->constrained('manufacturers');
            $table->string('recall_number', 100)->unique();
            $table->text('reason');
            $table->string('classification', 20);
            $table->text('qc_summary');
            $table->string('status', 20)->default('active');
            $table->date('date_issued');
            $table->string('scope', 100)->default('National');
            $table->text('internal_investigation_notes')->nullable();
            $table->integer('inspector_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recalls');
    }
};
