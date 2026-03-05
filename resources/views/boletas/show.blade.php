@extends('layouts.pdf')

@section('content')

<p class="center bold">
    BOLETA DE CALIFICACIONES
</p>

<table class="no-border">
    <tr>
        <td><strong>Matrícula:</strong> {{ $student->enrollment_number }}</td>
        <td><strong>Alumno:</strong> {{ $student->user->name }}</td>
    </tr>
    <tr>
        <td><strong>Grupo:</strong> {{ $teachingAssignment->group->name }}</td>
        <td><strong>Materia:</strong> {{ $teachingAssignment->subject->name }}</td>
    </tr>
</table>

<br>

<table>
    <thead>
        <tr class="center bold">
            <th>Criterio</th>
            <th>%</th>
            <th>Calificación</th>
            <th>Aporta</th>
        </tr>
    </thead>
    <tbody>
        @foreach($breakdown['rows'] as $row)
            <tr>
                <td>{{ $row['criterion'] }}</td>
                <td class="center">{{ $row['percentage'] }}%</td>
                <td class="center">{{ $row['average'] }}</td>
                <td class="center">{{ $row['contribution'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="bold">
            <td colspan="3" class="right">Calificación final</td>
            <td class="center">{{ $breakdown['final'] }}</td>
        </tr>
    </tfoot>
</table>

@endsection
