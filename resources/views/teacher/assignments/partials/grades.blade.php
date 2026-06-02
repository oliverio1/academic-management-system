@if(request('tab') === 'grades')
<h4>
    <a href="{{ route('actas.calificaciones', [$teachingAssignment->id]) }}" class="btn btn-sm btn-outline-danger">Actas</a>
</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Base</th>
            <th>Promedio final</th>
            <th>Resultado</th>
            <th>Ordinario A</th>
            <th>Ordinario B</th>
            <th>Extraordinario</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($gradesData as $row)
            @php
                $remedial = $row['remedial'];
                $formId = 'remedial-'.$row['student']->id;
                $promotion = $row['promotion'];
                $isPassed = (bool) ($promotion['passed'] ?? false);
                $labelClass = $isPassed ? 'success' : 'danger';
                $route = (string) ($promotion['route'] ?? '');
                if (str_contains($route, 'pending') || $route === 'without_grades') {
                    $labelClass = 'warning';
                } elseif ($route === 'without_period') {
                    $labelClass = 'secondary';
                }

                $isFinalLocked = in_array($route, ['ordinario_a_final', 'ordinario_b_final', 'extraordinario_final'], true);
                $allowOrdA = ! $isFinalLocked && in_array($route, ['pending_ordinario_a', 'base_direct'], true);
                $allowOrdB = ! $isFinalLocked && $route === 'pending_ordinario_b';
                $allowExtra = ! $isFinalLocked && in_array($route, ['pending_ordinario_b', 'ordinario_b_final', 'pending_ordinario_a', 'base_direct'], true);
            @endphp
            <tr>
                <td>{{ $row['student']->user->name }}</td>
                <td>{{ $row['promotion']['base_final'] }}</td>
                <td>{{ $row['final'] }}</td>
                <td>
                    <span class="badge badge-{{ $labelClass }}">
                        {{ $promotion['label'] ?? ($isPassed ? 'Acreditado' : 'En riesgo') }}
                    </span>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" max="10" name="ordinario_a_score" class="form-control form-control-sm"
                        value="{{ optional($remedial)->ordinario_a_score }}" style="min-width:90px;" form="{{ $formId }}"
                        {{ $allowOrdA ? '' : 'disabled' }}>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" max="10" name="ordinario_b_score" class="form-control form-control-sm"
                        value="{{ optional($remedial)->ordinario_b_score }}" style="min-width:90px;" form="{{ $formId }}"
                        {{ $allowOrdB ? '' : 'disabled' }}>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" max="10" name="extraordinario_score" class="form-control form-control-sm"
                        value="{{ optional($remedial)->extraordinario_score }}" style="min-width:100px;" form="{{ $formId }}"
                        {{ $allowExtra ? '' : 'disabled' }}>
                </td>
                <td class="text-end">
                    <form id="{{ $formId }}" method="POST" action="{{ route('assignments.remedial.store', [$teachingAssignment->id, $row['student']->id]) }}" class="d-none">
                        @csrf
                    </form>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-success" type="submit" form="{{ $formId }}"
                            {{ $isFinalLocked ? 'disabled' : '' }}>Guardar</button>
                        <button class="btn btn-outline-primary"
                                type="button"
                                data-toggle="collapse"
                                data-target="#detail-{{ $row['student']->id }}"
                                data-bs-toggle="collapse"
                                data-bs-target="#detail-{{ $row['student']->id }}"
                                aria-expanded="false"
                                aria-controls="detail-{{ $row['student']->id }}">
                            Ver detalle
                        </button>
                        <a href="{{ route('boletas.pdf', [$teachingAssignment->id, $row['student']->id]) }}" class="btn btn-sm btn-outline-danger">PDF</a>
                    </div>
                </td>
            </tr>

            <tr class="collapse" id="detail-{{ $row['student']->id }}">
                <td colspan="8">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Criterio</th>
                                <th>%</th>
                                <th>Calificacion</th>
                                <th>Aporta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($row['breakdown']['rows'] as $b)
                                <tr>
                                    <td>{{ $b['criterion'] }}</td>
                                    <td>{{ $b['percentage'] }}%</td>
                                    <td>{{ $b['average'] }}</td>
                                    <td>{{ $b['contribution'] }}</td>
                                </tr>
                            @endforeach
                            @if (empty($row['breakdown']['rows']))
                                <tr>
                                    <td colspan="4" class="text-muted text-center">
                                        No hay rubros configurados para desglosar.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Final</td>
                                <td>{{ $row['breakdown']['final'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
