@if($grades->isEmpty())
    <p class="text-muted">No hay calificaciones registradas.</p>
@else
<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Materia</th>
            <th>Promedio</th>
        </tr>
    </thead>
    <tbody>
        @foreach($grades as $row)
            <tr>
                <td>{{ $row->subject_name }}</td>
                <td>
                    <span class="badge badge-{{ $row->average < 70 ? 'danger' : 'success' }}">
                        {{ number_format($row->average, 1) }}
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
