@extends('layouts.app')

@section('title', 'Días feriados y vacaciones')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="alert">
        <strong>{{ session('success') }}</strong>
    </div>
@endif

@if(session('info'))
    <div class="alert alert-primary" role="alert">
        <strong>{{ session('info') }}</strong>
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
                            <h4>Días feriados y vacaciones</h4>
                        </div>
                        <div class="col-sm-6 text-right">
                            <a href="{{ route('academic-calendar-days.create') }}" class="btn btn-primary">
                                Nuevo día feriado
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <table id="calendar-days" class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Nombre</th>
                                <th>Modalidad</th>
                                <th>Afecta docentes</th>
                                <th>Afecta alumnos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($days as $day)
                                <tr>
                                    <td>{{ $day->date->format('d/m/Y') }}</td>
                                    <td>{{ ucfirst($day->type) }}</td>
                                    <td>{{ $day->name }}</td>
                                    <td>{{ $day->modality?->name ?? 'Global' }}</td>
                                    <td>{{ $day->affects_teachers ? 'Sí' : 'No' }}</td>
                                    <td>{{ $day->affects_students ? 'Sí' : 'No' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('academic-calendar-days.destroy', $day) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este día?')">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No hay días registrados.</td>
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

@section('page_scripts')
<script>
    $(document).ready(function () {
        $('#calendar-days').DataTable({
            dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
            order: [[0, "asc"]],
            buttons: ['excelHtml5', 'pdfHtml5'],
            language: { url: '/datatables.json' }
        });
    });
</script>
@endsection
