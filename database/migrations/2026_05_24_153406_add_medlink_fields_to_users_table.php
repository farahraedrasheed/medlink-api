<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('id');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('name', 255)->nullable()->after('last_name');
            $table->string('phone', 20)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('profile_image')->nullable()->after('address');
            $table->enum('role', ['citizen', 'pharmacy', 'admin'])->default('citizen')->after('profile_image');
            $table->boolean('is_active')->default(true)->after('role');
            $table->json('permissions')->nullable()->after('is_active');
            $table->string('license_number', 100)->nullable()->unique()->after('permissions');
            $table->date('license_expiry')->nullable()->after('license_number');
            $table->decimal('latitude', 10, 8)->nullable()->after('license_expiry');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('area', 100)->nullable()->after('longitude');
            $table->enum('status', ['pending', 'verified', 'rejected', 'suspended'])->nullable()->after('area');
            $table->json('working_hours')->nullable()->after('status');
            $table->boolean('delivery_available')->default(false)->after('working_hours');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_available');
            $table->decimal('rating', 3, 2)->default(0)->after('delivery_fee');
            $table->integer('review_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'name', 'phone', 'address',
                'profile_image', 'role', 'is_active', 'permissions',
                'license_number', 'license_expiry', 'latitude', 'longitude',
                'area', 'status', 'working_hours', 'delivery_available',
                'delivery_fee', 'rating', 'review_count',
            ]);
        });
    }
};