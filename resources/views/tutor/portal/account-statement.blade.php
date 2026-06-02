@extends('layouts.app')

@section('title', 'Estado de cuenta')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Estado de cuenta</h4>
                    <small class="text-muted">Alumno: {{ $student->user->name }}</small>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total cargado</small>
                                <h5 class="mb-0">${{ number_format((float) $summary['charged'], 2) }}</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total abonado</small>
                                <h5 class="mb-0">${{ number_format((float) $summary['applied'], 2) }}</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Saldo</small>
                                <h5 class="mb-0">${{ number_format((float) $summary['balance'], 2) }}</h5>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><strong>Cargos</strong></div>
                        <div class="card-body table-responsive p-3">
                            <table data-datatable="true" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Descripción</th>
                                        <th>Vencimiento</th>
                                        <th>Monto</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($charges as $charge)
                                        <tr>
                                            <td>{{ optional($charge->concept)->name ?? '-' }}</td>
                                            <td>{{ $charge->description }}</td>
                                            <td>{{ optional($charge->due_date)->format('d/m/Y') }}</td>
                                            <td>${{ number_format((float) $charge->amount, 2) }}</td>
                                            <td>{{ $charge->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">Sin cargos registrados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><strong>Pagos</strong></div>
                        <div class="card-body table-responsive p-3">
                            <table data-datatable="true" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payments as $payment)
                                        <tr>
                                            <td>{{ optional($payment->payment_date)->format('d/m/Y H:i') }}</td>
                                            <td>${{ number_format((float) $payment->amount, 2) }}</td>
                                            <td>{{ $payment->method }}</td>
                                            <td>{{ $payment->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Sin pagos registrados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

