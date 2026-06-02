@extends('layouts.app')

@section('title', 'Dashboard profesor')

@section('content')
@foreach (['success', 'info', 'warning', 'danger'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            <strong>{{ session($type) }}</strong>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endforeach

<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Panel docente</h3>
                </div>
                <div class="card-body">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="font-weight-bold mb-1">Buen día, {{ auth()->user()->name }} 👋</h4>
                            <div class="text-muted small">{{ now()->translatedFormat('l d \\d\\e F') }}</div>
                            @if(($pendingDocumentItems ?? 0) > 0)
                                <div class="alert alert-warning mt-3 mb-0">
                                    Tienes {{ $pendingDocumentItems }} documento(s) pendiente(s) por entregar.
                                    <a href="{{ route('teacher.document-requests.index') }}" class="alert-link">Ir a documentos solicitados</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="mb-0">Accesos rápidos</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-block">
                                        <i class="fas fa-home mr-1"></i>Inicio
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <a href="{{ route('teacher.classes.index') }}" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-chalkboard-teacher mr-1"></i>Mis clases
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <a href="{{ route('teacher.didactic-plans.index') }}" class="btn btn-outline-info btn-block">
                                        <i class="fas fa-file-alt mr-1"></i>Planeaciones
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <a href="{{ route('teacher.follow-ups.index') }}" class="btn btn-outline-warning btn-block">
                                        <i class="fas fa-bullhorn mr-1"></i>Avisos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-0">
                        <div class="card-header">
                            <h3 class="mb-0">Avisos institucionales</h3>
                        </div>
                        <div class="card-body">
                            @if($notifications->count())
                                @foreach($notifications as $note)
                                    <div class="card bg-light border-0 mb-2">
                                        <div class="card-body py-2">
                                            <div class="small font-weight-bold">{{ $note->title ?? 'Aviso' }}</div>
                                            <div class="small">{{ $note->message }}</div>
                                            <div class="text-muted small">{{ $note->date ?? '' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-muted small">No hay avisos por ahora.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
