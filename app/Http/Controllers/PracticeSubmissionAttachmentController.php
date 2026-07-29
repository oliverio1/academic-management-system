<?php

namespace App\Http\Controllers;

use App\Models\PracticeSubmissionAttachment;
use Illuminate\Support\Facades\Storage;

class PracticeSubmissionAttachmentController extends Controller
{
    public function download(PracticeSubmissionAttachment $attachment)
    {
        $attachment->loadMissing('submission.practice.teachingAssignment', 'submission.team.students');

        abort_unless($this->canDownload($attachment), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function canDownload(PracticeSubmissionAttachment $attachment): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['coordinator', 'admin'])) {
            return true;
        }

        $submission = $attachment->submission;
        $assignment = $submission->practice->teachingAssignment;

        if ($user->teacher && $assignment->teacher_id === $user->teacher->id) {
            return true;
        }

        if ($user->student) {
            return $submission->team->students->contains('id', $user->student->id);
        }

        return false;
    }
}
