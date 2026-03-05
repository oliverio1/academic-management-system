@extends('layouts.app')

@section('title', 'Configuración de mis materias')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
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
                                <h2 class="mb-0">
                                    {{ $teachingAssignment->subject->name }}
                                    <small class="text-muted">Grupo {{ $teachingAssignment->group->name }}</small>
                                </h2>
                                <small class="text-muted">
                                    {{ $teachingAssignment->group->students->count() ?? 0 }} alumnos
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($teachingAssignment->evaluation_criteria_count === 0)
                            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                                <span>
                                    ⚠️ Aún no has configurado los criterios de evaluación para este grupo.
                                </span>
                                <a href="{{ route('teacher.assignments.evaluation', $teachingAssignment) }}"
                                class="btn btn-sm btn-warning">
                                    Configurar evaluación
                                </a>
                            </div>
                        @endif

                        {{-- TABS --}}
                        @include('teacher.assignments.partials.tabs')

                        {{-- CONTENIDO --}}
                        <div class="card mt-3">
                            <div class="card-body">
                                @switch(request('tab', 'evaluation'))
                                    @case('evaluation')
                                        @include('teacher.assignments.partials.evaluation')
                                        @break

                                    @case('activities')
                                        @include('teacher.assignments.partials.activities')
                                        @break

                                    @case('attendance')
                                        @include('teacher.assignments.partials.attendance')
                                        @break

                                    @case('teams')
                                        @include('teacher.assignments.partials.teams')
                                        @break

                                    @case('grades')
                                        @include('teacher.assignments.partials.grades')
                                        @break
                                @endswitch
                            </div>
                        </div>
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
        $(document).ready(function () {
            $('#levels').DataTable({
                dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
                "columnDefs": [
                    { "type": "num", "targets": 0 }
                ],
                "order": [[ 0, "asc" ]],
                buttons: [
                    'excelHtml5',
                    'pdfHtml5'
                ],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
@endsection