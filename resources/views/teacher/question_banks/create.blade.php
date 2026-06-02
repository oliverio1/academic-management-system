@extends('layouts.app')

@section('title', 'Nuevo banco de preguntas')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Crear banco de preguntas</h4></div>
        <form method="POST" action="{{ route('teacher.question-banks.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Materia</label>
                        <select name="subject_id" class="form-control" required>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Ciclo</label>
                        <select name="school_cycle_id" class="form-control">
                            <option value="">Sin ciclo</option>
                            @foreach($cycles as $cycle)
                                <option value="{{ $cycle->id }}">{{ $cycle->name }} ({{ $cycle->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Parcial</label>
                        <select name="cycle_partial_id" class="form-control">
                            <option value="">Sin parcial</option>
                            @foreach($partials as $partial)
                                <option value="{{ $partial->id }}">{{ $partial->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Crear banco</button>
                <a href="{{ route('teacher.question-banks.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

