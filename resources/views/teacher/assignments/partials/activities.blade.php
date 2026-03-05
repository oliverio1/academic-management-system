@if(request('tab') === 'activities')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            Actividades del periodo
            @if($activePeriod)
                <small class="text-muted">({{ $activePeriod->name }})</small>
            @endif
        </h5>

        <a href="{{ route('activities.create', $teachingAssignment) }}"
           class="btn btn-sm btn-primary">
            + Nueva actividad
        </a>
    </div>

    @if(!$activePeriod)
        <div class="alert alert-warning">
            ⚠️ No hay un periodo activo configurado.
        </div>
    @elseif($activities->isEmpty())
        <div class="alert alert-info">
            No hay actividades registradas en este periodo.
        </div>
    @else

        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Criterio</th>
                    <th>Máx.</th>
                    <th>Fecha</th>
                    <th>Calificados</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activities as $activity)
                    <tr>
                        <td>{{ $activity->title }}</td>
                        <td>
                            <span class="badge bg-secondary">
                                {{ $activity->evaluationCriterion->name }}
                            </span>
                        </td>
                        <td>{{ $activity->max_score }}</td>
                        <td>{{ optional($activity->due_date)->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge {{ $activity->graded_count == $totalStudents ? 'bg-success' : 'bg-warning' }}">
                                {{ $activity->graded_count }} de {{ $totalStudents }} / {{ $activity->evaluation_mode === 'team' ? 'Equipo' : 'Individual' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('activities.grade', $activity) }}"
                            class="btn btn-success btn-sm">
                                Calificar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    @endif

@endif