@if(request('tab') === 'grades')
<h4>
    <a href="{{ route('actas.calificaciones', [$teachingAssignment->id]) }}"class="btn btn-sm btn-outline-danger">Actas</a>
</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Promedio</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($gradesData as $row)
            <tr>
                <td>{{ $row['student']->user->name }}</td>
                <td>{{ $row['final'] }}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary"
                                type="button"
                                data-toggle="collapse"
                                data-target="#detail-{{ $row['student']->id }}">
                            Ver detalle
                        </button>
                        <a href="{{ route('boletas.pdf', [$teachingAssignment->id, $row['student']->id]) }}"class="btn btn-sm btn-outline-danger">PDF</a>
                    </div>
                </td>
            </tr>

            <tr class="collapse" id="detail-{{ $row['student']->id }}">
                <td colspan="4">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Criterio</th>
                                <th>%</th>
                                <th>Calificación</th>
                                <th>Aporta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($row['breakdown']['rows'] as $b)
                                <tr>
                                    <td>{{ $b['criterion'] }}</td>
                                    <td>{{ $b['percentage'] }}%</td>
                                    <td>{{ $b['average'] }}</td>
                                    <td>{{ $b['contribution'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Final</td>
                                <td>{{ $row['breakdown']['final'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif