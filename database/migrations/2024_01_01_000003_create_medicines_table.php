<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('generic_name', 255)->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('strength', 100)->nullable();
            $table->enum('form', ['tablet', 'capsule', 'liquid', 'injection', 'cream', 'drops', 'inhaler', 'patch', 'suppository', 'powder', 'other'])->default('tablet');
            $table->string('manufacturer', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('precautions')->nullable();
            $table->json('active_ingredients')->nullable();
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('is_controlled')->default(false);
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('generic_name');
            $table->index('category_id');
            $table->index('requires_prescription');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
