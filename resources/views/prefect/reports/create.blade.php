@extends('layouts.app')

@section('title', 'Nuevo reporte')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Reportar situacion</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('prefect.reports.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="report_to">A quien deseas reportar</label>
                            <select name="report_to" id="report_to" class="form-control" required>
                                <option value="">Seleccione destinatario</option>
                                @foreach($reportToOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('report_to') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="category">Tipo de situacion</label>
                            <select name="category" id="category" class="form-control" required>
                                <option value="">Seleccione categoria</option>
                                @foreach($categoryOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('category') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject">Asunto</label>
                        <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}" maxlength="150" required>
                    </div>

                    <div class="mb-3">
                        <label for="description">Descripcion del reporte</label>
                        <textarea name="description" id="description" rows="6" class="form-control" required>{{ old('description') }}</textarea>
                    </div>

                    <button class="btn btn-primary">Enviar reporte</button>
                    <a href="{{ route('prefect.reports.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
