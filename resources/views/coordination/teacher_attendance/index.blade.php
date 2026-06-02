@extends('layouts.app')

@section('title', 'Asistencia docente')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Asistencia de docentes</h4>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-primary">{{ session('info') }}</div>
                    @endif
                    <form method="GET" class="form-row mb-3">
                        <div class="col-md-4 mb-2">
                            <label>Campus</label>
                            <select name="campus_id" class="form-control" onchange="this.form.submit()">
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}" {{ (int) $campusId === (int) $campus->id ? 'selected' : '' }}>
                                        {{ $campus->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Fecha</label>
                            <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3 mb-2 d-flex align-items-end">
                            <a href="{{ route('coordination.teacher-attendance.export', ['campus_id' => $campusId, 'date' => $date]) }}" class="btn btn-outline-success">
                                Exportar CSV
                            </a>
                        </div>
                    </form>

                    <div class="mb-3">
                        <span class="badge badge-success">Puntual: {{ $summary['on_time'] }}</span>
                        <span class="badge badge-warning">Retardo: {{ $summary['late'] }}</span>
                        <span class="badge badge-danger">Falta: {{ $summary['absent'] }}</span>
                        <span class="badge badge-secondary">Pendiente: {{ $summary['pending'] }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Docente</th>
                                    <th>Primera clase</th>
                                    <th>Entrada</th>
                                    <th>Ubicación entrada</th>
                                    <th>Salida</th>
                                    <th>Ubicación salida</th>
                                    <th>Estatus</th>
                                    <th>Captura manual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ $row['teacher']->user->name ?? '-' }}</td>
                                        <td>{{ $row['first_class_start'] ? $row['first_class_start']->format('H:i') : '-' }}</td>
                                        <td>{{ $row['record']?->check_in_time ? \Carbon\Carbon::parse($row['record']->check_in_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @if($row['record']?->check_in_latitude && $row['record']?->check_in_longitude)
                                                {{ $row['record']->check_in_latitude }}, {{ $row['record']->check_in_longitude }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $row['record']?->check_out_time ? \Carbon\Carbon::parse($row['record']->check_out_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @if($row['record']?->check_out_latitude && $row['record']?->check_out_longitude)
                                                {{ $row['record']->check_out_latitude }}, {{ $row['record']->check_out_longitude }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $row['status'] }}</td>
                                        <td style="min-width:320px;">
                                            <form method="POST" action="{{ route('coordination.teacher-attendance.manual') }}" class="form-row">
                                                @csrf
                                                <input type="hidden" name="teacher_id" value="{{ $row['teacher']->id }}">
                                                <input type="hidden" name="campus_id" value="{{ $campusId }}">
                                                <input type="hidden" name="attendance_date" value="{{ $date }}">
                                                <div class="col-4 pr-1">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach(['pending' => 'Pendiente', 'on_time' => 'Puntual', 'late' => 'Retardo', 'absent' => 'Falta'] as $statusKey => $statusLabel)
                                                            <option value="{{ $statusKey }}" {{ $row['status'] === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-3 px-1">
                                                    <input type="time" name="check_in_time" class="form-control form-control-sm" value="{{ $row['record']?->check_in_time ? \Carbon\Carbon::parse($row['record']->check_in_time)->format('H:i') : '' }}">
                                                </div>
                                                <div class="col-3 px-1">
                                                    <input type="time" name="check_out_time" class="form-control form-control-sm" value="{{ $row['record']?->check_out_time ? \Carbon\Carbon::parse($row['record']->check_out_time)->format('H:i') : '' }}">
                                                </div>
                                                <div class="col-2 pl-1">
                                                    <button class="btn btn-sm btn-outline-primary w-100" type="submit">Guardar</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No hay docentes para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
