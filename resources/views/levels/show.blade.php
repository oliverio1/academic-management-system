
@extends('layouts.app')

@section('title', 'Niveles')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>s  
    @endif

    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12 connectedSortable mt-3">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Detalle del nivel</h4>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-5 m-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">ID:</th>
                                        <td>{{ $level->id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Nombre:</th>
                                        <td>{{ $level->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Código:</th>
                                        <td>{{ $level->code }}</td>
                                    </tr>
                                    <tr>
                                        <th>Estado:</th>
                                        <td>
                                            <span class="badge badge-{{ $level->is_active ? 'success' : 'danger' }}">
                                                {{ $level->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-5 m-3">
                                <h5>Descripción:</h5>
                                <p>{{ $level->description ?: 'Sin descripción' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')

@endsection

@section('page_scripts')

@endsection