@extends('layouts.app')

@section('title', 'Detalle de actividades')

@section('content')

<div class="container-fluid">

    <h4 class="mb-3">
        Detalle de actividades
        <small class="text-muted d-block">
            {{ $student->user->name }}
        </small>
    </h4>

    <div class="accordion" id="subjectsAccordion">

        @forelse($matrix as $subject => $values)

            @php
                $subjectId = \Illuminate\Support\Str::slug($subject);
                $attendance = $attendanceSummary[$subject] ?? null;

                // calcular promedio simple de calificaciones visibles
                $scores = collect($values)
                    ->flatten()
                    ->filter(fn($v) => is_numeric($v));

                $average = $scores->count()
                    ? round($scores->avg(), 1)
                    : null;

                // alerta si asistencia baja
                $hasAlert = $attendance !== null && $attendance < 80;
            @endphp

            <div class="card mb-2">

                {{-- HEADER --}}
                <div class="card-header py-2" id="heading-{{ $subjectId }}">
                    <button
                        class="btn btn-link d-flex justify-content-between align-items-center w-100 text-left"
                        data-toggle="collapse"
                        data-target="#collapse-{{ $subjectId }}"
                        aria-expanded="false"
                        aria-controls="collapse-{{ $subjectId }}"
                    >
                        <div>
                            <strong>{{ $subject }}</strong>
                        </div>

                        <div class="text-right small">

                            <span class="mr-3">
                                Prom:
                                <strong>
                                    {{ $average !== null ? $average : '—' }}
                                </strong>
                            </span>

                            <span class="mr-3">
                                Asist:
                                <strong>
                                    {{ $attendance !== null ? $attendance.'%' : '—' }}
                                </strong>
                            </span>

                            @if($hasAlert)
                                <span class="text-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                            @else
                                <span class="text-success">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            @endif

                        </div>
                    </button>
                </div>

                {{-- BODY --}}
                <div
                    id="collapse-{{ $subjectId }}"
                    class="collapse"
                    data-parent="#subjectsAccordion"
                >
                    <div class="card-body p-2">

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered text-center mb-0">

                                <thead>
                                    <tr>
                                        <th class="text-left">Fecha</th>
                                        <th class="text-left">Actividad</th>
                                        <th>Calificación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($values as $row)
                                        <tr>
                                            <td>
                                                {{ \Carbon\Carbon::parse($row['date'])->format('d/m') }}
                                            </td>

                                            <td class="text-left">
                                                {{ $row['title'] }}
                                            </td>

                                            <td>
                                                @if(is_numeric($row['score']))
                                                    <span class="badge badge-success">
                                                        {{ $row['score'] }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>

        @empty

            <div class="alert alert-secondary">
                No hay actividades registradas para este alumno.
            </div>

        @endforelse

    </div>

</div>

@endsection
