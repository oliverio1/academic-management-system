@extends('layouts.app')

@section('title', 'Cartera vencida')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Cartera vencida</h4>
                </div>
                <div class="card-body table-responsive p-3">
                    <table data-datatable="true" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th class="text-right">Saldo vencido</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row->student_name }}</td>
                                    <td>{{ $row->group_name ?? '-' }}</td>
                                    <td class="text-right">${{ number_format((float) $row->overdue_balance, 2) }}</td>
                                    <td class="text-center">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('coordination.finance.statements.show', $row->student_id) }}">Ver estado</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin adeudos vencidos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

