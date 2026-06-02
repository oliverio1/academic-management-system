@extends('layouts.app')

@section('title', 'Chat interno')

@section('content')
<div class="content px-3 mt-3">
    <div class="card mb-3">
        <div class="card-header">
            <h4 class="mb-0">Chat interno</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header py-2"><strong>Conversaciones</strong></div>
                        <div class="card-body p-0" style="max-height: 62vh; overflow-y: auto;">
                            @forelse($conversations as $conversation)
                                @php
                                    $isActive = optional($selectedConversation)->id === $conversation->id;
                                    $label = $conversation->type === 'group'
                                        ? ($conversation->title ?: 'Grupo '.optional($conversation->group)->name)
                                        : optional($conversation->participants->where('id', '!=', auth()->id())->first())->name;
                                @endphp
                                <a href="{{ route('chat.index', ['conversation' => $conversation->id]) }}"
                                   class="d-block px-3 py-2 border-bottom {{ $isActive ? 'bg-light' : '' }}">
                                    <div class="font-weight-bold">{{ $label ?: 'Conversación' }}</div>
                                    <small class="text-muted">{{ $conversation->type === 'group' ? 'Grupo' : 'Directo' }}</small>
                                </a>
                            @empty
                                <div class="p-3 text-muted">Sin conversaciones.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-md-8 mb-3">
                    <div class="card mb-3">
                        <div class="card-header py-2"><strong>Nuevo chat directo</strong></div>
                        <div class="card-body py-2">
                            <form action="{{ route('chat.direct.store') }}" method="POST" class="form-inline">
                                @csrf
                                <select name="user_id" class="form-control form-control-sm mr-2" required>
                                    <option value="">Selecciona usuario</option>
                                    @foreach($availableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary btn-sm">Abrir chat</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header py-2"><strong>Nuevo chat de grupo</strong></div>
                        <div class="card-body py-2">
                            <form action="{{ route('chat.group.store') }}" method="POST" class="form-inline">
                                @csrf
                                <select name="group_id" class="form-control form-control-sm mr-2" required>
                                    <option value="">Selecciona grupo</option>
                                    @foreach($availableGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary btn-sm">Abrir chat grupal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header py-2">
                            <strong>
                                @if($selectedConversation)
                                    @if($selectedConversation->type === 'group')
                                        {{ $selectedConversation->title ?: 'Grupo '.optional($selectedConversation->group)->name }}
                                    @else
                                        {{ optional($selectedConversation->participants->where('id', '!=', auth()->id())->first())->name ?: 'Chat directo' }}
                                    @endif
                                @else
                                    Sin conversación seleccionada
                                @endif
                            </strong>
                        </div>
                        <div class="card-body" id="messages-box" style="height: 45vh; overflow-y: auto;">
                            @if($selectedConversation)
                                @forelse($messages as $message)
                                    <div class="mb-2" data-message-id="{{ $message->id }}">
                                        <div class="small text-muted">{{ $message->user->name ?? 'Usuario' }} · {{ optional($message->created_at)->format('d/m/Y H:i') }}</div>
                                        <div class="border rounded px-2 py-1">{{ $message->body }}</div>
                                    </div>
                                @empty
                                    <div class="text-muted">No hay mensajes aún.</div>
                                @endforelse
                            @else
                                <div class="text-muted">Selecciona o crea una conversación.</div>
                            @endif
                        </div>
                        @if($selectedConversation)
                            <div class="card-footer">
                                <form method="POST" action="{{ route('chat.messages.store', $selectedConversation) }}">
                                    @csrf
                                    <div class="input-group">
                                        <input type="text" name="body" class="form-control" maxlength="5000" placeholder="Escribe un mensaje..." required>
                                        <div class="input-group-append">
                                            <button class="btn btn-primary">Enviar</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
@if($selectedConversation)
<script>
    (function () {
        const conversationId = {{ $selectedConversation->id }};
        const authUserId = {{ auth()->id() }};
        const box = document.getElementById('messages-box');

        const getLastId = () => {
            const all = box.querySelectorAll('[data-message-id]');
            const last = all.length ? all[all.length - 1] : null;
            return last ? Number(last.getAttribute('data-message-id')) : 0;
        };

        const appendMessage = (message) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'mb-2';
            wrapper.setAttribute('data-message-id', message.id);
            wrapper.innerHTML = `
                <div class="small text-muted">${message.user_name} · ${message.time}</div>
                <div class="border rounded px-2 py-1">${message.body}</div>
            `;
            box.appendChild(wrapper);
        };

        const poll = async () => {
            try {
                const after = getLastId();
                const response = await fetch(`{{ url('/chat/conversations') }}/${conversationId}/messages?after_id=${after}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const payload = await response.json();
                if (!payload.messages || !payload.messages.length) return;
                payload.messages.forEach(appendMessage);
                box.scrollTop = box.scrollHeight;
            } catch (e) {
            }
        };

        box.scrollTop = box.scrollHeight;
        setInterval(poll, 4000);
    })();
</script>
@endif
@endsection
