<?php

use App\Enums\LanguageDirection;
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
		Schema::create('languages', function (Blueprint $table) {
			$table->increments('id');
			$table->string('code', 20);
			$table->string('system_locale', 25)->nullable();
			$table->string('name', 100);
			$table->string('native_name', 100)->nullable();
			$table->string('flag_icon', 100)->nullable();
			// Stored as VARCHAR; the allowed value set lives in App\Enums\LanguageDirection (app-boundary enforced).
			$table->string('direction', 32)->nullable()->default(LanguageDirection::Ltr->value);
			$table->boolean('russian_pluralization')->nullable()->default(false);
			$table->string('date_format', 100)->nullable();
			$table->string('datetime_format', 100)->nullable();
			$table->boolean('active')->nullable()->default(true);
			$table->boolean('default')->nullable()->default(false);
			$table->integer('parent_id')->unsigned()->nullable();
			$table->integer('position')->unsigned()->nullable()->default(0);
			$table->timestamp('deleted_at')->nullable();
			$table->timestamps();
			
			$table->unique(['code']);
			$table->index(['position']);
			$table->index(['active']);
			$table->index(['default']);
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('languages');
	}
};
