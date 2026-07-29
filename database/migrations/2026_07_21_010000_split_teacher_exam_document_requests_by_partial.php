<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyItems = DB::table('teacher_document_request_items')
            ->where('document_type', 'examenes')
            ->get();

        foreach ($legacyItems as $item) {
            foreach ([1, 2, 3, 4] as $partialNumber) {
                $documentType = 'examen_parcial_' . $partialNumber;
                $exists = DB::table('teacher_document_request_items')
                    ->where('request_id', $item->request_id)
                    ->where('teaching_assignment_id', $item->teaching_assignment_id)
                    ->where('document_type', $documentType)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('teacher_document_request_items')->insert([
                    'tenant_id' => $item->tenant_id,
                    'request_id' => $item->request_id,
                    'teaching_assignment_id' => $item->teaching_assignment_id,
                    'document_type' => $documentType,
                    'notes' => $item->notes,
                    'is_required' => $item->is_required,
                    'is_student_visible' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('teacher_document_request_items')
            ->where('document_type', 'examenes')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('teacher_document_submissions')
                    ->whereColumn('teacher_document_submissions.item_id', 'teacher_document_request_items.id');
            })
            ->delete();
    }

    public function down(): void
    {
        DB::table('teacher_document_request_items')
            ->whereIn('document_type', [
                'examen_parcial_1',
                'examen_parcial_2',
                'examen_parcial_3',
                'examen_parcial_4',
            ])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('teacher_document_submissions')
                    ->whereColumn('teacher_document_submissions.item_id', 'teacher_document_request_items.id');
            })
            ->delete();
    }
};
