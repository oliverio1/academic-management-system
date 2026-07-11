@extends('layouts.app')

@section('title', 'Captura de reporte')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mt-3">
            <strong>Revisa la información:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-0 math-content">{{ $practice->kind_label }} {{ $practice->number }}: {{ $practice->title }}</h4>
                        <small class="text-muted">
                            {{ $practice->teachingAssignment->subject->name }}
                            · Entrega: {{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}
                        </small>
                    </div>
                    <a href="{{ route('student.practices.index') }}" class="btn btn-outline-secondary btn-sm">
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5>Información del profesor</h5>

                                    @if($practice->realization_date)
                                        <p class="mb-2">
                                            <strong>Fecha de realización:</strong>
                                            {{ $practice->realization_date->format('d/m/Y') }}
                                        </p>
                                    @endif

                                    @if($practice->introduction)
                                        <h6>Introducción</h6>
                                        <div class="mb-3 math-content">{!! nl2br(e($practice->introduction)) !!}</div>
                                    @endif

                                    @if($practice->instructions)
                                        <h6>Instrucciones</h6>
                                        <div class="mb-3 math-content">{!! nl2br(e($practice->instructions)) !!}</div>
                                    @endif

                                    @if($practice->procedure)
                                        <h6>Procedimiento</h6>
                                        <div class="math-content">{!! nl2br(e($practice->procedure)) !!}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <form method="POST" action="{{ route('student.practices.store', $practice) }}">
                                @csrf

                                @include('student.practices.partials.submission_fields')

                                @if(!empty($practice->questionnaire))
                                    <hr>
                                    <h5>Preguntas adicionales</h5>
                                    @foreach($practice->questionnaire as $index => $question)
                                        @php $key = ($question['id'] ?? 'q') . '_' . $index; @endphp
                                        <div class="form-group">
                                            <label class="math-content">{{ $question['question'] ?? 'Pregunta' }}</label>
                                            @if(($question['type'] ?? 'text') === 'multiple_choice')
                                                <select name="questionnaire_answers[{{ $key }}]" class="form-control">
                                                    <option value="">Selecciona una opción</option>
                                                    @foreach(($question['options'] ?? []) as $option)
                                                        <option value="{{ $option }}" @selected(($submission->questionnaire_answers[$key] ?? null) === $option)>
                                                            {{ $option }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @elseif(($question['type'] ?? 'text') === 'boolean')
                                                <select name="questionnaire_answers[{{ $key }}]" class="form-control">
                                                    <option value="">Selecciona una opción</option>
                                                    <option value="Sí" @selected(($submission->questionnaire_answers[$key] ?? null) === 'Sí')>Sí</option>
                                                    <option value="No" @selected(($submission->questionnaire_answers[$key] ?? null) === 'No')>No</option>
                                                </select>
                                            @else
                                                <textarea name="questionnaire_answers[{{ $key }}]" class="form-control" rows="3">{{ $submission->questionnaire_answers[$key] ?? '' }}</textarea>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif

                                <div class="text-right">
                                    <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                                        Guardar borrador
                                    </button>
                                    <button type="submit" name="action" value="submitted" class="btn btn-primary">
                                        Guardar y generar reporte
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
    @include('partials.mathjax')
@endsection
