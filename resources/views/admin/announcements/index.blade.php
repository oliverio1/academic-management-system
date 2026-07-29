@extends('layouts.app')

@section('title', 'Avisos')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Avisos institucionales</h4>
                    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">Nuevo aviso</a>
                </div>

                <div class="card-body">
                    <div class="table-responsive p-3">
                        <table data-datatable="true" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Dirigido a</th>
                                    <th>Activo</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($announcements as $a)
                                    <tr>
                                        <td>{{ $a->title }}</td>
                                        <td>{{ $a->scope === 'public' ? 'Público' : 'Interno' }}</td>
                                        <td>
                                            @switch($a->audience)
                                                @case('all') Todos @break
                                                @case('teachers') Profesores @break
                                                @case('students') Estudiantes @break
                                                @case('specific') Usuarios específicos @break
                                                @default {{ ucfirst($a->audience) }}
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($a->is_active)
                                                <span class="badge badge-success">Activo</span>
                                            @else
                                                <span class="badge badge-secondary">Inactivo</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.announcements.edit', $a) }}" class="btn btn-sm btn-warning">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                action="{{ route('admin.announcements.destroy', $a) }}"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="return confirm('¿Eliminar aviso?')">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No hay avisos registrados.</td>
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
