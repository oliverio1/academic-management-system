@extends('layouts.app')

@section('title', 'Cargos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Cargos</h4>
                    <div>
                        <a href="{{ route('coordination.finance.charges.massive.create') }}" class="btn btn-outline-primary btn-sm">Carga masiva</a>
                        <a href="{{ route('coordination.finance.charges.create') }}" class="btn btn-primary btn-sm">Nuevo cargo</a>
                    </div>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead><tr><th>Alumno</th><th>Concepto</th><th>Descripción</th><th>Vence</th><th>Monto</th><th>Estatus</th></tr></thead>
                        <tbody>
                            @forelse($charges as $charge)
                                <tr>
                                    <td>{{ optional($charge->student->user)->name }}</td>
                                    <td>{{ optional($charge->concept)->name }}</td>
                                    <td>{{ $charge->description }}</td>
                                    <td>{{ optional($charge->due_date)->format('d/m/Y') }}</td>
                                    <td>${{ number_format((float)$charge->amount,2) }}</td>
                                    <td>{{ $charge->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Sin cargos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
