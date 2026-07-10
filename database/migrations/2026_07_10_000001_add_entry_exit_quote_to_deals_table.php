<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->decimal('entry_quote', 36, 12)->default(0)->after('entry_amount');
            $table->decimal('exit_quote', 36, 12)->default(0)->after('exit_amount');
        });

        DB::table('deals')->update([
            'entry_quote' => DB::raw('entry_average_price * entry_amount'),
            'exit_quote' => DB::raw('exit_average_price * exit_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropColumn(['entry_quote', 'exit_quote']);
        });
    }
};
