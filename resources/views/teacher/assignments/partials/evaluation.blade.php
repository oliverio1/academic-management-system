@if(request('tab', 'evaluation') === 'evaluation')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Criterios de evaluación por parcial</h5>
        <a href="{{ route('teacher.classes.evaluation.index', $teachingAssignment) }}"
           class="btn btn-sm btn-outline-primary">
            Configurar rubros
        </a>
    </div>

    @php
        $hasAnyCriteria = isset($evaluationCriteriaByPartial)
            && $evaluationCriteriaByPartial->contains(fn ($row) => $row['criteria']->isNotEmpty());
    @endphp

    @if(! $hasAnyCriteria)
        <div class="alert alert-warning">
            Aún no has configurado rubros en los parciales de esta materia.
        </div>
    @endif

    @foreach(($evaluationCriteriaByPartial ?? collect()) as $row)
        @php
            /** @var \App\Models\CyclePartial|null $partial */
            $partial = $row['partial'];
            $criteria = $row['criteria'];
            $total = (float) $row['total'];
        @endphp

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $partial?->name ?? 'Rubros sin parcial' }}</strong>
                @if($partial && $partial->academicPeriod)
                    <small class="text-muted">
                        {{ $partial->academicPeriod->start_date?->format('d/m/Y') }}
                        -
                        {{ $partial->academicPeriod->end_date?->format('d/m/Y') }}
                    </small>
                @endif
            </div>
            <div class="card-body p-0">
                @if($criteria->isEmpty())
                    <div class="p-3 text-muted">
                        Sin rubros configurados para este parcial.
                    </div>
                @else
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Tipo de actividad</th>
                                <th class="text-end">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($criteria as $criterion)
                                <tr>
                                    <td>{{ $criterion->name }}</td>
                                    <td class="text-end">{{ number_format((float) $criterion->percentage, 2) }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end">{{ number_format($total, 2) }} %</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
            @if($criteria->isNotEmpty() && $total !== 100.0)
                <div class="card-footer">
                    <div class="alert alert-danger mb-0">
                        La suma de los porcentajes en este parcial debe ser 100 %.
                    </div>
                </div>
            @endif
        </div>
    @endforeach
@endif

