<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            $table->decimal('last_price', 36, 12)->default(0)->after('step_size');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            $table->dropColumn('last_price');
        });
    }
};
