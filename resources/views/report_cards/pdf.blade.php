<h3 style="text-align:center">Boleta de Calificaciones</h3>

<p><strong>Alumno:</strong> {{ $student->user->name }}</p>
<p><strong>Grupo:</strong> {{ $group->name }}</p>

<table width="100%" border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr>
            <th>Materia</th>
            <th>Promedio</th>
        </tr>
    </thead>
    <tbody>
        @foreach($subjects as $subject)
            <tr>
                <td>{{ $subject->name }}</td>
                <td>{{ $subjectAverages[$subject->id] ?? '—' }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Promedio General</th>
            <th>{{ $generalAverage ?? '—' }}</th>
        </tr>
    </tbody>
</table>
