@extends('layouts.app')

@section('title', 'Estado de cuenta')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">Estado de cuenta: {{ optional($student->user)->name }}</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <h6>Registrar pago</h6>
                            <form method="POST" action="{{ route('coordination.finance.statements.payments.store', $student) }}">
                                @csrf
                                <div class="form-group">
                                    <label>Fecha</label>
                                    <input type="datetime-local" name="payment_date" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Monto</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Método</label>
                                    <select name="method" class="form-control" required>
                                        <option value="cash">Efectivo</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="other">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Referencia</label>
                                    <input name="reference" class="form-control">
                                </div>
                                <h6>Aplicaciones</h6>
                                @foreach($pendingCharges as $idx => $charge)
                                    <div class="border rounded p-2 mb-2">
                                        <div><small>{{ $charge->description }} ({{ optional($charge->due_date)->format('d/m/Y') }})</small></div>
                                        <div><small>Pendiente aprox.: ${{ number_format($charge->pendingAmount(),2) }}</small></div>
                                        <input type="hidden" name="applications[{{ $idx }}][charge_id]" value="{{ $charge->id }}">
                                        <input type="number" step="0.01" min="0" name="applications[{{ $idx }}][amount]" class="form-control form-control-sm mt-1" placeholder="Monto a aplicar (opcional)">
                                    </div>
                                @endforeach
                                <button class="btn btn-primary btn-sm">Registrar pago</button>
                            </form>
                        </div>
                        <div class="col-md-7">
                            <h6>Cargos</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-hover">
                                    <thead><tr><th>Concepto</th><th>Descripción</th><th>Vence</th><th>Monto</th><th>Estatus</th></tr></thead>
                                    <tbody>
                                    @forelse($charges as $charge)
                                        <tr>
                                            <td>{{ optional($charge->concept)->name }}</td>
                                            <td>{{ $charge->description }}</td>
                                            <td>{{ optional($charge->due_date)->format('d/m/Y') }}</td>
                                            <td>${{ number_format((float) $charge->amount,2) }}</td>
                                            <td>{{ $charge->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">Sin cargos.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <h6>Pagos</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Estatus</th></tr></thead>
                                    <tbody>
                                    @forelse($payments as $payment)
                                        <tr>
                                            <td>{{ optional($payment->payment_date)->format('d/m/Y H:i') }}</td>
                                            <td>${{ number_format((float) $payment->amount,2) }}</td>
                                            <td>{{ $payment->method }}</td>
                                            <td>{{ $payment->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Sin pagos.</td></tr>
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
</div>
@endsection

