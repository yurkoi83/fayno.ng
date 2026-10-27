<?php

use App\Enums\MenuItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `menu_items.type` is stored as a plain VARCHAR rather than a MySQL
 * `enum(...)`: the allowed value set lives in `App\Enums\MenuItemType`,
 * is enforced at the application boundary by the model's cast and by
 * `Rule::enum(MenuItemType::class)`, and stays VARCHAR here so adding /
 * removing a case is a one-line edit to the enum file — no
 * `ALTER TABLE … CHANGE COLUMN` migration required. The default value is
 * sourced from the enum so the schema's "fallback" can never drift from
 * the app's idea of "default". (`menu_items.url_type` is already a
 * nullable VARCHAR backed by `App\Enums\MenuItemUrlType`.)
 */
return new class extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up(): void
	{
		$menusTable = 'menus';
		Schema::create($menusTable, function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('location')->unique()->comment("'header', 'footer', 'sidebar', etc.");
			$table->string('description')->nullable();
			$table->integer('parent_id')->unsigned()->nullable();
			$table->integer('position')->unsigned()->nullable()->default(0);
			$table->boolean('active')->default(true);
			$table->timestamps();
			
			$table->unique(['name', 'location']);
			$table->index(['position']);
		});
		
		$menuItemsTable = 'menu_items';
		Schema::create($menuItemsTable, function (Blueprint $table) use ($menusTable, $menuItemsTable) {
			$table->id();
			$table->foreignId('menu_id')->constrained($menusTable)->cascadeOnDelete();
			$table->foreignId('parent_id')->nullable()->constrained($menuItemsTable)->cascadeOnDelete();
			$table->string('type', 32)->default(MenuItemType::Link->value);
			$table->string('icon')->nullable(); // Font Awesome, Bootstrap Icons, etc.
			$table->json('label')->nullable();  // Translatable field using JSON
			$table->string('url_type')->nullable()->comment('route, internal, external, or null');
			$table->string('route_name')->nullable();
			$table->json('route_parameters')->nullable();
			$table->string('url')->nullable();
			$table->string('target')->nullable()->comment('_self, _blank, _parent, _top');
			$table->boolean('nofollow')->default(false);
			$table->string('btn_class')->nullable();
			$table->boolean('btn_outline')->nullable()->default(false);
			$table->string('css_class')->nullable();
			$table->json('conditions')->nullable();      // Custom conditions for visibility
			$table->json('html_attributes')->nullable(); // Additional HTML attributes
			$table->json('roles')->nullable();           // Array of required roles
			$table->json('permissions')->nullable();     // Array of required permissions
			$table->string('description')->nullable()->comment('For details or notes');
			$table->integer('lft')->unsigned()->nullable()->default(0);
			$table->integer('rgt')->unsigned()->nullable()->default(0);
			$table->integer('depth')->unsigned()->nullable()->default(0);
			$table->boolean('active')->default(true);
			$table->timestamps();
			
			$table->index(['menu_id', 'parent_id', 'lft', 'rgt']);
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
		Schema::dropIfExists('menu_items');
		Schema::dropIfExists('menus');
	}
};
