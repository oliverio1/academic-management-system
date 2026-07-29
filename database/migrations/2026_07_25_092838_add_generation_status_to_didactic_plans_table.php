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
        Schema::table('didactic_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('didactic_plans', 'status')) {
                $table->string('status', 30)->default('final')->after('title');
            }

            if (! Schema::hasColumn('didactic_plans', 'generated_by_system')) {
                $table->boolean('generated_by_system')->default(false)->after('status');
            }

            if (! Schema::hasColumn('didactic_plans', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('generated_by_system');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('didactic_plans', function (Blueprint $table) {
            foreach (['generated_at', 'generated_by_system', 'status'] as $column) {
                if (Schema::hasColumn('didactic_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
