@extends('layouts.app')

@section('title', 'Revisar entrega')

@section('content')
<div class="content px-3">
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
        <div class="col-lg-8 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Entrega del alumno</h4>
                    <small class="text-muted">
                        {{ $practice->kind_label }} {{ $practice->number }}: {{ $practice->title }}
                    </small>
                </div>
                <div class="card-body">
                    @include('student.practices.partials.report_content')
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Revision y calificacion</h4>
                    <small class="text-muted">
                        Rubro: {{ $practice->activity->evaluationCriterion->name ?? '-' }}
                    </small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('practices.submissions.review.store', $submission) }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label>Calificacion</label>
                            <input type="number"
                                   name="score"
                                   class="form-control"
                                   step="0.1"
                                   min="0"
                                   max="{{ $practice->activity->max_score ?? 10 }}"
                                   value="{{ old('score', $submission->score ?? optional($grade)->score) }}"
                                   required>
                            <small class="text-muted">Maximo: {{ $practice->activity->max_score ?? 10 }}</small>
                        </div>

                        <div class="form-group">
                            <label>Correcciones</label>
                            <textarea name="teacher_corrections" class="form-control" rows="4">{{ old('teacher_corrections', $submission->teacher_corrections) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label>Comentarios</label>
                            <textarea name="teacher_comments" class="form-control" rows="4">{{ old('teacher_comments', $submission->teacher_comments) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label>Sugerencias</label>
                            <textarea name="teacher_suggestions" class="form-control" rows="4">{{ old('teacher_suggestions', $submission->teacher_suggestions) }}</textarea>
                        </div>

                        <hr>

                        <div class="form-group form-check">
                            <input type="hidden" name="allow_resubmission" value="0">
                            <input type="checkbox"
                                   name="allow_resubmission"
                                   value="1"
                                   class="form-check-input"
                                   id="allow_resubmission"
                                   @checked(old('allow_resubmission', $submission->is_resubmission_allowed))>
                            <label class="form-check-label" for="allow_resubmission">
                                Solicitar reentrega
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Fecha limite de reentrega</label>
                            <input type="date"
                                   name="resubmission_due_date"
                                   class="form-control"
                                   value="{{ old('resubmission_due_date', optional($submission->resubmission_due_date)->format('Y-m-d')) }}">
                        </div>

                        <div class="form-group">
                            <label>Indicaciones para la reentrega</label>
                            <textarea name="resubmission_note" class="form-control" rows="3">{{ old('resubmission_note', $submission->resubmission_note) }}</textarea>
                        </div>

                        <button class="btn btn-primary btn-block">Guardar revision</button>
                        <a href="{{ route('practices.submissions', $practice) }}" class="btn btn-outline-secondary btn-block">
                            Cancelar
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
    @include('partials.mathjax')
@endsection
