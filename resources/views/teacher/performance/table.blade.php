@extends('layouts.app')

@section('title', 'Profesores')

@section('content')
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
                                <h4>
                                    {{ $assignment->group->name }} —
                                    {{ $assignment->subject->name }}
                                </h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teacher.performance.index') }}" class="btn btn-primary">Volver</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>% Asistencia</th>

                                    @foreach($assignment->evaluationCriteria as $criterion)
                                        <th>{{ $criterion->name }} ({{ $criterion->percentage }}%)</th>
                                    @endforeach

                                    <th>Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td>{{ $row['student'] }}</td>
                                        <td class="text-center">
                                            <a href="{{ route(
                                                'performance.detail',
                                                [$assignment, $row['student_id']]
                                            ) }}?type=attendance">
                                                {{ $row['attendance'] }}%
                                            </a>
                                        </td>

                                        @foreach($assignment->evaluationCriteria as $criterion)
                                            <td class="text-center">
                                                @if ($criterion->isAttendance())
                                                    {{ $row['criteria'][$criterion->name] ?? 0 }}
                                                @else
                                                    <a href="{{ route(
                                                        'performance.detail',
                                                        [$assignment, $row['student_id']]
                                                    ) }}?type=criterion&criterion_id={{ $criterion->id }}">
                                                        {{ $row['criteria'][$criterion->name] ?? 0 }}
                                                    </a>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="text-center font-weight-bold">
                                            {{ $row['final'] }}
                                        </td>
                                    </tr>
                                @endforeach
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
        $('#teachers').DataTable({
            dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
            order: [[0, "asc"]],
            buttons: ['excelHtml5', 'pdfHtml5'],
            language: {
                url: '/datatables.json'
            }
        });
    });
</script>
@endsection
