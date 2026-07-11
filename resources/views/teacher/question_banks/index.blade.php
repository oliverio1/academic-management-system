@extends('layouts.app')

@section('title', 'Bancos de preguntas')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Bancos de preguntas</h4>
            <a href="{{ route('teacher.question-banks.create') }}" class="btn btn-primary btn-sm">+ Nuevo banco</a>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Materia</th>
                        <th>Parcial</th>
                        <th>Preguntas</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($banks as $bank)
                        <tr>
                            <td>{{ $bank->name }}</td>
                            <td>{{ $bank->subject->name ?? 'N/D' }}</td>
                            <td>{{ $bank->partial->name ?? 'Sin parcial' }}</td>
                            <td>{{ $bank->questions_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('teacher.question-banks.show', $bank) }}" class="btn btn-outline-primary btn-sm">
                                    Gestionar preguntas
                                </a>
                                <a href="{{ route('teacher.question-banks.exam.configure', $bank) }}" class="btn btn-outline-success btn-sm">
                                    Configurar examen
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Sin bancos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
