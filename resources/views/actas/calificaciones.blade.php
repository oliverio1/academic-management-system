@extends('layouts.pdf')

@section('content')

<p class="center title">ACTA GENERAL DE CALIFICACIONES</p>
<p class="center subtitle">Universidad Latinoamericana - Campus Valle</p>

<table class="no-border">
    <tr>
        <td><strong>Grupo:</strong> {{ $teachingAssignment->group->name }}</td>
        <td><strong>Materia:</strong> {{ $teachingAssignment->subject->name }}</td>
    </tr>
    <tr>
        <td><strong>Docente:</strong> {{ $teachingAssignment->teacher->user->name }}</td>
        <td><strong>Periodo:</strong> {{ optional($activePeriod)->name }}</td>
    </tr>
</table>

<br>

<table>
    <thead>
        <tr class="center bold">
            <th>Matricula</th>
            <th>Nombre del alumno</th>
            <th>Calificacion final</th>
            <th>% Asistencia</th>
            <th>Firma de aceptado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="center">{{ $row['enrollment'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="center">{{ $row['grade'] !== null ? number_format((float) $row['grade'], 1) : '-' }}</td>
                <td class="center">{{ number_format((float) $row['attendance'], 0) }}%</td>
                <td class="signature-line"></td>
            </tr>
        @endforeach
    </tbody>
</table>

<br><br>

<table class="no-border">
    <tr class="center">
        <td>
            ___________________________<br>
            DOCENTE
        </td>
        <td>
            ___________________________<br>
            COORDINADOR
        </td>
    </tr>
</table>

@endsection
