@extends('layouts.app')

@section('title', 'Validacion de alumnos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Validacion de alumnos del ciclo</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Campus:</strong> {{ $campus->name }} ({{ $campus->code }})
                        <span class="ml-3"><strong>Ciclo:</strong> {{ $cycle->name }} ({{ $cycle->code }})</span>
                    </div>

                    <div class="alert {{ $summary['has_warnings'] ? 'alert-warning' : 'alert-success' }}">
                        Esta validacion no guardo cambios. Revisa el resumen antes de confirmar la importacion.
                        @if($options['deactivate_missing'] ?? false)
                            <br>Al confirmar, se inactivaran alumnos del ciclo/campus que no aparezcan en el archivo.
                        @endif
                    </div>

                    @include('imports.cycle-students._summary', ['summary' => $summary])
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('imports.cycle-students.create') }}" class="btn btn-secondary">Volver</a>
                    <form method="POST" action="{{ route('imports.cycle-students.import') }}">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            Confirmar importacion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
