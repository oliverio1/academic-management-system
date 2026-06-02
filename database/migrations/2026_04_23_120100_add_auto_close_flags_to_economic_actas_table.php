<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('economic_actas', function (Blueprint $table) {
            $table->boolean('is_auto_closed')->default(false)->after('status');
            $table->timestamp('auto_closed_at')->nullable()->after('sent_at');
            $table->boolean('is_late_closure_acta')->default(false)->after('is_auto_closed');
        });
    }

    public function down(): void
    {
        Schema::table('economic_actas', function (Blueprint $table) {
            $table->dropColumn([
                'is_auto_closed',
                'auto_closed_at',
                'is_late_closure_acta',
            ]);
        });
    }
};

