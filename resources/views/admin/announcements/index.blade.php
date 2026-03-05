@extends('layouts.app')

@section('title', 'Avisos')

@section('content')

@if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Avisos institucionales</h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">Nuevo aviso</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Dirigido a</th>
                                    <th>Activo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($announcements as $a)
                                    <tr>
                                        <td>{{ $a->title }}</td>
                                        <td>{{ ucfirst($a->target) }}</td>
                                        <td>
                                            @if($a->is_active)
                                                <span class="badge badge-success">Activo</span>
                                            @else
                                                <span class="badge badge-secondary">Inactivo</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.announcements.edit', $a) }}"
                                            class="btn btn-sm btn-warning">Editar</a>

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
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
