@extends('layouts.pdf')

@section('content')

{{-- ENCABEZADO --}}
<p class="center title">ACTA DE CALIFICACIONES</p>
<p class="center subtitle">Universidad Latinoamericana – Campus Valle</p>

<table class="no-border">
    <tr>
        <td><strong>Grupo:</strong> {{ $teachingAssignment->group->name }}</td>
        <td><strong>Materia:</strong> {{ $teachingAssignment->subject->name }}</td>
    </tr>
    <tr>
        <td><strong>Docente:</strong> {{ $teachingAssignment->teacher->user->name }}</td>
        <td><strong>Periodo:</strong> {{ optional($teachingAssignment->activePeriod)->name }}</td>
    </tr>
</table>

<br>

{{-- TABLA PRINCIPAL --}}
<table>
    <thead>
        <tr class="center bold">
            <th>No.</th>
            <th>Matrícula</th>
            <th>Nombre del alumno</th>
            <th>Calificación final</th>
            <th>% Asistencia</th>
            <th>Firma / Recibí</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="center">{{ $row['num'] }}</td>
                <td class="center">{{ $row['enrollment'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="center">{{ $row['grade'] }}</td>
                <td class="center">{{ $row['attendance'] }}%</td>
                <td class="signature-line"></td>
            </tr>
        @endforeach
    </tbody>
</table>

<br><br>

{{-- FIRMAS --}}
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
