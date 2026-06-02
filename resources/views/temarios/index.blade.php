@extends('layouts.app')

@section('title', 'Temarios')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="content px-3">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-8">
                                <h4 class="mb-0">Temarios</h4>
                                <small class="text-muted">
                                    {{ $subject->name }}
                                </small>
                            </div>
                            <div class="col-sm-4 text-right">
                                <a class="btn btn-outline-secondary" href="{{ route('temarios.import.form', $subject) }}">
                                    Importar
                                </a>
                                <a class="btn btn-primary" href="{{ route('temarios.create', $subject) }}">
                                    Nuevo temario
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($temarios->isEmpty())
                            <div class="alert alert-light border mb-0">
                                Aun no hay temarios registrados para esta materia.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">Titulo</th>
                                            <th style="width: 60%;">Puntos</th>
                                            <th style="width: 10%;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($temarios as $temario)
                                            <tr>
                                                <td>
                                                    <strong>{{ $temario->title }}</strong>
                                                    @if($temario->description)
                                                        <div class="text-muted small mt-1">
                                                            {{ $temario->description }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @forelse($temario->points as $point)
                                                        @php
                                                            $level = max(1, (int) ($point->level ?? 1));
                                                            $margin = ($level - 1) * 18;
                                                            $levelLabel = $level === 1
                                                                ? 'Unidad'
                                                                : ($level === 2 ? 'Tema' : 'Subtema');
                                                        @endphp
                                                        <div class="mb-1" style="margin-left: {{ $margin }}px;">
                                                            <span class="badge badge-light border mr-1">{{ $levelLabel }}</span>
                                                            @if($point->label)
                                                                <strong>{{ $point->label }}</strong>
                                                            @endif
                                                            <span>{{ $point->content }}</span>
                                                        </div>
                                                    @empty
                                                        <span class="text-muted">Sin puntos</span>
                                                    @endforelse
                                                </td>
                                                <td class="text-right">
                                                    <a href="{{ route('temarios.edit', $temario) }}" class="btn btn-sm btn-secondary">
                                                        Editar
                                                    </a>

                                                    <form method="POST"
                                                          action="{{ route('temarios.destroy', $temario) }}"
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Se eliminara el temario. Continuar?')">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
