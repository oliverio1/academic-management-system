@extends('layouts.app')

@section('title', 'Detalle expediente docente')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">{{ $teacher->user->name ?? 'Docente' }}</h4>
                        @if($activeCycle)
                            <small class="text-muted">Expediente del ciclo {{ $activeCycle->name }}</small>
                        @endif
                    </div>
                    <a href="{{ route('coordination.teacher-documents.index') }}" class="btn btn-secondary btn-sm">Volver</a>
                </div>
                <div class="card-body">
                    @forelse($assignments as $assignmentRow)
                        @php $assignment = $assignmentRow['assignment']; @endphp
                        <div class="border rounded mb-3">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light border-bottom">
                                <div>
                                    <strong>{{ $assignment?->subject?->name ?? '-' }}</strong>
                                    <span class="text-muted">/ Grupo {{ $assignment?->group?->name ?? '-' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="badge {{ $assignmentRow['overdue'] > 0 ? 'badge-danger' : ($assignmentRow['pending'] > 0 ? 'badge-warning' : 'badge-success') }}">
                                        {{ $assignmentRow['delivered'] }} de {{ $assignmentRow['total'] }} entregados
                                    </span>
                                    <span class="badge badge-light">{{ $assignmentRow['percentage'] }}%</span>
                                </div>
                            </div>
                            <div class="table-responsive p-3">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Documento</th>
                                            <th>Fecha limite</th>
                                            <th>Estatus</th>
                                            <th>Archivo</th>
                                            <th>Entrega</th>
                                            <th>Visible alumno</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($assignmentRow['items'] as $item)
                                        @php
                                            $latest = $item->latestSubmission;
                                            $pdfUrl = $item->document_pdf_url ?? null;
                                        @endphp
                                        <tr>
                                            <td>{{ $documentTypes[$item->document_type] ?? $item->document_type }}</td>
                                            <td>{{ optional(optional($item->request)->due_date)->format('d/m/Y') ?? '-' }}</td>
                                            <td>
                                                @if($item->tracking_status === 'overdue')
                                                    <span class="badge badge-danger">Atrasado</span>
                                                @elseif($item->tracking_status === 'pending')
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @else
                                                    <span class="badge badge-success">Entregado</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($pdfUrl)
                                                    <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-sm btn-outline-danger">
                                                        Ver PDF
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                        Ver PDF
                                                    </button>
                                                @endif
                                            </td>
                                            <td>{{ $latest ? optional($latest->submitted_at)->format('d/m/Y H:i') : (($item->system_artifact_label ?? null) ? 'Registrado en sistema' : '-') }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('coordination.teacher-documents.items.visibility', $item) }}" class="d-flex align-items-center">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_student_visible" value="{{ $item->is_student_visible ? 0 : 1 }}">
                                                    @if($item->is_student_visible)
                                                        <button class="btn btn-sm btn-success" title="Ocultar para alumnos">Si</button>
                                                    @else
                                                        <button class="btn btn-sm btn-outline-secondary" title="Mostrar a alumnos">No</button>
                                                    @endif
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info mb-0">Este profesor no tiene expediente documental en el ciclo activo.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
