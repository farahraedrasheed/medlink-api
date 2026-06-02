<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary(); // ORD-{timestamp}
            $table->foreignUuid('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('pharmacy_id')->constrained('users')->cascadeOnDelete();
            $table->json('medicines'); // snapshot of items at order time
            $table->decimal('total_price', 10, 2);
            $table->enum('urgency', ['standard', 'urgent', 'critical'])->default('standard');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'preparing', 'ready', 'delivered', 'cancelled'])->default('pending');
            $table->json('status_timeline')->nullable();
            $table->text('pharmacy_response')->nullable();
            $table->timestamp('response_date')->nullable();
            $table->timestamp('order_date')->useCurrent();
            $table->timestamp('expected_delivery')->nullable();
            $table->timestamp('completed_date')->nullable();
            $table->timestamps();

            $table->index('citizen_id');
            $table->index('pharmacy_id');
            $table->index('status');
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
