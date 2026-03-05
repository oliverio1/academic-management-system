@if($attendance->isEmpty())
    <p class="text-muted">No hay registros de asistencia.</p>
@else
<table class="table table-sm table-bordered">
    <thead>
        <tr>
            <th>Materia</th>
            <th>Asistencias</th>
            <th>Total</th>
            <th>%</th>
        </tr>
    </thead>
    <tbody>
        @foreach($attendance as $row)
            @php
                $percentage = $row->total > 0
                    ? round(($row->presents / $row->total) * 100)
                    : 0;
            @endphp
            <tr>
                <td>{{ $row->subject_name }}</td>
                <td>{{ $row->presents }}</td>
                <td>{{ $row->total }}</td>
                <td>
                    <span class="badge badge-{{ $percentage < 80 ? 'danger' : 'success' }}">
                        {{ $percentage }}%
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif