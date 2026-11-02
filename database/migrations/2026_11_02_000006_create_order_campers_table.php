<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_campers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('camper_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestamps();
            $table->unique(['order_id', 'camper_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_campers');
    }
};
