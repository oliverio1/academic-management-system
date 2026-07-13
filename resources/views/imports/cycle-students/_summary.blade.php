@php
    $metrics = $summary['metrics'] ?? [];
    $primaryMetrics = [
        'Filas leidas',
        'Filas validas',
        'Filas omitidas',
        'Alumnos creados',
        'Alumnos actualizados',
        'Tutores creados',
        'Tutores vinculados',
        'Vinculos de seccion',
        'Alumnos inactivados',
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
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if(!empty($summary['warnings']))
    <div class="card card-warning">
        <div class="card-header">
            <h3 class="card-title">Filas con advertencias</h3>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                @foreach(array_slice($summary['warnings'], 0, 60) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>

            @if(count($summary['warnings']) > 60)
                <div class="text-muted mt-2">
                    Y {{ count($summary['warnings']) - 60 }} advertencias mas.
                </div>
            @endif
        </div>
    </div>
@endif
