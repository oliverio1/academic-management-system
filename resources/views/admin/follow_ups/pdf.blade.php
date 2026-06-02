@extends('layouts.pdf')

@section('content')
<h3 class="center" style="margin-bottom: 8px;">SEGUIMIENTO DEL ALUMNO</h3>

<table class="no-border" style="margin-bottom: 10px;">
    <tr>
        <td><strong>Folio:</strong> #{{ $followUp->id }}</td>
        <td><strong>Fecha:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td><strong>Alumno:</strong> {{ $followUp->student->user->name }}</td>
        <td><strong>Grupo:</strong> {{ $followUp->student->group->name ?? 'N/D' }}</td>
    </tr>
    <tr>
        <td><strong>Solicitado por:</strong> {{ $followUp->requester->name ?? 'N/D' }}</td>
        <td><strong>Estatus:</strong> {{ $followUp->status === 'open' ? 'En proceso' : 'Cerrado' }}</td>
    </tr>
    @if($followUp->message)
        <tr>
            <td colspan="2"><strong>Contexto:</strong> {{ $followUp->message }}</td>
        </tr>
    @endif
</table>

<table>
    <thead>
        <tr>
            <th style="width: 20%;">Profesor</th>
            <th style="width: 25%;">Conductual</th>
            <th style="width: 25%;">Academico</th>
            <th style="width: 30%;">Comentarios</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>
                    {{ $row['teacher'] }}<br>
                    @if($row['answered_at'])
                        <small>Respondio: {{ $row['answered_at']->format('d/m/Y H:i') }}</small>
                    @else
                        <small>Pendiente</small>
                    @endif
                </td>
                <td>{{ $row['behavioral'] }}</td>
                <td>{{ $row['academic'] }}</td>
                <td>{{ $row['comments'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="center">No hay profesores asignados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="no-border" style="margin-top: 38px;">
    <tr>
        <td class="center" style="width: 45%;">
            ___________________________<br>
            Firma del alumno/tutor
        </td>
        <td style="width: 10%;"></td>
        <td class="center" style="width: 45%;">
            ___________________________<br>
            Coordinacion academica
        </td>
    </tr>
</table>
@endsection

