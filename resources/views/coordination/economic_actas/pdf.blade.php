@extends('layouts.pdf')

@section('content')
<p class="center title">ACTA ECONOMICA DE CALIFICACIONES (DGIRE)</p>
<p class="center subtitle">Universidad Latinoamericana - Campus Valle</p>

<table class="no-border">
    <tr>
        <td><strong>Ciclo:</strong> {{ $partial->schoolCycle->name ?? '-' }}</td>
        <td><strong>Parcial:</strong> {{ $partial->name }}</td>
    </tr>
    <tr>
        <td><strong>Grupo:</strong> {{ $assignment->group->name }}</td>
        <td><strong>Materia:</strong> {{ $assignment->subject->name }}</td>
    </tr>
    <tr>
        <td><strong>Profesor:</strong> {{ $assignment->teacher->user->name }}</td>
        <td><strong>Estatus acta:</strong> {{ strtoupper($acta->status) }}</td>
    </tr>
</table>

<br>

<table>
    <thead>
        <tr class="center bold">
            <th>#</th>
            <th>Matricula</th>
            <th>Nombre del alumno</th>
            <th>Calificacion final</th>
            <th>% Asistencia</th>
            <th>Firma</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="center">{{ $row['num'] }}</td>
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
    <tr>
        <td>
            <strong>Enviada a coordinacion:</strong>
            {{ optional($acta->submittedByUser)->name ?? '-' }}
            ({{ optional($acta->submitted_at)->format('d/m/Y H:i') ?? '-' }})
        </td>
    </tr>
    <tr>
        <td>
            <strong>Borrador:</strong>
            {{ optional($acta->draftedByUser)->name ?? '-' }}
            ({{ optional($acta->drafted_at)->format('d/m/Y H:i') ?? '-' }})
        </td>
    </tr>
    <tr>
        <td>
            <strong>Cerrada:</strong>
            {{ optional($acta->closedByUser)->name ?? '-' }}
            ({{ optional($acta->closed_at)->format('d/m/Y H:i') ?? '-' }})
        </td>
    </tr>
    <tr>
        <td>
            <strong>Enviada:</strong>
            {{ optional($acta->sentByUser)->name ?? '-' }}
            ({{ optional($acta->sent_at)->format('d/m/Y H:i') ?? '-' }})
            @if($acta->sent_reference)
                - <strong>Folio:</strong> {{ $acta->sent_reference }}
            @endif
        </td>
    </tr>
</table>

@endsection
