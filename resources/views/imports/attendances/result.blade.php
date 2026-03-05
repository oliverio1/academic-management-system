@extends('layouts.app')

@section('title', 'Resultado importación')

@section('content')
<div class="container-fluid">
    <h3>Resultado de la importación</h3>

    <ul>
        <li>Registros creados: {{ $result->created }}</li>
        <li>Registros actualizados: {{ $result->updated }}</li>
        <li>Registros omitidos: {{ $result->skipped }}</li>
    </ul>

    @if ($result->errors)
        <div class="alert alert-warning">
            <h5>Errores</h5>
            <ul>
                @foreach ($result->errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
