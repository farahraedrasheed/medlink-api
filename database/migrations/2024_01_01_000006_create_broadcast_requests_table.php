<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_requests', function (Blueprint $table) {
            $table->string('id')->primary(); // REQ-{timestamp}
            $table->foreignUuid('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->string('medicine_name', 255);
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->enum('urgency', ['standard', 'urgent', 'critical'])->default('standard');
            $table->enum('status', ['open', 'accepted', 'expired', 'closed'])->default('open');
            $table->json('responses')->nullable(); // pharmacy responses array
            $table->foreignUuid('accepted_pharmacy_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('citizen_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_requests');
    }
};
