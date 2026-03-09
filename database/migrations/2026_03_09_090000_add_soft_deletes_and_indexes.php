<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurants') && ! Schema::hasColumn('restaurants', 'deleted_at')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->softDeletes();
                $table->index('email');
            });
        }

        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'deleted_at')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'deleted_at')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('saas_payments') && ! Schema::hasColumn('saas_payments', 'transaction_reference')) {
            // no-op; column is expected to exist from initial migration
        }

        if (Schema::hasTable('saas_payments')) {
            Schema::table('saas_payments', function (Blueprint $table) {
                $table->index('transaction_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                if (Schema::hasColumn('restaurants', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                $table->dropIndex(['email']);
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('menu_items')) {
            Schema::table('menu_items', function (Blueprint $table) {
                if (Schema::hasColumn('menu_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('saas_payments')) {
            Schema::table('saas_payments', function (Blueprint $table) {
                $table->dropIndex(['transaction_reference']);
            });
        }
    }
};

