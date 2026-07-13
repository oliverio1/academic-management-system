@php
    $metrics = $summary['metrics'] ?? [];
    $primaryMetrics = [
        'Filas leidas',
        'Filas importadas',
        'Filas omitidas',
        'Horarios creados/actualizados',
    ];
    $catalogMetrics = [
        'Grupos creados/reactivados',
        'Materias creadas/reactivadas',
        'Docentes creados/reactivados',
        'Grupos de ciclo creados/reactivados',
        'Asignaciones creadas/actualizadas',
    ];
@endphp

<div class="row">
    @foreach($primaryMetrics as $label)
        <div class="col-md-3 mb-3">
            <div class="small-box {{ $label === 'Filas omitidas' && ($metrics[$label] ?? 0) > 0 ? 'bg-warning' : 'bg-info' }}">
                <div class="inner">
                    <h3>{{ number_format((int) ($metrics[$label] ?? 0)) }}</h3>
                    <p>{{ $label }}</p>
                </div>
                <div class="icon">
                    <i class="fas fa-table"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Cambios detectados</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <tbody>
                @foreach($catalogMetrics as $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-right font-weight-bold">{{ number_format((int) ($metrics[$label] ?? 0)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(!empty($summary['warnings']))
    <div class="alert alert-warning">
        <strong>Advertencias:</strong> {{ count($summary['warnings']) }} filas requieren revision.
    </div>
@endif

<div class="card">
    <div class="card-header">
        <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#technicalImportOutput">
            Ver detalle tecnico
        </button>
    </div>
    <div id="technicalImportOutput" class="collapse">
        <div class="card-body">
            <pre class="bg-dark text-white p-3 rounded mb-0" style="white-space: pre-wrap;">{{ $output }}</pre>
        </div>
    </div>
</div>
