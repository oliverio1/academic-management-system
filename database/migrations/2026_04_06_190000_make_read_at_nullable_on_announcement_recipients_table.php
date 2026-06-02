<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE announcement_recipients MODIFY read_at TIMESTAMP NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE announcement_recipients ALTER COLUMN read_at TYPE TIMESTAMP');
            DB::statement('ALTER TABLE announcement_recipients ALTER COLUMN read_at DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS _announcement_recipients_tmp');
            DB::statement('CREATE TABLE _announcement_recipients_tmp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                announcement_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                read_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )');
            DB::statement('INSERT INTO _announcement_recipients_tmp (id, announcement_id, user_id, read_at, created_at, updated_at)
                SELECT id, announcement_id, user_id, read_at, created_at, updated_at FROM announcement_recipients');
            DB::statement('DROP TABLE announcement_recipients');
            DB::statement('ALTER TABLE _announcement_recipients_tmp RENAME TO announcement_recipients');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE announcement_recipients MODIFY read_at DATE NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE announcement_recipients ALTER COLUMN read_at TYPE DATE');
            DB::statement('UPDATE announcement_recipients SET read_at = CURRENT_DATE WHERE read_at IS NULL');
            DB::statement('ALTER TABLE announcement_recipients ALTER COLUMN read_at SET NOT NULL');
        }
    }
};
