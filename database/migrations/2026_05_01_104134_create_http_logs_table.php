<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('http_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('exchange')->nullable()->index();
            $table->string('scope')->nullable()->index();
            $table->string('method', 16);
            $table->text('url');
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['exchange', 'scope', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('http_logs');
    }
};
