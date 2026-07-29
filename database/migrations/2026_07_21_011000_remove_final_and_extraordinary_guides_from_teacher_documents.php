<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $types = [
        'guia_final',
        'guia_extraordinario',
    ];

    public function up(): void
    {
        DB::table('teacher_document_request_items')
            ->whereIn('document_type', $this->types)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('teacher_document_submissions')
                    ->whereColumn('teacher_document_submissions.item_id', 'teacher_document_request_items.id');
            })
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
