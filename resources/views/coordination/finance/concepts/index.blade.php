@extends('layouts.app')

@section('title', 'Conceptos de cobro')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Conceptos de cobro</h4>
                    <a href="{{ route('coordination.finance.concepts.create') }}" class="btn btn-primary btn-sm">Nuevo concepto</a>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead><tr><th>Código</th><th>Nombre</th><th>Monto base</th><th>Estatus</th><th>Acción</th></tr></thead>
                        <tbody>
                            @forelse($concepts as $concept)
                                <tr>
                                    <td>{{ $concept->code }}</td>
                                    <td>{{ $concept->name }}</td>
                                    <td>{{ $concept->default_amount !== null ? '$'.number_format((float)$concept->default_amount,2) : '-' }}</td>
                                    <td>{!! $concept->is_active ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>' !!}</td>
                                    <td><a href="{{ route('coordination.finance.concepts.edit', $concept) }}" class="btn btn-sm btn-outline-primary">Editar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">Sin conceptos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

