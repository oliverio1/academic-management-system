@extends('layouts.app')

@section('title', 'Reporte de entregable')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning mt-3">{{ session('warning') }}</div>
    @endif

    @php
        $hasTeacherReview = $submission->status === 'reviewed'
            || $submission->reviewed_at
            || $submission->score !== null
            || filled($submission->teacher_corrections)
            || filled($submission->teacher_comments)
            || filled($submission->teacher_suggestions);
    @endphp

    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Reporte generado</h4>
                    <div>
                        @if($canCapture)
                            <a href="{{ route('student.practices.show', $practice) }}" class="btn btn-outline-secondary btn-sm">
                                Editar
                            </a>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                Edición cerrada
                            </button>
                        @endif
                        <a href="{{ route('student.practices.pdf', $practice) }}" class="btn btn-danger btn-sm">
                            Descargar PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @include('student.practices.partials.report_content')
                </div>
            </div>
        </div>

        @if($hasTeacherReview)
            <div class="col-md-12 mt-3">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Revisión del profesor</h4>
                    </div>
                    <div class="card-body">
                        @include('student.practices.partials.teacher_review_content')
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('page_scripts')
    @include('partials.mathjax')
@endsection
