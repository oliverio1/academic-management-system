@extends('layouts.app')

@section('title', 'Ponderaciones')

@section('content')
<div class="content px-3">
    <div class="card">

        <div class="card-header">
            <h4>
                Ponderaciones —
                {{ $assignment->subject->name }}
                ({{ $assignment->group->name }})
            </h4>
        </div>

        <div class="card-body">

            @if($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST"
                  action="{{ route('weights.update', [$assignment->group, $assignment]) }}">
                @csrf

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tipo de actividad</th>
                            <th>Ponderación (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activityTypes as $key => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td>
                                    <input type="number"
                                           name="weights[{{ $key }}]"
                                           class="form-control"
                                           min="0"
                                           max="100"
                                           step="1"
                                           value="{{ old('weights.'.$key, $weights[$key] ?? 0) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('groups.assignments.edit', $assignment->group) }}"
                   class="btn btn-secondary">Cancelar</a>
            </form>

        </div>
    </div>
</div>
@endsection
