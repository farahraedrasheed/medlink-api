<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->string('id')->primary(); // CP-{timestamp}
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('against_pharmacy_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 255);
            $table->text('details');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_review', 'resolved', 'rejected'])->default('open');
            $table->foreignUuid('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolution_date')->nullable();
            $table->timestamps();

            $table->index('reporter_id');
            $table->index('against_pharmacy_id');
            $table->index('status');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('pharmacy_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('rating', 2, 1);
            $table->text('review_text')->nullable();
            $table->timestamps();

            $table->unique(['citizen_id', 'pharmacy_id']);
            $table->index('pharmacy_id');
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->enum('favorite_type', ['medicine', 'pharmacy']);
            $table->string('favorite_id', 255);
            $table->json('favorite_data')->nullable(); // snapshot
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['citizen_id', 'favorite_type', 'favorite_id']);
            $table->index('citizen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('complaints');
    }
};
