@extends('layouts.app')

@section('title', 'Nuevo estudiante')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card mb-3">
                <div class="card-header">
                    <h4 class="mb-0">Nuevo estudiante</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('students.store') }}">
                        @csrf
                        <input type="hidden" name="source" value="{{ request('source') }}">
                        <input type="hidden" name="school_cycle_id" value="{{ request('school_cycle_id') }}">

                        @include('students._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ request('source') === 'active-cycle' ? route('coordination.students.active-cycle') : route('students.index') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Alta rápida masiva</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('students.bulk-store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Grupo destino</label>
                            <select name="group_id" class="form-control js-group-select @error('group_id') is-invalid @enderror" required>
                                <option value="">Seleccione un grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('group_id')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Dominio por defecto de correo</label>
                            <input type="text" name="email_domain" class="form-control" value="{{ old('email_domain', 'my.ula.edu.mx') }}">
                            <small class="text-muted">Si una fila no trae correo, se genera como matrícula@dominio.</small>
                        </div>

                        <div class="form-group">
                            <label>Lista de alumnos</label>
                            <textarea name="bulk_rows" rows="8" class="form-control @error('bulk_rows') is-invalid @enderror" placeholder="Formato por línea: Nombre completo, Matrícula(opcional), Correo(opcional)&#10;Ejemplo: Juan Pérez López, U12345, juan@my.ula.edu.mx&#10;Ejemplo: María Torres, U22334&#10;Ejemplo: Carlos Sánchez">{{ old('bulk_rows') }}</textarea>
                            @error('bulk_rows')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <button class="btn btn-success">Crear alumnos en lote</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        if (!window.jQuery || !$.fn.select2) return;
        $('.js-group-select').select2({
            width: '100%',
            placeholder: 'Selecciona un grupo',
            theme: 'bootstrap4'
        });
    })();
</script>
<style>
    .select2-container--bootstrap4 .select2-selection--single {
        min-height: calc(2.25rem + 2px);
    }
    .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 2.25rem;
    }
    .select2-container--bootstrap4 .select2-selection__arrow {
        height: calc(2.25rem + 2px);
    }
</style>
@endsection
