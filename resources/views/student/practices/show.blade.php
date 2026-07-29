@extends('layouts.app')

@section('title', 'Capturar entrega')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mt-3">
            <strong>Revisa la informacion:</strong>
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
                        <span class="badge badge-info">{{ $practice->kind_label }}</span>
                        <h4 class="mb-0 mt-1 math-content">{{ $practice->number }}. {{ $practice->title }}</h4>
                        <small class="text-muted">
                            {{ $practice->teachingAssignment->subject->name }}
                            - Entrega: {{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}
                        </small>
                    </div>
                    <a href="{{ route('student.practices.index') }}" class="btn btn-outline-secondary btn-sm">
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="student-delivery-panel mb-3">
                                <h5>Indicaciones del profesor</h5>

                                @if($practice->realization_date)
                                    <p class="mb-2">
                                        <strong>Fecha de realizacion:</strong>
                                        {{ $practice->realization_date->format('d/m/Y') }}
                                    </p>
                                @endif

                                @if($practice->introduction)
                                    <h6>Contexto</h6>
                                    <div class="mb-3 math-content">{!! nl2br(e($practice->introduction)) !!}</div>
                                @endif

                                @if($practice->instructions)
                                    <h6>Instrucciones</h6>
                                    <div class="mb-3 math-content">{!! nl2br(e($practice->instructions)) !!}</div>
                                @endif

                                @if($practice->procedure)
                                    <h6>Procedimiento o criterios</h6>
                                    <div class="math-content">{!! nl2br(e($practice->procedure)) !!}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <form method="POST" action="{{ route('student.practices.store', $practice) }}" enctype="multipart/form-data">
                                @csrf

                                <div class="student-delivery-panel">
                                    <h5>Tu entrega</h5>
                                    <p class="text-muted small">
                                        Guarda como borrador si aun estas trabajando. Envia cuando quieras generar la entrega final para revision.
                                    </p>

                                    @if($submission->is_resubmission_allowed)
                                        <div class="alert alert-warning">
                                            <strong>Reentrega solicitada.</strong>
                                            Fecha limite: {{ optional($submission->resubmission_due_date)->format('d/m/Y') ?? '-' }}
                                            @if($submission->resubmission_note)
                                                <div class="mt-2">{{ $submission->resubmission_note }}</div>
                                            @endif
                                        </div>
                                    @endif

                                    @include('student.practices.partials.submission_fields')

                                    <hr>
                                    <h5>Evidencias y archivos</h5>
                                    <p class="text-muted small mb-2">
                                        Puedes adjuntar hasta 5 archivos por guardado: PDF, Word o imagenes. Tamano maximo por archivo: 10 MB.
                                    </p>

                                    @if($submission->attachments->isNotEmpty())
                                        <div class="list-group mb-3">
                                            @foreach($submission->attachments as $attachment)
                                                <label class="list-group-item d-flex justify-content-between align-items-center mb-0">
                                                    <span>
                                                        <a href="{{ route('practice-submission-attachments.download', $attachment) }}">
                                                            {{ $attachment->original_name }}
                                                        </a>
                                                        <small class="text-muted d-block">
                                                            {{ number_format($attachment->size / 1024, 1) }} KB
                                                        </small>
                                                    </span>
                                                    <span class="text-muted small">
                                                        <input type="checkbox" name="delete_attachment_ids[]" value="{{ $attachment->id }}">
                                                        Quitar
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="form-group">
                                        <label>Agregar archivos</label>
                                        <input type="file"
                                               name="attachments[]"
                                               class="form-control"
                                               multiple
                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                                    </div>

                                    @if(!empty($practice->questionnaire))
                                        <hr>
                                        <h5>Preguntas adicionales</h5>
                                        @foreach($practice->questionnaire as $index => $question)
                                            @php $key = ($question['id'] ?? 'q') . '_' . $index; @endphp
                                            <div class="form-group">
                                                <label class="math-content">{{ $question['question'] ?? 'Pregunta' }}</label>
                                                @if(($question['type'] ?? 'text') === 'multiple_choice')
                                                    <select name="questionnaire_answers[{{ $key }}]" class="form-control">
                                                        <option value="">Selecciona una opcion</option>
                                                        @foreach(($question['options'] ?? []) as $option)
                                                            <option value="{{ $option }}" @selected(($submission->questionnaire_answers[$key] ?? null) === $option)>
                                                                {{ $option }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif(($question['type'] ?? 'text') === 'boolean')
                                                    <select name="questionnaire_answers[{{ $key }}]" class="form-control">
                                                        <option value="">Selecciona una opcion</option>
                                                        <option value="Si" @selected(($submission->questionnaire_answers[$key] ?? null) === 'Si')>Si</option>
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
                                            Enviar entrega
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .student-delivery-panel {
        border: 1px solid #dbe3ee;
        border-radius: 6px;
        padding: 1rem;
    }
</style>
@endsection

@section('page_scripts')
    @include('partials.mathjax')
@endsection
