<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('token_preview', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('token_preview', 8)->change();
        });
    }
};
