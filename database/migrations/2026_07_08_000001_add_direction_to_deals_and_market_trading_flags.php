<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->string('direction', 16)->default('long')->after('mode')->index();
        });

        Schema::table('markets', function (Blueprint $table): void {
            $table->boolean('long_enabled')->default(true)->after('is_active');
            $table->boolean('short_enabled')->default(false)->after('long_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropColumn('direction');
        });

        Schema::table('markets', function (Blueprint $table): void {
            $table->dropColumn(['long_enabled', 'short_enabled']);
        });
    }
};
