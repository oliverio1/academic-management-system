@extends('layouts.app')

@section('title', 'Seguimiento expediente docente')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Seguimiento de entregables docentes</h4>
                    <a href="{{ route('coordination.teacher-documents.index') }}" class="btn btn-secondary btn-sm">Volver</a>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Solicitud</th>
                                <th>Fecha límite</th>
                                <th>Docente</th>
                                <th>Materia</th>
                                <th>Grupo</th>
                                <th>Documento</th>
                                <th>Visible alumno</th>
                                <th>Estatus</th>
                                <th>Archivo</th>
                                <th>Fecha entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $item)
                            @php $latest = $item->latestSubmission; @endphp
                            <tr>
                                <td>{{ optional($item->request)->title ?? '-' }}</td>
                                <td>{{ optional(optional($item->request)->due_date)->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ optional(optional($item->assignment->teacher)->user)->name ?? '-' }}</td>
                                <td>{{ optional($item->assignment->subject)->name ?? '-' }}</td>
                                <td>{{ optional($item->assignment->group)->name ?? '-' }}</td>
                                <td>{{ $documentTypes[$item->document_type] ?? $item->document_type }}</td>
                                <td>
                                    <form method="POST" action="{{ route('coordination.teacher-documents.items.visibility', $item) }}" class="d-flex align-items-center">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_student_visible" value="{{ $item->is_student_visible ? 0 : 1 }}">
                                        @if($item->is_student_visible)
                                            <button class="btn btn-sm btn-success" title="Ocultar para alumnos">Sí</button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary" title="Mostrar para alumnos">No</button>
                                        @endif
                                    </form>
                                </td>
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
                                    @if($latest)
                                        <a href="{{ asset('storage/'.$latest->file_path) }}" target="_blank">Ver PDF</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $latest ? optional($latest->submitted_at)->format('d/m/Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">No hay registros para seguimiento.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
