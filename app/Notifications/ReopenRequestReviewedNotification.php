<?php

namespace App\Notifications;

use App\Models\EconomicActaReopenRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReopenRequestReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public EconomicActaReopenRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $approved = $this->request->status === 'approved';
        $assignment = $this->request->economicActa?->assignment;
        $partial = $this->request->economicActa?->partial;

        return [
            'title' => $approved ? 'Reapertura aprobada' : 'Reapertura rechazada',
            'message' => ($approved ? 'Coordinacion aprobo' : 'Coordinacion rechazo')
                . ' tu solicitud de reapertura para '
                . ($assignment?->subject?->name ?? '-')
                . ' (' . ($assignment?->group?->name ?? '-') . ')'
                . ' en ' . ($partial?->name ?? '-')
                . '.',
            'type' => 'reopen_request_reviewed',
            'economic_acta_id' => $this->request->economic_acta_id,
            'request_id' => $this->request->id,
            'status' => $this->request->status,
        ];
    }
}

