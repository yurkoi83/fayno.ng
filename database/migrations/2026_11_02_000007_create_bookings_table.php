<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_camper_id')->unique()->constrained('order_campers')->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
