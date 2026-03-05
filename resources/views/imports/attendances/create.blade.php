@extends('layouts.app')

@section('title', 'Importar asistencias')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Importar asistencias desde Excel</h3>
                </div>
                <form method="POST"
                      action="{{ route('imports.attendances.store') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        {{-- Periodo académico --}}
                        <div class="form-group">
                            <label>Periodo académico</label>
                            <select name="academic_period_id"
                                    class="form-control"
                                    required>
                                <option value="">Seleccione...</option>
                                @foreach($academicPeriods as $period)
                                    <option value="{{ $period->id }}">
                                        {{ $period->name }} ({{ $period->modality->name }})
                                    </option>
                                @endforeach
                                </select>
                        </div>
                        {{-- Archivo --}}
                        <div class="form-group">
                            <label>Archivo Excel</label>
                            <input type="file"
                                   name="file"
                                   class="form-control"
                                   accept=".xlsx,.xls"
                                   required>
                        </div>
                        {{-- Errores --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection