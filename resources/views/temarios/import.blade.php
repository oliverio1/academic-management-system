@extends('layouts.app')

@section('title', 'Importar temario')

@section('content')
    <div class="content px-3">
        <div class="row">
            <div class="col-md-10 mt-3">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Importar temario</h3>
                    </div>

                    <form method="POST"
                          action="{{ route('temarios.import.store', $subject) }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Formato obligatorio:</strong> encabezado en filas 1 y 2, y temario desde fila 4.<br>
                                <code>A1</code>: nombre de la materia. <code>B1</code>: objetivo general del curso.<br>
                                <code>A2</code>: texto "Creditos". <code>B2</code>: cantidad de creditos.<br>
                                Desde la fila 4: <code>A</code> numeracion jerarquica + texto del punto.<br>
                                Para unidades (ej. <code>1.</code>, <code>2.</code>), usar <code>B</code> para objetivo especifico.<br>
                                Se aceptan archivos <code>.xlsx</code>, <code>.xls</code> o <code>.ods</code>.<br>
                                Ejemplos validos: <code>1. Unidad</code>, <code>1.1 Tema</code>, <code>1.1.1 Subtema</code>.<br>
                                Regla de niveles: un numero = unidad, dos numeros = tema, tres o mas = subtema.<br>
                                La columna <code>B</code> solo se usa para objetivo especifico de la unidad.
                            </div>

                            <p class="mb-2">
                                <a href="{{ route('temarios.template.download') }}" class="btn btn-outline-secondary btn-sm">
                                    Descargar plantilla .xlsx
                                </a>
                            </p>

                            <div class="form-group">
                                <label>Materia</label>
                                <input type="text"
                                       class="form-control"
                                       value="{{ $subject->name }}"
                                       disabled>
                            </div>

                            <div class="form-group">
                                <label for="file">Archivo</label>
                                <input id="file"
                                       type="file"
                                       name="file"
                                       class="form-control @error('file') is-invalid @enderror"
                                       accept=".xlsx,.xls,.ods"
                                       required>
                                @error('file')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Importar</button>
                            <a href="{{ route('temarios.index', $subject) }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
