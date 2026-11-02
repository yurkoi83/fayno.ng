<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('payments', function (Blueprint $table) {
  $table->id(); $table->foreignId('booking_id')->constrained()->restrictOnDelete(); $table->decimal('amount',10,2);
  $table->char('currency',3)->default('EUR'); $table->string('status',32)->default('pending'); $table->string('payment_method',64)->nullable();
  $table->string('transaction_id')->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamps();
  $table->index(['booking_id','status']); $table->index('transaction_id');
 }); }
 public function down(): void { Schema::dropIfExists('payments'); }
};
