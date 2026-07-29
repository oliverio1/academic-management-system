@extends('layouts.app')

@section('title', 'Cargar planeacion')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-8 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">Cargar planeacion desde Excel</h4>
                            <small class="text-muted">{{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}</small>
                        </div>
                        <a href="{{ route('teacher.didactic-plans.plans', $assignment) }}" class="btn btn-secondary btn-sm">
                            Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <strong>No se pudo importar.</strong>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('teacher.didactic-plans.import.store', $assignment) }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label for="planning_file">Archivo Excel de planeacion</label>
                            <input type="file"
                                   id="planning_file"
                                   name="planning_file"
                                   class="form-control-file"
                                   accept=".xlsx,.xls"
                                   required>
                            <small class="form-text text-muted">
                                Usa el machote descargado desde esta materia. Se leeran las hojas Institucion, Materia, Evaluacion y Planeacion.
                            </small>
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox"
                                   id="replace_existing"
                                   name="replace_existing"
                                   value="1"
                                   class="form-check-input">
                            <label class="form-check-label" for="replace_existing">
                                Reemplazar planeaciones existentes de esta materia en el ciclo del archivo
                            </label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('teacher.didactic-plans.template', $assignment) }}" class="btn btn-outline-success">
                                Descargar machote
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Importar planeacion
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4 mt-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Que se importara</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 pl-3">
                        <li>Una planeacion para esta materia y grupo.</li>
                        <li>Un renglon por cada fila con fecha en la hoja Planeacion.</li>
                        <li>Objetivo, actividad, evidencia e instrumento.</li>
                        <li>Bibliografia, recursos y criterios de evaluacion generales.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
