@extends('layouts.app')

@section('content')
<h3>Resultado de importación</h3>

<ul>
    <li>Actividades creadas: {{ $result->created }}</li>
    <li>Calificaciones guardadas: {{ $result->updated }}</li>
</ul>

@if ($result->warnings)
    <h4>Advertencias</h4>
    <ul>
        @foreach ($result->warnings as $w)
            <li>{{ $w }}</li>
        @endforeach
    </ul>
@endif

@if ($result->errors)
    <h4>Errores</h4>
    <ul>
        @foreach ($result->errors as $e)
            <li>{{ $e }}</li>
        @endforeach
    </ul>
@endif
@endsection
