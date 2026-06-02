@extends('layouts.app')

@section('title', $mode === 'create' ? 'Nuevo PNO' : 'Editar PNO')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ $mode === 'create' ? 'Nuevo PNO' : 'Editar PNO' }}</h4>
        </div>
        <form method="POST" action="{{ $mode === 'create' ? route('coordination.quality.documents.store') : route('coordination.quality.documents.update', $document) }}">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Proceso</label>
                        <select name="quality_process_id" class="form-control" required>
                            <option value="">Selecciona...</option>
                            @foreach($processes as $process)
                                <option value="{{ $process->id }}" {{ (string)old('quality_process_id', $document->quality_process_id) === (string)$process->id ? 'selected' : '' }}>
                                    {{ $process->code ? $process->code.' - ' : '' }}{{ $process->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Código</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $document->code) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Título</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Versión</label>
                        <input type="text" name="version" class="form-control" value="{{ old('version', $document->version) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Estatus</label>
                        <select name="status" class="form-control" required>
                            @foreach(['draft' => 'Borrador', 'active' => 'Vigente', 'obsolete' => 'Obsoleto'] as $key => $label)
                                <option value="{{ $key }}" {{ old('status', $document->status ?: 'draft') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Fecha efectiva</label>
                        <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', optional($document->effective_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Fecha revisión</label>
                        <input type="date" name="review_date" class="form-control" value="{{ old('review_date', optional($document->review_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Responsable</label>
                        <input type="text" name="owner" class="form-control" value="{{ old('owner', $document->owner) }}">
                    </div>
                    <div class="form-group col-md-12">
                        <label>Contenido del PNO</label>
                        <textarea name="content" rows="12" class="form-control">{{ old('content', $document->content) }}</textarea>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Resumen de cambios</label>
                        <textarea name="change_summary" rows="2" class="form-control" placeholder="Describe brevemente qué cambió en esta versión">{{ old('change_summary') }}</textarea>
                    </div>
                    <div class="form-group col-md-3">
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $document->exists ? $document->is_active : true) ? 'checked' : '' }}>
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
