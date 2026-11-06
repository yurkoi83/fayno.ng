<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('camps')->whereNull('active')->update(['active' => true]);

        Schema::table('camps', function (Blueprint $table) {
            $table->boolean('active')->default(true)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('camps', function (Blueprint $table) {
            $table->boolean('active')->default(true)->nullable()->change();
        });
    }
};
