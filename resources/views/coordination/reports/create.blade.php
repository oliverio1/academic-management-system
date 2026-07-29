@extends('layouts.app')

@section('title', 'Levantar reporte')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Levantar reporte</h3>
                    <small class="text-muted">Para llamadas, visitas de tutores, alumnos que reportan personalmente o situaciones recibidas por coordinacion.</small>
                </div>
                <form method="POST" action="{{ route('coordination.reports.store') }}">
                    @csrf
                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <strong>Revisa la informacion capturada.</strong>
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label>Medio de recepcion</label>
                                <select name="received_via" class="form-control" required>
                                    @foreach($receivedViaOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('received_via', 'in_person') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Categoria inicial</label>
                                <select name="category" class="form-control" required>
                                    @foreach($categoryOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('category', 'other') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Prioridad inicial</label>
                                <select name="priority" class="form-control" required>
                                    <option value="1" {{ old('priority', '2') === '1' ? 'selected' : '' }}>Baja</option>
                                    <option value="2" {{ old('priority', '2') === '2' ? 'selected' : '' }}>Media</option>
                                    <option value="3" {{ old('priority', '2') === '3' ? 'selected' : '' }}>Alta</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Persona que reporta</label>
                                <input name="reporter_name" value="{{ old('reporter_name') }}" class="form-control" maxlength="160" placeholder="Tutor, alumno, visitante">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Contacto</label>
                                <input name="reporter_contact" value="{{ old('reporter_contact') }}" class="form-control" maxlength="180" placeholder="Telefono o correo">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label>Asunto</label>
                                <input name="subject" value="{{ old('subject') }}" class="form-control" maxlength="150" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Descripcion</label>
                            <textarea name="description" rows="6" class="form-control" required>{{ old('description') }}</textarea>
                            <small class="form-text text-muted">
                                Se breve, claro y suficiente para identificar el caso. Incluye alumno, matricula, grupo, persona involucrada y datos de contacto cuando aplique.
                            </small>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('coordination.reports.index') }}" class="btn btn-secondary">Cancelar</a>
                        <button class="btn btn-primary">Guardar reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
