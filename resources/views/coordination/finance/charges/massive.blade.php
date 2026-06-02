@extends('layouts.app')

@section('title', 'Carga masiva de cargos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-10 mt-3">
            <div class="card">
                <div class="card-header"><h4 class="mb-0">Carga masiva de cargos por grupo</h4></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('coordination.finance.charges.massive.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="col-md-4 mb-3">
                                <label>Ciclo (opcional)</label>
                                <select name="school_cycle_id" class="form-control">
                                    <option value="">Sin ciclo</option>
                                    @foreach($activeCycles as $cycle)
                                        <option value="{{ $cycle->id }}">{{ $cycle->name }} ({{ $cycle->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Grupo</label>
                                <select name="group_id" class="form-control" required>
                                    <option value="">Seleccione grupo</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Concepto</label>
                                <select name="concept_id" class="form-control" required>
                                    <option value="">Seleccione concepto</option>
                                    @foreach($concepts as $concept)
                                        <option value="{{ $concept->id }}">{{ $concept->name }} ({{ $concept->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label>Descripción</label>
                                <input name="description" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Referencia</label>
                                <input name="reference" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Monto</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Vencimiento</label>
                                <input type="date" name="due_date" class="form-control" required>
                            </div>
                        </div>
                        <button class="btn btn-primary">Generar cargos</button>
                        <a href="{{ route('coordination.finance.charges.index') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

