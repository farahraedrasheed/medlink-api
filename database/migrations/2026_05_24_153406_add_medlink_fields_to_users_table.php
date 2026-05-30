<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name'))
                $table->string('first_name', 100)->nullable()->after('id');
            if (!Schema::hasColumn('users', 'last_name'))
                $table->string('last_name', 100)->nullable()->after('first_name');
            if (!Schema::hasColumn('users', 'name'))
                $table->string('name', 255)->nullable()->after('last_name');
            if (!Schema::hasColumn('users', 'phone'))
                $table->string('phone', 20)->nullable()->after('email');
            if (!Schema::hasColumn('users', 'address'))
                $table->text('address')->nullable()->after('phone');
            if (!Schema::hasColumn('users', 'profile_image'))
                $table->string('profile_image')->nullable()->after('address');
            if (!Schema::hasColumn('users', 'role'))
                $table->enum('role', ['citizen', 'pharmacy', 'admin'])->default('citizen')->after('profile_image');
            if (!Schema::hasColumn('users', 'is_active'))
                $table->boolean('is_active')->default(true)->after('role');
            if (!Schema::hasColumn('users', 'permissions'))
                $table->json('permissions')->nullable()->after('is_active');
            if (!Schema::hasColumn('users', 'license_number'))
                $table->string('license_number', 100)->nullable()->unique()->after('permissions');
            if (!Schema::hasColumn('users', 'license_expiry'))
                $table->date('license_expiry')->nullable()->after('license_number');
            if (!Schema::hasColumn('users', 'latitude'))
                $table->decimal('latitude', 10, 8)->nullable()->after('license_expiry');
            if (!Schema::hasColumn('users', 'longitude'))
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            if (!Schema::hasColumn('users', 'area'))
                $table->string('area', 100)->nullable()->after('longitude');
            if (!Schema::hasColumn('users', 'status'))
                $table->enum('status', ['pending', 'verified', 'rejected', 'suspended'])->nullable()->after('area');
            if (!Schema::hasColumn('users', 'working_hours'))
                $table->json('working_hours')->nullable()->after('status');
            if (!Schema::hasColumn('users', 'delivery_available'))
                $table->boolean('delivery_available')->default(false)->after('working_hours');
            if (!Schema::hasColumn('users', 'delivery_fee'))
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_available');
            if (!Schema::hasColumn('users', 'rating'))
                $table->decimal('rating', 3, 2)->default(0)->after('delivery_fee');
            if (!Schema::hasColumn('users', 'review_count'))
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