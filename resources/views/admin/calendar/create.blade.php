@extends('layouts.app')

@section('title', 'Días festivos y vacaciones')

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
                                <h3>Alta de días festivos o vacaciones</h3>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('academic-calendar-days.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label>Tipo</label>
                                        <select name="type" id="type" class="form-control" required>
                                            <option value="">Seleccione</option>
                                            <option value="holiday">Día feriado</option>
                                            <option value="vacation">Vacaciones</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label>Nombre / descripción</label>
                                        <input type="text"
                                        name="name"
                                        class="form-control"
                                        placeholder="Ej. Vacaciones de verano"
                                        required>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label>Modalidad</label>
                                        <select name="modality_id" class="form-control">
                                            <option value="">Todas las modalidades</option>
                                            @foreach($modalities as $modality)
                                                <option value="{{ $modality->id }}">
                                                    {{ $modality->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div class="form-group" id="single-date" style="display:none;">
                                        <label>Fecha</label>
                                        <input type="date"
                                            name="date"
                                            class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div id="range-dates" style="display:none;">
                                        <div class="form-group">
                                            <label>Fecha inicio</label>
                                            <input type="date"
                                                name="start_date"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Fecha fin</label>
                                            <input type="date"
                                                name="end_date"
                                                class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="affects_teachers" checked>
                                            Afecta docentes
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="affects_students" checked>
                                            Afecta alumnos
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-info" type="submit">Guardar</button>
                            <a href="{{ route('academic-calendar-days.index') }}" class="btn btn-danger">Cancelar</a>
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
    <script>
        document.getElementById('type').addEventListener('change', function () {
            const single = document.getElementById('single-date');
            const range = document.getElementById('range-dates');

            single.style.display = this.value === 'holiday' ? 'block' : 'none';
            range.style.display = this.value === 'vacation' ? 'block' : 'none';
        });
    </script>
@endsection