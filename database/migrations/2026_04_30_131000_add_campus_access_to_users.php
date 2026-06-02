<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'default_campus_id')) {
                $table->foreignId('default_campus_id')->nullable()->after('id')->constrained('campuses')->nullOnDelete();
            }
        });

        Schema::create('campus_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'campus_id'], 'campus_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_user');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'default_campus_id')) {
                $table->dropConstrainedForeignId('default_campus_id');
            }
        });
    }
};

