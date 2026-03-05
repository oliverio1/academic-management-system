@extends('layouts.app')

@section('title', 'Nuevo seguimiento')

@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Solicitar seguimiento de alumno</h5>
    </div>

    <form method="POST" action="{{ route('coordination.follow-ups.store') }}">
        @csrf

        <div class="card-body">

            <div class="form-group">
                <label>Alumno</label>
                <select name="student_id"
                        id="student_id"
                        class="form-control"
                        required>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">
                            {{ $student->user->name }}
                            — {{ $student->group->name ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Tipo de seguimiento</label>
                <select name="type" class="form-control" required>
                    <option value="academic">Académico</option>
                    <option value="behavioral">Conductual</option>
                    <option value="mixed">Mixto</option>
                </select>
            </div>

            <div class="form-group">
                <label>Mensaje para los profesores (opcional)</label>
                <textarea name="message"
                          class="form-control"
                          rows="3"
                          placeholder="Contexto adicional, si es necesario"></textarea>
            </div>

        </div>

        <div class="card-footer text-right">
            <a href="{{ route('coordination.follow-ups.index') }}"
               class="btn btn-secondary">
                Cancelar
            </a>

            <button type="submit" class="btn btn-primary">
                Solicitar seguimiento
            </button>
        </div>
    </form>
</div>

@endsection

@section('page_scripts')
<script>
    $('#student_id').select2({
        theme: 'classic'
    });
</script>
@endsection