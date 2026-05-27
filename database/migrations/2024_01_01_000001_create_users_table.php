<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable(); // null for pharmacies (use name)
            $table->string('name', 255)->nullable();       // pharmacy display name
            $table->string('email', 255)->unique();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('profile_image')->nullable();
            $table->enum('role', ['citizen', 'pharmacy', 'admin'])->default('citizen');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();       // for admins

            // Pharmacy-specific fields
            $table->string('license_number', 100)->nullable()->unique();
            $table->date('license_expiry')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('area', 100)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected', 'suspended'])->nullable();
            $table->json('working_hours')->nullable();
            $table->boolean('delivery_available')->default(false);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);

            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('status');
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
