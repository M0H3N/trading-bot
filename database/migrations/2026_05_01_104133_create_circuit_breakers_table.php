<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_breakers', function (Blueprint $table): void {
            $table->id();
            $table->string('exchange');
            $table->string('scope')->default('global');
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('opened_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['exchange', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_breakers');
    }
};
