@php
    $coordUnread = auth()->user()->unreadNotifications()->latest()->take(6)->get();
@endphp

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="far fa-bell mr-2"></i>
                    Notificaciones por revisar
                </h5>
                <span class="badge badge-primary">{{ $coordUnread->count() }}</span>
            </div>
            <div class="card-body p-0">
                @if($coordUnread->isEmpty())
                    <div class="p-3 text-muted">
                        No hay novedades por revisar.
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($coordUnread as $notification)
                            @php
                                $title = $notification->data['title'] ?? 'Notificacion';
                                $message = $notification->data['message'] ?? ($notification->data['excerpt'] ?? 'Tienes una nueva notificacion.');
                                $url = $notification->data['url'] ?? route('dashboard');
                            @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="mr-3">
                                    <strong>{{ $title }}</strong>
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($message, 130) }}</div>
                                    <div class="small text-muted">{{ $notification->created_at->diffForHumans() }}</div>
                                </div>
                                <div class="text-right">
                                    <a href="{{ $url }}" class="btn btn-sm btn-outline-primary mb-1">Abrir</a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary js-mark-notification"
                                        data-read-url="{{ route('notifications.read', $notification) }}">
                                        Marcar leida
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

