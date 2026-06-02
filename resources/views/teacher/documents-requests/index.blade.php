@extends('layouts.app')

@section('title', 'Documentos solicitados')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Documentos solicitados por coordinación</h4>
                </div>
                <div class="card-body">
                    @forelse($requests as $requestId => $items)
                        @php $request = $items->first()->request; @endphp
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>{{ $request->title }}</strong>
                                <span class="ml-2 badge badge-warning">Límite: {{ optional($request->due_date)->format('d/m/Y') }}</span>
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
                                        <th>Última entrega</th>
                                        <th>Archivo</th>
                                        <th>Cargar</th>
                                        <th>Clonar</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($items as $item)
                                        @php
                                            $latest = $item->latestSubmission;
                                        @endphp
                                        <tr>
                                            <td>{{ optional($item->assignment->subject)->name ?? '-' }}</td>
                                            <td>{{ optional($item->assignment->group)->name ?? '-' }}</td>
                                            <td>{{ $documentTypes[$item->document_type] ?? $item->document_type }}</td>
                                            <td>{{ $latest ? optional($latest->submitted_at)->format('d/m/Y H:i') : 'Pendiente' }}</td>
                                            <td>
                                                @if($latest)
                                                    <a href="{{ asset('storage/'.$latest->file_path) }}" target="_blank">Ver archivo</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('teacher.document-requests.upload', $item) }}" enctype="multipart/form-data" class="d-flex align-items-center">
                                                    @csrf
                                                    <input type="file" name="document" accept="application/pdf,.pdf" required class="form-control form-control-sm mr-1" style="max-width: 240px;">
                                                    <button class="btn btn-sm btn-primary">Subir</button>
                                                </form>
                                            </td>
                                            <td>
                                                @if($item->document_type === 'reglamento' && $latest)
                                                    <form method="POST" action="{{ route('teacher.document-requests.clone-reglamento', $item) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-info">Clonar a mis materias</button>
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
