@extends('layouts.app')

@section('content')
<form method="POST" action="{{ route('imports.grades.store') }}"
      enctype="multipart/form-data">
    @csrf

    <label>Periodo académico</label>
    <select name="academic_period_id" required>
        @foreach($periods as $period)
            <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->modality->name }})</option>
        @endforeach
    </select>

    <label>Archivo Excel</label>
    <input type="file" name="file" required>

    <button type="submit">Importar calificaciones</button>
</form>
@endsection
