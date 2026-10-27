<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `pages.type` is stored as a plain VARCHAR rather than a MySQL `enum(...)`:
 * the allowed value set lives in `App\Enums\PageType`, is enforced at the
 * application boundary by the model's cast and by `Rule::enum(PageType::class)`,
 * and stays VARCHAR here, so adding/removing a case is a one-line edit to the
 * enum file — no `ALTER TABLE … CHANGE COLUMN` migration required.
 */
return new class extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('pages', function (Blueprint $table) {
			$table->increments('id');
			$table->integer('parent_id')->unsigned()->nullable();
			$table->string('type', 32);
			$table->text('name')->nullable();
			$table->string('slug', 191)->nullable();
			$table->text('title')->nullable();
			$table->string('image_path', 255)->nullable();
			$table->mediumtext('content')->nullable();
			$table->string('external_link', 255)->nullable();
			$table->string('name_color', 10)->nullable();
			$table->string('title_color', 10)->nullable();
			$table->string('target')->nullable()->default('_self');
			$table->text('seo_title')->nullable();
			$table->text('seo_description')->nullable();
			$table->text('seo_keywords')->nullable();
			$table->integer('position')->unsigned()->nullable()->default(0);
			$table->boolean('active')->nullable()->default(true);
			$table->timestamps();
			
			$table->index(['slug']);
			$table->index(['parent_id']);
			$table->index(['position']);
			$table->index(['active']);
			$table->index(['created_at']);
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('pages');
	}
};
