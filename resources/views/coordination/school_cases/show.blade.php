@extends('layouts.app')

@section('title', 'Caso '.$case->case_number)

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">{{ $case->case_number }} - {{ $case->subject }}</h3>
                        <small class="text-muted">{{ $categories[$case->category] ?? $case->category }} | {{ $targetTypes[$case->target_type] ?? $case->target_type }}</small>
                    </div>
                    <a href="{{ route('coordination.school-cases.index') }}" class="btn btn-secondary btn-sm">Volver</a>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-success">{{ session('info') }}</div>
                    @endif

                    <div class="row">
                        <div class="col-lg-8">
                            <section class="case-section mb-4">
                                <h4 class="case-section-title">Detalle del caso</h4>
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Estatus</dt>
                                    <dd class="col-sm-9">{{ $statuses[$case->status] ?? $case->status }}</dd>
                                    <dt class="col-sm-3">Prioridad</dt>
                                    <dd class="col-sm-9">{{ $priorities[$case->priority] ?? $case->priority }}</dd>
                                    <dt class="col-sm-3">Origen</dt>
                                    <dd class="col-sm-9">{{ $sourceTypes[$case->source_type] ?? $case->source_type }} {{ $case->sourceUser ? '- '.$case->sourceUser->name : '' }}</dd>
                                    <dt class="col-sm-3">Relacionado con</dt>
                                    <dd class="col-sm-9">
                                        {{ $case->student?->user?->name
                                            ?? $case->group?->name
                                            ?? $case->teacher?->user?->name
                                            ?? $case->guardian?->name
                                            ?? $case->location
                                            ?? 'General' }}
                                    </dd>
                                    <dt class="col-sm-3">Responsable</dt>
                                    <dd class="col-sm-9">{{ $case->assignee?->name ?? 'Sin asignar' }}</dd>
                                    <dt class="col-sm-3">Fecha compromiso</dt>
                                    <dd class="col-sm-9">{{ $case->due_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                                    <dt class="col-sm-3">Descripcion</dt>
                                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $case->description }}</dd>
                                    <dt class="col-sm-3">Ultima respuesta</dt>
                                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $case->public_response ?: '-' }}</dd>
                                </dl>
                            </section>

                            <section class="case-section">
                                <h4 class="case-section-title">Historial</h4>
                                @forelse($case->entries as $entry)
                                    <div class="border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong>{{ $entry->user?->name ?? 'Sistema' }}</strong>
                                            <small class="text-muted">{{ $entry->created_at->format('d/m/Y H:i') }}</small>
                                        </div>
                                        <div class="text-muted small">
                                            {{ ucfirst(str_replace('_', ' ', $entry->entry_type)) }} | {{ $visibilityOptions[$entry->visibility] ?? $entry->visibility }}
                                        </div>
                                        <div class="mt-2" style="white-space: pre-line;">{{ $entry->body }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Sin movimientos.</p>
                                @endforelse
                            </section>
                        </div>

                        <div class="col-lg-4">
                            <section class="case-section mb-4">
                                <h4 class="case-section-title">Actualizar estatus</h4>
                                <form method="POST" action="{{ route('coordination.school-cases.status', $case) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-control mb-3" required>
                                        @foreach($statuses as $value => $label)
                                            <option value="{{ $value }}" {{ $case->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="status_note" rows="3" class="form-control mb-3" placeholder="Nota del cambio de estatus"></textarea>
                                    <button class="btn btn-primary btn-block">Actualizar</button>
                                </form>
                            </section>

                            <section class="case-section mb-4">
                                <h4 class="case-section-title">Acciones</h4>
                                @forelse($case->actions as $action)
                                    <div class="border-bottom pb-3 mb-3">
                                        <strong>{{ $action->title }}</strong>
                                        <div class="small text-muted">
                                            {{ $action->assignee?->name ?? 'Sin responsable' }}
                                            @if($action->due_at)
                                                | {{ $action->due_at->format('d/m/Y H:i') }}
                                            @endif
                                        </div>
                                        @if($action->status === 'completed')
                                            <span class="badge badge-success mt-2">Completada</span>
                                        @else
                                            <form method="POST" action="{{ route('coordination.school-cases.actions.complete', $action) }}" class="mt-2">
                                                @csrf
                                                @method('PATCH')
                                                <input name="notes" class="form-control form-control-sm mb-2" placeholder="Nota de cierre">
                                                <button class="btn btn-sm btn-success">Completar</button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted">Sin acciones pendientes.</p>
                                @endforelse

                                <form method="POST" action="{{ route('coordination.school-cases.actions.store', $case) }}">
                                    @csrf
                                    <label>Nueva accion</label>
                                    <input name="title" class="form-control mb-2" required>
                                    <select name="assigned_to" class="form-control mb-2">
                                        <option value="">Sin responsable</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="datetime-local" name="due_at" class="form-control mb-2">
                                    <button class="btn btn-primary btn-block">Agregar accion</button>
                                </form>
                            </section>

                            <section class="case-section">
                                <h4 class="case-section-title">Agregar nota</h4>
                                <form method="POST" action="{{ route('coordination.school-cases.entries.store', $case) }}">
                                    @csrf
                                    <select name="entry_type" class="form-control mb-2" required>
                                        <option value="note">Nota</option>
                                        <option value="public_response">Respuesta para quien reporto</option>
                                    </select>
                                    <select name="visibility" class="form-control mb-2" required>
                                        @foreach($visibilityOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="body" rows="4" class="form-control mb-3" required></textarea>
                                    <button class="btn btn-primary btn-block">Guardar nota</button>
                                </form>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .case-section {
        border: 1px solid #e5e7eb;
        border-radius: .35rem;
        padding: 1rem;
        background: #fff;
    }

    .case-section-title {
        color: #344054;
        font-size: .85rem;
        font-weight: 700;
        letter-spacing: .02em;
        margin-bottom: 1rem;
        text-transform: uppercase;
    }
</style>
@endsection
