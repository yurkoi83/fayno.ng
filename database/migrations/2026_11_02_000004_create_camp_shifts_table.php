<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('camp_shifts', function (Blueprint $table) {
  $table->id(); $table->foreignId('camp_id')->constrained()->restrictOnDelete(); $table->string('name'); $table->date('starts_at'); $table->date('ends_at');
  $table->unsignedInteger('capacity'); $table->decimal('regular_price',10,2); $table->decimal('early_price',10,2)->nullable();
  $table->date('early_booking_until')->nullable(); $table->char('currency',3)->default('EUR'); $table->string('status',32)->default('draft'); $table->timestamps();
  $table->index(['camp_id','starts_at','ends_at']); $table->index('status');
 }); }
 public function down(): void { Schema::dropIfExists('camp_shifts'); }
};
