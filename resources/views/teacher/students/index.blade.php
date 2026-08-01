@extends('layouts.app')

@section('title', 'Mis alumnos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Mis alumnos</h4>
                            @if($activeCycle)
                                <div class="text-muted small">
                                    {{ $activeCycle->name }} ({{ $activeCycle->code }})
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('teacher.classes.index') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-chalkboard-teacher mr-1"></i>Mis clases
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(!$activeCycle)
                        <div class="alert alert-warning mb-0">
                            No hay un ciclo escolar activo configurado.
                        </div>
                    @elseif(($groupCards ?? collect())->isEmpty())
                        <div class="teacher-empty-state">
                            No tienes grupos asignados en el ciclo activo.
                        </div>
                    @else
                        <div class="teacher-group-grid">
                            @foreach($groupCards as $card)
                                @php
                                    $group = $card['group'];
                                @endphp
                                <a href="{{ route('teacher.students.group', $group) }}" class="teacher-group-card">
                                    <div class="teacher-group-main">
                                        <div>
                                            <div class="teacher-group-label">Grupo</div>
                                            <div class="teacher-group-name">{{ $group->name }}</div>
                                            @if($card['level'])
                                                <div class="text-muted small">{{ $card['level'] }}</div>
                                            @endif
                                        </div>
                                        <span class="teacher-group-action">
                                            <i class="fas fa-users"></i>
                                        </span>
                                    </div>

                                    <div class="teacher-group-meta">
                                        <span>{{ $card['students_count'] }} alumnos</span>
                                        <span>{{ $card['assignments'] }} asignaciones</span>
                                    </div>

                                    <div class="teacher-subject-list">
                                        @foreach($card['subjects']->take(4) as $subject)
                                            <span>{{ $subject }}</span>
                                        @endforeach
                                        @if($card['subjects']->count() > 4)
                                            <span>+{{ $card['subjects']->count() - 4 }}</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .teacher-empty-state {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        color: #6c757d;
        padding: 1rem;
    }

    .teacher-group-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    }

    .teacher-group-card {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        color: #212529;
        display: block;
        min-height: 176px;
        padding: 1rem;
        text-decoration: none;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .teacher-group-card:hover {
        border-color: #0d6efd;
        box-shadow: 0 6px 18px rgba(13, 110, 253, .12);
        color: #212529;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .teacher-group-main {
        align-items: flex-start;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .teacher-group-label {
        color: #6c757d;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .teacher-group-name {
        font-size: 1.65rem;
        font-weight: 800;
        line-height: 1.05;
    }

    .teacher-group-action {
        align-items: center;
        background: #0d6efd;
        border-radius: 4px;
        color: #fff;
        display: inline-flex;
        height: 34px;
        justify-content: center;
        width: 34px;
    }

    .teacher-group-meta {
        color: #495057;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
        font-size: .88rem;
    }

    .teacher-group-meta span {
        background: #f1f3f5;
        border-radius: 4px;
        padding: .25rem .45rem;
    }

    .teacher-subject-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 14px;
    }

    .teacher-subject-list span {
        border: 1px solid #e9ecef;
        border-radius: 4px;
        color: #495057;
        font-size: .78rem;
        padding: .2rem .4rem;
    }
</style>
@endsection
