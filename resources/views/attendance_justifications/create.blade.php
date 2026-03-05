@extends('layouts.app')

@section('title', 'Justificantes')

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
                                <h3>Alta de justificantes</h3>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" 
                            action="{{ route('attendance_justifications.store') }}" 
                            enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Alumno</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="">Seleccione un alumno</option>
                                    @foreach($students as $student)
                                        <option value="{{ $student->id }}">
                                            {{ $student->user->name }} · {{ $student->enrollment_number }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Desde</label>
                                    <input type="date" name="from_date" class="form-control" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" name="to_date" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Motivo</label>
                                <input type="text" name="reason" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Documento (opcional)</label>
                                <input type="file" name="document" class="form-control">
                            </div>

                            <button class="btn btn-primary">
                                Emitir justificante
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection