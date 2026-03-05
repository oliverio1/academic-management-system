@extends('layouts.app')

@section('title', 'Boleta')

@section('content')
<div class="content px-3">

    <div class="card">
        <div class="card-header text-center">
            <h4>Boleta de Calificaciones</h4>
            <strong>{{ $student->user->name }}</strong><br>
            Grupo {{ $student->group->name }}
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Materia</th>
                        <th class="text-center">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $block)
                        <h5>
                            {{ $block['period']->name }}
                            <small class="text-muted">
                                ({{ $block['period']->start_date }} – {{ $block['period']->end_date }})
                            </small>
                        </h5>

                        <table class="table table-sm table-bordered">
                            <tbody>
                                @foreach($block['subjects'] as $item)
                                <tr>
                                    <td>{{ $item['subject'] }}</td>
                                    <td class="text-center">
                                        {{ number_format($item['average'], 1) }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="font-weight-bold">
                                    <td>Promedio del periodo</td>
                                    <td class="text-center">
                                        {{ number_format($block['general'], 1) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer text-right">
            <a href="#" class="btn btn-danger">
                Descargar PDF
            </a>
        </div>
    </div>

</div>
@endsection
