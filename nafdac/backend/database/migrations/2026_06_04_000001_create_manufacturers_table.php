<?php
// database/migrations/2026_06_04_000001_create_manufacturers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('origin_country', 100);
            $table->string('reg_no', 100)->unique();
            $table->string('authorization_status', 50)->default('authorized');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('manufacturers'); }
};
