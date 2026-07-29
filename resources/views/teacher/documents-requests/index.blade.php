@extends('layouts.app')

@section('title', 'Documentos solicitados')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Documentos solicitados por coordinacion</h4>
                </div>
                <div class="card-body">
                    @forelse($requests as $requestId => $items)
                        @php $request = $items->first()->request; @endphp
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>{{ $request->title }}</strong>
                                <span class="ml-2 badge badge-warning">Limite: {{ optional($request->due_date)->format('d/m/Y') }}</span>
                                @if($request->instructions)
                                    <div class="small text-muted mt-1">{{ $request->instructions }}</div>
                                @endif
                            </div>
                            <div class="card-body table-responsive p-3">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Grupo</th>
                                        <th>Documento</th>
                                        <th>Estatus</th>
                                        <th>Archivo</th>
                                        <th>Generar</th>
                                        <th>Clonar</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($items as $item)
                                        @php $latest = $item->latestSubmission; @endphp
                                        <tr>
                                            <td>{{ optional($item->assignment->subject)->name ?? '-' }}</td>
                                            <td>{{ optional($item->assignment->group)->name ?? '-' }}</td>
                                            <td>{{ $documentTypes[$item->document_type] ?? $item->document_type }}</td>
                                            <td>
                                                @if($item->tracking_status === 'delivered')
                                                    <span class="badge badge-success">Entregado</span>
                                                    <div class="small text-muted">
                                                        {{ $latest ? optional($latest->submitted_at)->format('d/m/Y H:i') : ($item->system_artifact_label ?? 'Registrado en sistema') }}
                                                    </div>
                                                @elseif($item->tracking_status === 'overdue')
                                                    <span class="badge badge-danger">Atrasado</span>
                                                @else
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($latest)
                                                    <a href="{{ route('teacher.document-requests.submissions.pdf', $latest) }}" target="_blank" class="btn btn-sm btn-outline-dark">
                                                        Ver PDF
                                                    </a>
                                                @elseif($item->editableContent?->submitted_at)
                                                    <a href="{{ route('teacher.document-requests.content.pdf', $item) }}" target="_blank" class="btn btn-sm btn-outline-dark">
                                                        Ver PDF
                                                    </a>
                                                @elseif($item->system_artifact_label ?? null)
                                                    <span class="text-muted">{{ $item->system_artifact_label }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @switch($item->document_type)
                                                    @case('reglamento')
                                                    @case('criterios_evaluacion')
                                                    @case('cuadernillo_actividades')
                                                    @case('guias_parciales')
                                                        <a href="{{ route('teacher.document-requests.content.edit', $item) }}" class="btn btn-sm btn-outline-secondary">
                                                            Abrir editor
                                                        </a>
                                                        @break

                                                    @case('examen_parcial_1')
                                                    @case('examen_parcial_2')
                                                    @case('examen_parcial_3')
                                                    @case('examen_parcial_4')
                                                    @case('examenes')
                                                        <a href="{{ route('teacher.question-banks.index', ($item->exam_partial_id ?? null) ? ['cycle_partial_id' => $item->exam_partial_id] : []) }}" class="btn btn-sm btn-outline-primary">
                                                            Configurar examen
                                                        </a>
                                                        @break

                                                    @case('planeacion')
                                                        <a href="{{ route('teacher.didactic-plans.plans', $item->assignment) }}" class="btn btn-sm btn-outline-primary">
                                                            Ir a planeacion
                                                        </a>
                                                        @break

                                                    @case('temario')
                                                        <span class="text-muted">Sin opcion</span>
                                                        @break

                                                    @default
                                                        <span class="text-muted">Sin opcion</span>
                                                @endswitch
                                            </td>
                                            <td>
                                                @if($item->document_type === 'reglamento' && $latest)
                                                    <form method="POST" action="{{ route('teacher.document-requests.clone-reglamento', $item) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-info">Clonar a mis materias</button>
                                                    </form>
                                                @elseif($item->document_type === 'criterios_evaluacion' && $latest)
                                                    <form method="POST" action="{{ route('teacher.document-requests.clone-criteria', $item) }}" class="d-flex align-items-center">
                                                        @csrf
                                                        <select name="scope" class="form-control form-control-sm mr-1" style="max-width: 170px;">
                                                            <option value="same_subject">Misma materia</option>
                                                            <option value="all_subjects">Todas mis materias</option>
                                                        </select>
                                                        <button class="btn btn-sm btn-outline-info">Clonar</button>
                                                    </form>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info mb-0">No tienes solicitudes documentales pendientes.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
