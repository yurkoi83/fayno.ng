<?php

use App\Enums\FieldBelongsTo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('fields', function (Blueprint $table) {
			$table->increments('id');
			// Stored as VARCHAR; the allowed value set lives in App\Enums\FieldBelongsTo (app-boundary enforced).
			$table->string('belongs_to', 32)->default(FieldBelongsTo::Listing->value);
			// Stable, code-friendly identifier (snake_case). Used in form payloads
			// (`cf[machine_name]`), search filter URLs, and API responses so references
			// survive ID resets, exports, and re-seeds. The translatable `name` stays
			// the human label.
			$table->string('machine_name', 64);
			$table->text('name')->nullable();
			$table->string('type', 50)->default('text');
			$table->integer('max')->unsigned()->nullable()->default('255');
			$table->text('default_value')->nullable();
			$table->boolean('required')->nullable();
			$table->boolean('use_as_filter')->nullable()->default(true);
			$table->text('help')->nullable();
			$table->boolean('active')->nullable()->default(true);

			$table->unique(['belongs_to', 'machine_name']);
			$table->index(['belongs_to']);
			$table->index(['active']);
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('fields');
	}
};
