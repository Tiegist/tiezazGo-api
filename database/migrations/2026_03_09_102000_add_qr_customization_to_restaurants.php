<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'qr_foreground')) {
                $table->string('qr_foreground', 16)->nullable()->after('moto'); // e.g. #000000
            }
            if (! Schema::hasColumn('restaurants', 'qr_background')) {
                $table->string('qr_background', 16)->nullable()->after('qr_foreground'); // e.g. #FFFFFF
            }
            if (! Schema::hasColumn('restaurants', 'qr_logo_path')) {
                $table->string('qr_logo_path')->nullable()->after('qr_background');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $drops = [];
            foreach (['qr_foreground', 'qr_background', 'qr_logo_path'] as $col) {
                if (Schema::hasColumn('restaurants', $col)) {
                    $drops[] = $col;
                }
            }
            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};

