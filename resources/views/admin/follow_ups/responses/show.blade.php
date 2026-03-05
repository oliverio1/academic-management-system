@extends('layouts.app')

@section('title', 'Respuesta de seguimiento')

@section('content')

<h4>
    Respuesta del profesor
</h4>

<p class="text-muted">
    <strong>Alumno:</strong>
    {{ $assignment->followUp->student->user->name }} <br>

    <strong>Profesor:</strong>
    {{ $assignment->teacher->user->name }}
</p>

<hr>

<p>
    <strong>Desempeño académico</strong><br>
    {{ $assignment->response->questionnaire['academic_performance'] }}
</p>

<p>
    <strong>Desempeño conductual</strong><br>
    {{ $assignment->response->questionnaire['behavioral_performance'] }}
</p>

@endsection
