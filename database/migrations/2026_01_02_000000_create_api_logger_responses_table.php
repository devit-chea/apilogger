<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logger_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_logger_id');
            $table->longText('response')->nullable();
            $table->longText('response_headers')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('payload_raw')->nullable();
            $table->text('headers')->nullable();
            
            $table->foreign('api_logger_id')
                  ->references('id')
                  ->on('api_loggers')
                  ->onDelete('cascade');
            
            $table->index('api_logger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logger_responses');
    }
};