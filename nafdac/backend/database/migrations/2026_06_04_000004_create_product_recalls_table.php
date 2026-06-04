<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('batches');
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('alert_reference', 100)->unique();
            $table->text('recall_reason');
            $table->string('severity_grade', 20);
            $table->text('laboratory_findings');
            $table->string('recall_status', 20)->default('active');
            $table->date('issue_date');
            $table->string('affected_regions', 255)->default('National');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('product_recalls'); }
};
