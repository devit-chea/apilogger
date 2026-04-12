<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_loggers', function (Blueprint $table) {
            $table->id();
            $table->string('log_id')->unique();
            $table->string('method', 10);
            $table->text('url');
            $table->string('ip', 45)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->float('duration')->nullable()->comment('milliseconds');
            $table->longText('payload')->nullable();
            $table->longText('payload_raw')->nullable()->after('payload');
            $table->longText('response')->nullable();
            $table->longText('response_headers')->nullable()->after('response');
            $table->text('headers')->nullable()->after('response');
            $table->string('controller')->nullable();
            $table->string('action')->nullable();
            $table->string('models')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('logged_at')->useCurrent();

            $table->index('method');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_loggers');
    }
};
