@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3"><strong>{{ session('success') }}</strong></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mt-3">
            <strong>No se pudo registrar el movimiento.</strong>
            <ul class="mb-0 mt-2 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">{{ $item->name }}</h4></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Codigo</dt>
                        <dd class="col-sm-7">{{ $item->code ?: '-' }}</dd>
                        <dt class="col-sm-5">Categoria</dt>
                        <dd class="col-sm-7">{{ $item->category->name ?? '-' }}</dd>
                        <dt class="col-sm-5">Ubicacion</dt>
                        <dd class="col-sm-7">{{ $item->location->name ?? '-' }}</dd>
                        <dt class="col-sm-5">Existencia</dt>
                        <dd class="col-sm-7">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</dd>
                        <dt class="col-sm-5">Minimo</dt>
                        <dd class="col-sm-7">{{ number_format((float) $item->minimum_quantity, 2) }}</dd>
                        <dt class="col-sm-5">Estatus</dt>
                        <dd class="col-sm-7">{{ ucfirst($item->status) }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <a href="{{ route('coordination.inventory.edit', $item) }}" class="btn btn-warning">Editar</a>
                    <a href="{{ route('coordination.inventory.index') }}" class="btn btn-light">Volver</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Registrar movimiento</h5></div>
                <form method="POST" action="{{ route('coordination.inventory.movements.store', $item) }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="type" class="form-control" required>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste a existencia exacta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            <input type="text" name="reason" class="form-control" placeholder="Compra, consumo, prestamo, merma...">
                        </div>
                        <div class="form-group">
                            <label>Notas</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary btn-block" type="submit">Guardar movimiento</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">Historial de movimientos</h4></div>
                <div class="card-body">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Antes</th>
                                <th>Despues</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($item->movements as $movement)
                                <tr>
                                    <td>{{ $movement->moved_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ ucfirst($movement->type) }}</td>
                                    <td>{{ number_format((float) $movement->quantity, 2) }}</td>
                                    <td>{{ number_format((float) $movement->quantity_before, 2) }}</td>
                                    <td>{{ number_format((float) $movement->quantity_after, 2) }}</td>
                                    <td>{{ $movement->user->name ?? '-' }}</td>
                                    <td>{{ $movement->reason ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin movimientos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
