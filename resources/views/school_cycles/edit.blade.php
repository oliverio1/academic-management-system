@extends('layouts.app')

@section('title', 'Editar ciclo escolar')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Editar ciclo escolar</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('school-cycles.update', $schoolCycle) }}">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            @include('school_cycles._form')
                        </div>
                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('school-cycles.index') }}" class="btn btn-secondary">Cancelar</a>
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

        $('.js-campus-select').select2({
            width: '100%',
            placeholder: 'Selecciona uno o más campus',
            allowClear: true,
            closeOnSelect: false
        });

        $('.js-modality-select').select2({
            width: '100%',
            placeholder: 'Selecciona modalidades',
            closeOnSelect: false
        });
    })();
</script>
@endsection
