<?php

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
		Schema::create('fields_options', function (Blueprint $table) {
			$table->increments('id');
			$table->integer('field_id')->unsigned()->nullable();
			$table->mediumtext('value')->nullable();
			$table->integer('parent_id')->unsigned()->nullable();
			$table->integer('position')->unsigned()->nullable()->default(0);
			
			$table->index(['field_id']);
			$table->index(['position']);
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('fields_options');
	}
};
