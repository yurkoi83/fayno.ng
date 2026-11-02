<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('customers', function (Blueprint $table) {
  $table->id(); $table->string('first_name'); $table->string('last_name'); $table->string('middle_name')->nullable();
  $table->string('phone',32); $table->string('email')->nullable(); $table->string('city')->nullable(); $table->string('address')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
  $table->index('phone'); $table->index('email'); $table->index(['last_name','first_name']);
 }); }
 public function down(): void { Schema::dropIfExists('customers'); }
};
