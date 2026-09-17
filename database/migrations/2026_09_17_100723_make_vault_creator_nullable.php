<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vaults', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')
                ->nullable()
                ->change();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (Schema::hasTable('vault_portals')) {
            Schema::table('vault_portals', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->foreignId('created_by')
                    ->nullable()
                    ->change();
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vault_portals')) {
            Schema::table('vault_portals', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->foreignId('created_by')
                    ->nullable(false)
                    ->change();
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('vaults', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')
                ->nullable(false)
                ->change();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
