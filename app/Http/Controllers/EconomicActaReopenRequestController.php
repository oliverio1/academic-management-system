<?php

namespace App\Http\Controllers;

use App\Models\EconomicActa;
use App\Models\EconomicActaReopenRequest;
use App\Models\User;
use App\Notifications\CoordinatorReviewNotification;
use App\Notifications\ReopenRequestReviewedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EconomicActaReopenRequestController extends Controller
{
    public function store(Request $request, EconomicActa $acta)
    {
        $teacherId = auth()->user()?->teacher?->id;
        abort_if(!$teacherId || (int) $acta->assignment->teacher_id !== (int) $teacherId, 403);

        abort_if(!$acta->is_auto_closed || $acta->status !== 'closed', 422, 'Solo puedes solicitar reapertura en cierres automaticos.');

        $existingPending = $acta->reopenRequests()->where('status', 'pending')->exists();
        abort_if($existingPending, 422, 'Ya existe una solicitud pendiente para esta materia.');

        $data = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $newRequest = EconomicActaReopenRequest::query()->create([
            'economic_acta_id' => $acta->id,
            'requested_by' => (int) auth()->id(),
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $coordinators = User::role('coordinator')->get();
        foreach ($coordinators as $coordinator) {
            $coordinator->notify(new CoordinatorReviewNotification([
                'type' => 'economic_acta_reopen_request',
                'title' => 'Solicitud de reapertura',
                'message' => 'Docente solicito reapertura para '
                    . ($acta->assignment->subject->name ?? '-')
                    . ' (' . ($acta->assignment->group->name ?? '-') . ').',
                'url' => route('coordination.economic-actas.index', [
                    'school_cycle_id' => $acta->partial->school_cycle_id,
                    'partial_id' => $acta->cycle_partial_id,
                ]),
                'meta' => ['request_id' => $newRequest->id],
            ]));
        }

        return back()->with('info', 'Solicitud de reapertura enviada a coordinacion.');
    }

    public function approve(Request $request, EconomicActaReopenRequest $reopenRequest)
    {
        abort_if($reopenRequest->status !== 'pending', 422, 'La solicitud ya fue revisada.');

        $data = $request->validate([
            'response_comment' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($reopenRequest, $data) {
            $acta = $reopenRequest->economicActa()->lockForUpdate()->firstOrFail();

            $fromStatus = $acta->status;

            $reopenRequest->update([
                'status' => 'approved',
                'reviewed_by' => (int) auth()->id(),
                'reviewed_at' => now(),
                'response_comment' => $data['response_comment'] ?? null,
            ]);

            $acta->update([
                'status' => 'draft',
                'drafted_by' => (int) auth()->id(),
                'drafted_at' => now(),
                'notes' => trim(($acta->notes ? $acta->notes . "\n" : '') . 'Reapertura aprobada por coordinacion.'),
            ]);

            $acta->events()->create([
                'from_status' => $fromStatus,
                'to_status' => 'draft',
                'changed_by' => (int) auth()->id(),
                'changed_at' => now(),
                'comment' => 'Reapertura aprobada por coordinacion.',
            ]);
        });

        $reopenRequest->refresh()->load('economicActa.assignment.teacher.user');
        $teacherUser = $reopenRequest->economicActa?->assignment?->teacher?->user;
        if ($teacherUser) {
            $teacherUser->notify(new ReopenRequestReviewedNotification($reopenRequest));
        }

        return back()->with('info', 'Solicitud aprobada. Captura reabierta para el docente.');
    }

    public function reject(Request $request, EconomicActaReopenRequest $reopenRequest)
    {
        abort_if($reopenRequest->status !== 'pending', 422, 'La solicitud ya fue revisada.');

        $data = $request->validate([
            'response_comment' => 'required|string|min:5|max:1000',
        ]);

        $reopenRequest->update([
            'status' => 'rejected',
            'reviewed_by' => (int) auth()->id(),
            'reviewed_at' => now(),
            'response_comment' => $data['response_comment'],
        ]);

        $reopenRequest->load('economicActa.assignment.teacher.user');
        $teacherUser = $reopenRequest->economicActa?->assignment?->teacher?->user;
        if ($teacherUser) {
            $teacherUser->notify(new ReopenRequestReviewedNotification($reopenRequest));
        }

        return back()->with('info', 'Solicitud rechazada.');
    }
}
