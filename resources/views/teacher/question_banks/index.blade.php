@extends('layouts.app')

@section('title', 'Bancos de preguntas')

@section('content')
<div class="content px-3 mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Bancos de preguntas</h4>
                @if($activeCycle)
                    <small class="text-muted">{{ $activeCycle->name }}</small>
                @endif
            </div>
            <a href="{{ route('teacher.question-banks.create', $selectedPartialId ? ['cycle_partial_id' => $selectedPartialId] : []) }}" class="btn btn-primary btn-sm">+ Nuevo banco</a>
        </div>
        <div class="card-body">
            @if(session('info'))
                <div class="alert alert-success">{{ session('info') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="GET" action="{{ route('teacher.question-banks.index') }}" class="form-inline mb-3">
                <label class="mr-2">Parcial</label>
                <select name="cycle_partial_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">Todos los parciales</option>
                    @foreach($partials as $partial)
                        <option value="{{ $partial->id }}" @selected((int) $selectedPartialId === (int) $partial->id)>
                            {{ $partial->name }}
                        </option>
                    @endforeach
                </select>
                @if($selectedPartialId)
                    <a href="{{ route('teacher.question-banks.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                @endif
            </form>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Materia</th>
                        <th>Ciclo</th>
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
                            <td>{{ $bank->schoolCycle->name ?? 'Sin ciclo' }}</td>
                            <td>{{ $bank->partial->name ?? 'Sin parcial' }}</td>
                            <td>{{ $bank->questions_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('teacher.question-banks.show', $bank) }}" class="btn btn-outline-primary btn-sm">
                                    Gestionar preguntas
                                </a>
                                <a href="{{ route('teacher.question-banks.exam.configure', $bank) }}" class="btn btn-outline-success btn-sm">
                                    Configurar examen
                                </a>
                                <a href="{{ route('teacher.question-banks.edit', $bank) }}" class="btn btn-outline-secondary btn-sm">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('teacher.question-banks.destroy', $bank) }}" class="d-inline-block" onsubmit="return confirm('¿Eliminar este banco de preguntas?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Sin bancos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
