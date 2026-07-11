@extends('layouts.app')

@section('title', $mode === 'create' ? 'Nuevo proceso SGC' : 'Editar proceso SGC')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ $mode === 'create' ? 'Nuevo proceso SGC' : 'Editar proceso SGC' }}</h4>
        </div>
        <form method="POST" action="{{ $mode === 'create' ? route('coordination.quality.processes.store') : route('coordination.quality.processes.update', $process) }}">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Revisa la información:</strong>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Proceso padre</label>
                        <select name="parent_id" class="form-control">
                            <option value="">Sin padre</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}" {{ (string)old('parent_id', $process->parent_id) === (string)$parent->id ? 'selected' : '' }}>
                                    {{ $parent->code ? $parent->code.' - ' : '' }}{{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Código</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $process->code) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $process->name) }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Orden</label>
                        <input type="number" min="1" name="sort_order" class="form-control" value="{{ old('sort_order', $process->sort_order ?: 1) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tipo de proceso</label>
                        <select name="process_type" class="form-control" required>
                            @foreach($processTypeLabels as $key => $label)
                                <option value="{{ $key }}" {{ old('process_type', $process->process_type ?: 'support') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Cláusulas ISO 9001</label>
                        <input type="text" name="iso_9001_clauses" class="form-control" placeholder="Ej. 4, 5, 6.1" value="{{ old('iso_9001_clauses', $process->iso_9001_clauses) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Cláusulas ISO 21001</label>
                        <input type="text" name="iso_21001_clauses" class="form-control" placeholder="Ej. 4, 5, 8.5" value="{{ old('iso_21001_clauses', $process->iso_21001_clauses) }}">
                    </div>
                    <div class="form-group col-md-12">
                        <label>Descripción</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $process->description) }}</textarea>
                    </div>
                    <div class="form-group col-md-3">
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $process->exists ? $process->is_active : true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('coordination.quality.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
