<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('orders', function (Blueprint $table) {
  $table->id(); $table->string('number',32)->unique(); $table->foreignId('customer_id')->constrained()->restrictOnDelete();
  $table->foreignId('camp_shift_id')->constrained()->restrictOnDelete(); $table->string('status',32)->default('new');
  $table->string('source',64)->nullable(); $table->string('departure_city')->nullable(); $table->text('comment')->nullable(); $table->text('admin_notes')->nullable();
  $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('responded_at')->nullable(); $table->timestamps();
  $table->index(['status','created_at']); $table->index('assigned_to'); $table->index('camp_shift_id');
 }); }
 public function down(): void { Schema::dropIfExists('orders'); }
};
