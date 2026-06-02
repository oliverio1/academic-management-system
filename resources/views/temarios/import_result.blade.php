@extends('layouts.app')

@section('title', 'Resultado importacion de temario')

@section('content')
    <div class="content px-3">
        <div class="row">
            <div class="col-md-10 mt-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Resultado de importacion</h3>
                        <a href="{{ route('temarios.import.form', $subject) }}" class="btn btn-outline-primary btn-sm">
                            Nueva importacion
                        </a>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>Registros creados: <strong>{{ $result->created }}</strong></li>
                            <li>Registros actualizados: <strong>{{ $result->updated }}</strong></li>
                            <li>Registros omitidos: <strong>{{ $result->skipped }}</strong></li>
                        </ul>

                        @if (!empty($result->warnings))
                            <div class="alert alert-warning">
                                <h5 class="mb-2">Advertencias</h5>
                                <ul class="mb-0">
                                    @foreach ($result->warnings as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($result->errors))
                            <div class="alert alert-danger">
                                <h5 class="mb-2">Errores</h5>
                                <ul class="mb-0">
                                    @foreach ($result->errors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
