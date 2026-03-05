@extends('layouts.app')

@section('title', 'Detalle de asistencias')

@section('content')
<div class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('coordination.students.show', $student) }}"
               class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>

            <h1 class="m-0 mt-2">Detalle de asistencias</h1>
            <small class="text-muted">{{ $student->user->name }}</small>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">

                <div class="table-responsive attendance-scroll">
                    <table class="table table-bordered table-sm text-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-left sticky-col bg-white">Materia</th>
                                @foreach($dates as $date)
                                    <th>
                                        {{ \Carbon\Carbon::parse($date)->format('d/m') }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($matrix as $subject => $values)
                                <tr>
                                    <td class="text-left sticky-col bg-white font-weight-bold">
                                        {{ $subject }}
                                    </td>

                                    @foreach($dates as $date)
                                        <td
                                            @if(!isset($imparted[$subject][$date]))
                                                class="bg-secondary text-white"
                                            @endif
                                        >
                                        @if(isset($values[$date]))
                                            @if($values[$date] === 1)
                                                {{-- Asistencia --}}
                                                <span class="text-success" title="Asistencia">
                                                    <i class="fas fa-check"></i>
                                                </span>
                                            @elseif($values[$date] === 0)
                                                {{-- Falta --}}
                                                <span class="text-danger" title="Falta">
                                                    <i class="fas fa-times"></i>
                                                </span>
                                            @elseif($values[$date] === 'justified')
                                                {{-- Justificada --}}
                                                <span class="text-warning" title="Falta justificada">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </span>
                                            @else
                                                {{-- Sin registro --}}
                                                <span class="text-muted">—</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($dates) + 1 }}" class="text-muted">
                                        No hay registros de asistencia
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    .attendance-scroll {
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
    }
    
    /* Fijar la primera columna (Materia) */
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
    }
    
    /* Encabezado fijo visualmente */
    thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #fff;
    }
</style>

@endsection