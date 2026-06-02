@extends('layouts.app')

@section('title', 'Actas economicas')

@section('content')
<div class="content px-3">
    @if(session('info'))
        <div class="alert alert-primary mt-3">{{ session('info') }}</div>
    @endif

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Actas economicas (DGIRE)</h4>
                </div>
                <div class="card-body">
                    <form method="GET" class="form-row align-items-end mb-3">
                        <div class="form-group col-md-4">
                            <label>Ciclo</label>
                            <select name="school_cycle_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Seleccione</option>
                                @foreach($cycles as $cycle)
                                    <option value="{{ $cycle->id }}" {{ (int) optional($selectedCycle)->id === (int) $cycle->id ? 'selected' : '' }}>
                                        {{ $cycle->name }} ({{ $cycle->modality->name ?? 'Sin modalidad' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Parcial</label>
                            <select name="partial_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Seleccione</option>
                                @foreach($partials as $partial)
                                    <option value="{{ $partial->id }}" {{ (int) optional($selectedPartial)->id === (int) $partial->id ? 'selected' : '' }}>
                                        {{ $partial->name }} ({{ optional($partial->start_date)->format('d/m/Y') }} - {{ optional($partial->end_date)->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    @if($selectedPartial)
                        <div class="d-flex flex-wrap mb-3">
                            <form method="POST" action="{{ route('coordination.economic-actas.initialize', $selectedPartial) }}" class="mr-2 mb-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    Iniciar proceso del parcial
                                </button>
                            </form>
                            <form method="POST" action="{{ route('coordination.economic-actas.remind-pending', $selectedPartial) }}" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning">
                                    Recordar pendientes a docentes
                                </button>
                            </form>
                        </div>
                    @endif

                    <div class="mb-3">
                        <span class="badge badge-secondary">Pendientes: {{ $resume['pending'] }}</span>
                        <span class="badge badge-primary">Enviadas por docente: {{ $resume['submitted'] }}</span>
                        <span class="badge badge-info">Borrador: {{ $resume['draft'] }}</span>
                        <span class="badge badge-warning">Cerradas: {{ $resume['closed'] }}</span>
                        <span class="badge badge-success">Enviadas: {{ $resume['sent'] }}</span>
                    </div>

                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-sm table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Grupo</th>
                                    <th>Materia</th>
                                    <th>Profesor</th>
                                    <th>Estatus</th>
                                    <th>Enviada a coordinacion</th>
                                    <th>Borrador</th>
                                    <th>Cerrada</th>
                                    <th>Enviada</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    @php
                                        $acta = $actasByAssignment->get($assignment->id);
                                        $pendingReopen = $acta ? $pendingReopenRequestsByActa->get($acta->id) : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $assignment->group->name ?? '-' }}</td>
                                        <td>{{ $assignment->subject->name ?? '-' }}</td>
                                        <td>{{ $assignment->teacher->user->name ?? '-' }}</td>
                                        <td>
                                            @if(!$acta)
                                                <span class="badge badge-secondary">Sin generar</span>
                                            @elseif($acta->status === 'submitted')
                                                <span class="badge badge-primary">Enviada por docente</span>
                                            @elseif($acta->status === 'draft')
                                                <span class="badge badge-info">Borrador</span>
                                            @elseif($acta->status === 'closed')
                                                <span class="badge badge-warning">Cerrada</span>
                                                @if($acta->is_auto_closed)
                                                    <span class="badge badge-danger">Automatica</span>
                                                @endif
                                            @else
                                                <span class="badge badge-success">Enviada</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($acta?->submitted_at)
                                                {{ $acta->submittedByUser->name ?? '-' }}<br>
                                                <small class="text-muted">{{ $acta->submitted_at->format('d/m/Y H:i') }}</small>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($acta?->drafted_at)
                                                {{ $acta->draftedByUser->name ?? '-' }}<br>
                                                <small class="text-muted">{{ $acta->drafted_at->format('d/m/Y H:i') }}</small>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($acta?->closed_at)
                                                {{ $acta->closedByUser->name ?? '-' }}<br>
                                                <small class="text-muted">{{ $acta->closed_at->format('d/m/Y H:i') }}</small>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($acta?->sent_at)
                                                {{ $acta->sentByUser->name ?? '-' }}<br>
                                                <small class="text-muted">{{ $acta->sent_at->format('d/m/Y H:i') }}</small>
                                                @if($acta->sent_reference)
                                                    <br><small>Folio: {{ $acta->sent_reference }}</small>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($pendingReopen)
                                                <div class="mb-1 text-left">
                                                    <small class="text-muted">
                                                        Reapertura solicitada por {{ $pendingReopen->requestedByUser->name ?? 'docente' }}
                                                    </small>
                                                </div>
                                                <form method="POST" action="{{ route('coordination.economic-acta-reopen-requests.approve', $pendingReopen) }}" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-outline-success btn-sm">Aprobar reapertura</button>
                                                </form>
                                                <form method="POST" action="{{ route('coordination.economic-acta-reopen-requests.reject', $pendingReopen) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="response_comment" value="Solicitud rechazada por coordinacion.">
                                                    <button class="btn btn-outline-danger btn-sm">Rechazar</button>
                                                </form>
                                                <br>
                                            @endif

                                            @if($selectedPartial)
                                                @if($acta && in_array($acta->status, ['submitted', 'draft', 'closed']))
                                                    <form method="POST" action="{{ route('coordination.economic-actas.draft', [$selectedPartial, $assignment]) }}" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-outline-info btn-sm">Borrador</button>
                                                    </form>
                                                @endif

                                                @if($acta && in_array($acta->status, ['submitted', 'draft']))
                                                    <form method="POST" action="{{ route('coordination.economic-actas.close', [$selectedPartial, $assignment]) }}" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-outline-warning btn-sm">Cerrar</button>
                                                    </form>
                                                @endif

                                                @if($acta && $acta->status === 'closed')
                                                    <form method="POST" action="{{ route('coordination.economic-actas.send', [$selectedPartial, $assignment]) }}" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-outline-success btn-sm">Enviar</button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if($acta)
                                                <a href="{{ route('coordination.economic-actas.pdf', $acta) }}"
                                                   class="btn btn-outline-primary btn-sm"
                                                   target="_blank">
                                                    PDF
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No hay asignaciones para el ciclo/parcial seleccionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

