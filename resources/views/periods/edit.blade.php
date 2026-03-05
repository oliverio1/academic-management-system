@extends('layouts.app')

@section('title', 'Periodos')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h3>Alta de periodos</h3>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('academic-periods.update', $period) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Modalidad</label>
                                    <select name="modality_id" class="form-control @error('modality_id') is-invalid @enderror" required>
                                        @foreach($modalities as $modality)
                                            <option value="{{ $modality->id }}" {{ old('modality_id', $student->modality_id ?? '') == $modality->id ? 'selected' : '' }}> {{ $modality->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('modality_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Nombre del periodo</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $period->name ?? '') }}">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Código periodo</label>
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code', $period->code ?? '') }}">
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Fecha de inicio</label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" name="start_date" value="{{ old('start_date', $period->start_date ?? '') }}">
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Fecha de termino</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" name="end_date" value="{{ old('end_date', $period->end_date ?? '') }}">
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-info" type="submit">Guardar</button>
                            <a href="{{ route('academic-periods.index') }}" class="btn btn-danger">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
    <script>
        (function () {
            'use strict'
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation')
        
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
@endsection