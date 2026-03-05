@extends('layouts.app')

@section('title', 'Asignar Materias')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-8 offset-md-2 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>
                        Materias del profesor: {{ $teacher->user->name }}
                    </h4>
                </div>
                <div class="card-body">
                    <form method="POST"
                          action="{{ route('teachers.subjects.update', $teacher) }}">
                        @csrf
                        <div class="form-group">
                            @foreach($subjects as $subject)
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="subjects[]"
                                           value="{{ $subject->id }}"
                                           id="subject{{ $subject->id }}"
                                           {{ in_array($subject->id, $assignedSubjects) ? 'checked' : '' }}>
                                    <label class="form-check-label"
                                           for="subject{{ $subject->id }}">
                                        {{ $subject->name }}
                                        <small class="text-muted">
                                            ({{ $subject->level->name ?? 'Sin nivel' }})
                                        </small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <hr>
                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('teachers.index') }}"
                           class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
