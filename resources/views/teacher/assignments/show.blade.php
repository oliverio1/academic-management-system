@extends('layouts.app')

@section('title', 'Configuracion de mis materias')

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
                                    Aun no has configurado los criterios de evaluacion para este grupo.
                                </span>
                                <a href="{{ route('teacher.assignments.evaluation', $teachingAssignment) }}"
                                class="btn btn-sm btn-warning">
                                    Configurar evaluacion
                                </a>
                            </div>
                        @endif

                        @if(!empty($activePartial))
                            <div class="alert alert-light border d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Cierre de acta del parcial:</strong>
                                    {{ $activePartial->name }}
                                    @if($activePartial->teacher_capture_deadline_at)
                                        <br>
                                        <small class="text-muted">
                                            Fecha limite docente: {{ $activePartial->teacher_capture_deadline_at->format('d/m/Y H:i') }}
                                        </small>
                                    @endif
                                    @if($economicActa)
                                        @php
                                            $badgeClass = match($economicActa->status) {
                                                'submitted' => 'primary',
                                                'draft' => 'info',
                                                'closed' => 'warning',
                                                'sent' => 'success',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge badge-{{ $badgeClass }}">
                                            {{ strtoupper($economicActa->status) }}
                                        </span>
                                        @if($economicActa->is_auto_closed)
                                            <span class="badge badge-danger">CIERRE AUTOMATICO</span>
                                        @endif
                                    @else
                                        <span class="badge badge-secondary">SIN ENVIAR</span>
                                    @endif
                                </div>
                                @if(!$economicActa || in_array($economicActa->status, ['submitted', 'draft']))
                                    <form method="POST" action="{{ route('teacher.economic-acta.submit', $teachingAssignment) }}">
                                        @csrf
                                        <input type="hidden" name="cycle_partial_id" value="{{ $activePartial->id }}">
                                        <button class="btn btn-outline-primary btn-sm">
                                            Enviar a coordinacion
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if(!$economicActa && $activePartial->teacher_capture_deadline_at && now()->greaterThan($activePartial->teacher_capture_deadline_at))
                                <div class="alert alert-danger">
                                    La fecha limite de cierre docente ya vencio, pero aun puedes enviar a coordinacion manualmente desde el boton superior.
                                </div>
                            @endif

                            @if($economicActa && $economicActa->is_auto_closed && $economicActa->status === 'closed')
                                <div class="alert alert-warning">
                                    <strong>Captura cerrada automaticamente.</strong>
                                    Debes solicitar reapertura a coordinacion para continuar calificando.
                                    @if($pendingReopenRequest)
                                        <div class="mt-2">
                                            <span class="badge badge-info">Solicitud pendiente</span>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('teacher.economic-acta.reopen-request', $economicActa) }}" class="mt-2">
                                            @csrf
                                            <div class="form-group mb-2">
                                                <label class="mb-1">Motivo de reapertura</label>
                                                <textarea name="reason" rows="2" class="form-control" required></textarea>
                                            </div>
                                            <button class="btn btn-sm btn-outline-warning">Solicitar reapertura</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        @endif

                        <div class="mb-3">
                            <a href="{{ route('teacher.documents.programa-operativo', [
                                'teachingAssignment' => $teachingAssignment->id,
                                'school_cycle_id' => optional($activeCycle)->id,
                                'cycle_partial_id' => optional($activePartial)->id,
                            ]) }}"
                               target="_blank"
                               class="btn btn-outline-primary btn-sm">
                                Programa Operativo (PDF)
                            </a>
                            <a href="{{ route('teacher.documents.planeacion-formato', [
                                'teachingAssignment' => $teachingAssignment->id,
                                'school_cycle_id' => optional($activeCycle)->id,
                                'cycle_partial_id' => optional($activePartial)->id,
                            ]) }}"
                               target="_blank"
                               class="btn btn-outline-secondary btn-sm">
                                Formato de Planeacion (PDF)
                            </a>
                        </div>

                        @include('teacher.assignments.partials.tabs')

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
