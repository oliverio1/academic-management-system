
@extends('layouts.app')

@section('title', 'Modalidades')

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
                                    <h4>Detalle de modalidad</h4>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-5 m-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">ID:</th>
                                        <td>{{ $modality->id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Nombre:</th>
                                        <td>{{ $modality->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Código:</th>
                                        <td>{{ $modality->code }}</td>
                                    </tr>
                                    <tr>
                                        <th>Estado:</th>
                                        <td>
                                            <span class="badge badge-{{ $modality->is_active ? 'success' : 'danger' }}">
                                                {{ $modality->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-5 m-3">
                                <h5>Descripción:</h5>
                                <p>{{ $modality->description ?: 'Sin descripción' }}</p>
                            </div>
                        </div>
                        <div class="mt-4 m-3">
                            <h4>Niveles de esta Modalidad</h4>
                            @if($modality->levels->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Código</th>
                                                <th>Orden</th>
                                                <th>Grupos</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($modality->levels as $level)
                                            <tr>
                                                <td>{{ $level->name }}</td>
                                                <td>{{ $level->code }}</td>
                                                <td>{{ $level->order }}</td>
                                                <td>
                                                    <span class="badge badge-info">{{ $level->groups_count ?? 0 }} grupos</span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $level->is_active ? 'success' : 'danger' }}">
                                                        {{ $level->is_active ? 'Activo' : 'Inactivo' }}
                                                    </span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Esta modalidad no tiene niveles registrados.
                                </div>
                            @endif
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