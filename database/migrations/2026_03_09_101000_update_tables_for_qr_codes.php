<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (! Schema::hasColumn('tables', 'qr_code_svg')) {
                $table->string('qr_code_svg')->nullable()->after('qr_code');
            }
        });

        Schema::table('tables', function (Blueprint $table) {
            // Enforce uniqueness of table numbers per restaurant
            $table->unique(['restaurant_id', 'table_number'], 'tables_restaurant_table_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique('tables_restaurant_table_number_unique');

            if (Schema::hasColumn('tables', 'qr_code_svg')) {
                $table->dropColumn('qr_code_svg');
            }
        });
    }
};

