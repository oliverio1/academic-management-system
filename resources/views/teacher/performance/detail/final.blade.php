@extends('layouts.app')

@section('title', 'Desglose final')

@section('content')
<div class="card">
    <div class="card-header">
        <h4>{{ $student->user->name }} — Desglose final</h4>
    </div>

    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Criterio</th>
                    <th>%</th>
                    <th>Promedio</th>
                    <th>Aporte</th>
                </tr>
            </thead>
            <tbody>
                @foreach($breakdown['rows'] as $row)
                    <tr>
                        <td>{{ $row['criterion'] }}</td>
                        <td>{{ $row['percentage'] }}%</td>
                        <td>{{ $row['average'] }}</td>
                        <td>{{ $row['contribution'] }}</td>
                    </tr>
                @endforeach
                <tr class="font-weight-bold">
                    <td colspan="3">Final</td>
                    <td>{{ $breakdown['final'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
