@extends('layouts.app')

@section('title', 'Reporte de entrega')

@section('content')
<div class="content px-3">
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
                    <h4 class="mb-0">Reporte de entrega</h4>
                    <div>
                        <a href="{{ route('practices.submissions', $practice) }}" class="btn btn-outline-secondary btn-sm">
                            Volver
                        </a>
                        <a href="{{ route('practices.submissions.pdf', $submission) }}" class="btn btn-danger btn-sm">
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
