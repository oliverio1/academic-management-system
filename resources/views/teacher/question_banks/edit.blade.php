@extends('layouts.app')

@section('title', 'Editar banco de preguntas')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Editar banco de preguntas</h4>
                <small class="text-muted">{{ $questionBank->questions()->count() }} pregunta(s) registradas</small>
            </div>
            <a href="{{ route('teacher.question-banks.show', $questionBank) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
        <form method="POST" action="{{ route('teacher.question-banks.update', $questionBank) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $questionBank->name) }}" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Ciclo</label>
                        <select id="school_cycle_id" name="school_cycle_id" class="form-control" required>
                            @foreach($cycles as $cycle)
                                <option value="{{ $cycle->id }}" @selected((int) old('school_cycle_id', optional($activeCycle)->id) === (int) $cycle->id)>
                                    {{ $cycle->name }} ({{ $cycle->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Materia</label>
                        <select name="subject_id" class="form-control" required>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((int) old('subject_id', $questionBank->subject_id) === (int) $subject->id)>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Parcial</label>
                        <select name="cycle_partial_id" class="form-control" required>
                            <option value="">Selecciona parcial</option>
                            @foreach($partials as $partial)
                                <option value="{{ $partial->id }}" @selected((int) old('cycle_partial_id', $questionBank->cycle_partial_id) === (int) $partial->id)>
                                    {{ $partial->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Si el banco ya se uso en un examen, solo se permite cambiar nombre y descripcion.</small>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Descripcion</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $questionBank->description) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('teacher.question-banks.show', $questionBank) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cycleSelect = document.getElementById('school_cycle_id');
    if (!cycleSelect) return;

    cycleSelect.addEventListener('change', function () {
        const url = new URL(@json(route('teacher.question-banks.edit', $questionBank)), window.location.origin);
        url.searchParams.set('school_cycle_id', cycleSelect.value);
        window.location.href = url.toString();
    });
});
</script>
@endsection
