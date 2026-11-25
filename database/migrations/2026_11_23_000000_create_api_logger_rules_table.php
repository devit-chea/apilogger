<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payload/response storage rules managed from the dashboard (apilogger.rules.database).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logger_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('payload or response');
            $table->string('pattern')->comment('route name or path pattern, * wildcard');
            $table->string('action', 10)->default('keep')->comment('keep, all or none');
            $table->json('keys')->nullable()->comment('dot-notation keys kept when action = keep');
            $table->unsignedInteger('priority')->default(100)->comment('lower runs first');
            $table->boolean('enabled')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['type', 'enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logger_rules');
    }
};
