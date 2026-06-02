@if(request('tab') === 'activities')

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            Actividades del ciclo (primer y segundo parcial)
            @if(!empty($activeCycle))
                <small class="text-muted">({{ $activeCycle->name }})</small>
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
            No hay actividades registradas para primer y segundo parcial en este ciclo.
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
                                {{ $activity->graded_count }} de {{ $totalStudents }} / Individual
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('activities.grade', $activity) }}"
                            class="btn btn-success btn-sm">
                                Calificar
                            </a>
                            <form method="POST"
                                  action="{{ route('activities.destroy', $activity) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Se eliminara esta actividad. Deseas continuar?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    @endif

@endif
