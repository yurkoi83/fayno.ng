<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('campers', function (Blueprint $table) {
  $table->id(); $table->foreignId('customer_id')->constrained()->restrictOnDelete(); $table->string('first_name'); $table->string('last_name');
  $table->string('middle_name')->nullable(); $table->date('birth_date'); $table->unsignedTinyInteger('gender_id')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
  $table->index(['last_name','first_name']);
 }); }
 public function down(): void { Schema::dropIfExists('campers'); }
};
