
@extends('layouts.app')

@section('title', 'Actividad')

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            Revisa la información antes de guardar.
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">

                <div class="card">

                    {{-- Header --}}
                    <div class="card-header">
                        <h5 class="mb-0">
                            Actividad de la sesión —
                            {{ $session->teachingAssignment->subject->name }}
                            <small class="text-muted">
                                ({{ $session->teachingAssignment->group->name }})
                            </small>
                        </h5>
                        <hr>
                        Sesión: {{ $session->session_date->translatedFormat('l j \\d\\e F \\d\\e Y') }} | {{ substr($session->start_time, 0, 5) }} – {{ substr($session->end_time, 0, 5) }}
                    </div>

                    {{-- Body --}}
                    <div class="card-body">
                        {{-- Aviso de cierre --}}
                        @if($session->isAttendanceClosed())
                            <div class="alert alert-secondary">
                                🔒 La semana académica está cerrada.
                                <br>
                                <small>No es posible asignar o modificar actividades.</small>
                            </div>
                        @endif

                        {{-- Formulario --}}
                        <form method="POST"
                            action="{{ route('session.activities.store', $session) }}">
                            @csrf

                            {{-- Título --}}
                            <div class="form-group">
                                <label for="title">
                                    Actividad realizada / asignada en clase
                                </label>
                                <input type="text"
                                    id="title"
                                    name="title"
                                    class="form-control"
                                    placeholder="Ej. Resolver ejercicios 5–10 del cuaderno"
                                    value="{{ old('title', optional($activity)->title) }}"
                                    {{ $session->isAttendanceClosed() ? 'disabled' : '' }}
                                    required>
                            </div>

                            {{-- Descripción --}}
                            <div class="form-group">
                                <label for="description">
                                    Descripción (opcional)
                                </label>
                                <textarea id="description"
                                    name="description"
                                    rows="3"
                                    class="form-control"
                                    placeholder="Indicaciones adicionales, observaciones, etc."
                                    {{ $session->isAttendanceClosed() ? 'disabled' : '' }}>{{ old('description', optional($activity)->description) }}</textarea>
                            </div>

                            {{-- Footer --}}
                            <div class="d-flex justify-content-between mt-4">
                                <a href="{{ route('dashboard') }}"
                                class="btn btn-secondary">
                                    Volver
                                </a>

                                @unless($session->isAttendanceClosed())
                                    <button class="btn btn-primary">
                                        {{ $activity ? 'Actualizar actividad' : 'Guardar actividad' }}
                                    </button>
                                @endunless
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
<style>
    .attendance-radio {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Ocultamos el radio nativo */
    .attendance-radio input[type="radio"] {
        display: none;
    }

    /* Círculo base */
    .attendance-radio label {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #ccc;
        cursor: pointer;
        position: relative;
    }

    /* Punto interior (apagado) */
    .attendance-radio label::after {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: transparent;
    }

    /* ===== COLORES ===== */

    /* Presente */
    .attendance-present input:checked + label {
        border-color: #28a745;
    }
    .attendance-present input:checked + label::after {
        background: #28a745;
    }

    /* Retardo */
    .attendance-late input:checked + label {
        border-color: #ffc107;
    }
    .attendance-late input:checked + label::after {
        background: #ffc107;
    }

    /* Falta */
    .attendance-absent input:checked + label {
        border-color: #dc3545;
    }
    .attendance-absent input:checked + label::after {
        background: #dc3545;
    }

    /* Deshabilitado (asistencia cerrada) */
    .attendance-radio input:disabled + label {
        cursor: not-allowed;
        opacity: 0.6;
    }
</style>
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#modalities').DataTable({
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