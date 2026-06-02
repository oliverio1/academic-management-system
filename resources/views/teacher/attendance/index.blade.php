@extends('layouts.app')

@section('title', 'Mi asistencia')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Mi asistencia al plantel</h4>
                </div>
                <div class="card-body">
                    @if(session('info'))
                        <div class="alert alert-primary">{{ session('info') }}</div>
                    @endif
                    @if(session('warning'))
                        <div class="alert alert-warning">{{ session('warning') }}</div>
                    @endif
                    <div id="geo-warning" class="alert alert-warning d-none"></div>

                    <div class="mb-3">
                        <strong>Hoy:</strong> {{ now()->format('d/m/Y') }}<br>
                        <strong>Primera clase:</strong>
                        {{ $firstClassStart ? $firstClassStart->format('H:i') : 'Sin clase programada' }}
                    </div>

                    <form method="POST" action="{{ route('teacher.attendance.clock') }}" class="mb-4" id="teacher-attendance-clock-form">
                        @csrf
                        <input type="hidden" name="latitude" id="geo_latitude">
                        <input type="hidden" name="longitude" id="geo_longitude">
                        <button class="btn btn-primary" type="submit">
                            {{ ! $todayRecord?->check_in_time ? 'Registrar entrada' : (! $todayRecord?->check_out_time ? 'Registrar salida' : 'Registro completo') }}
                        </button>
                    </form>

                    <div class="table-responsive">
                        <table data-datatable="true" class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Campus</th>
                                    <th>Primera clase</th>
                                    <th>Entrada</th>
                                    <th>Ubicación entrada</th>
                                    <th>Salida</th>
                                    <th>Ubicación salida</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($history as $row)
                                    <tr>
                                        <td>{{ $row->attendance_date?->format('d/m/Y') }}</td>
                                        <td>{{ $row->campus->name ?? '-' }}</td>
                                        <td>{{ $row->first_class_start_time ? \Carbon\Carbon::parse($row->first_class_start_time)->format('H:i') : '-' }}</td>
                                        <td>{{ $row->check_in_time ? \Carbon\Carbon::parse($row->check_in_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @if($row->check_in_latitude && $row->check_in_longitude)
                                                {{ $row->check_in_latitude }}, {{ $row->check_in_longitude }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $row->check_out_time ? \Carbon\Carbon::parse($row->check_out_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @if($row->check_out_latitude && $row->check_out_longitude)
                                                {{ $row->check_out_latitude }}, {{ $row->check_out_longitude }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $row->status }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Sin registros todavía.</td>
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

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('teacher-attendance-clock-form');
    if (!form) {
        return;
    }

    const lat = document.getElementById('geo_latitude');
    const lon = document.getElementById('geo_longitude');
    const geoWarning = document.getElementById('geo-warning');

    const showGeoWarning = function (message) {
        if (!geoWarning) return;
        geoWarning.textContent = message;
        geoWarning.classList.remove('d-none');
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!navigator.geolocation) {
            showGeoWarning('Tu navegador no soporta geolocalización. El registro se guardará sin ubicación.');
            form.submit();
            return;
        }

        navigator.geolocation.getCurrentPosition(function (position) {
            if (lat && lon) {
                lat.value = String(position.coords.latitude);
                lon.value = String(position.coords.longitude);
            }
            form.submit();
        }, function () {
            showGeoWarning('No fue posible obtener tu ubicación. Revisa permisos del navegador para este sitio.');
            form.submit();
        }, {
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 0,
        });
    });
});
</script>
@endsection
