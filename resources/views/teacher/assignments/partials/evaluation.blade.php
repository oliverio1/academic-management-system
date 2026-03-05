@if(request('tab', 'evaluation') === 'evaluation')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Criterios de evaluación</h5>

        @if(!$teachingAssignment->hasGrades() && $teachingAssignment->evaluationCriteria->isNotEmpty())
            <a href="{{ route('teacher.evaluation.edit', $teachingAssignment) }}"
            class="btn btn-sm btn-outline-primary">
                ✏️ Editar
            </a>
        @endif
    </div>
    @if($teachingAssignment->evaluationCriteria->isEmpty())
        <div class="alert alert-warning">
            ⚠️ Aún no has configurado los criterios de evaluación para este grupo.
        </div>
        @if(!$teachingAssignment->hasGrades())
            <a href="{{ route('teacher.evaluation.create', $teachingAssignment) }}"
            class="btn btn-primary">
                Configurar evaluación
            </a>
        @endif

    @else

        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Tipo de actividad</th>
                    <th class="text-end">Porcentaje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($teachingAssignment->evaluationCriteria as $criterion)
                    <tr>
                        <td>{{ $criterion->name }}</td>
                        <td class="text-end">{{ $criterion->percentage }} %</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Total</td>
                    <td class="text-end">
                        {{ $teachingAssignment->evaluationCriteria->sum('percentage') }} %
                    </td>
                </tr>
            </tfoot>
        </table>

        @if($teachingAssignment->evaluationCriteria->sum('percentage') !== 100)
            <div class="alert alert-danger mt-2">
                ❌ La suma de los porcentajes debe ser 100 %.
            </div>
        @endif

        @if(
            $teachingAssignment->evaluationCriteria->isEmpty() &&
            $teacherAssignments->where('evaluation_criteria_count', '>', 0)->isNotEmpty() &&
            !$teachingAssignment->hasGrades()
        )
            <hr>
            <h6>Clonar criterios desde otra asignación</h6>

            <form method="POST"
                action="{{ route('evaluation-criteria.clone', $teachingAssignment) }}"
                class="d-flex gap-2 align-items-end">
                @csrf

                <select name="source_assignment_id" class="form-select" required>
                    <option value="">Selecciona una asignación</option>
                    @foreach($teacherAssignments as $other)
                        @if($other->evaluation_criteria_count > 0)
                            <option value="{{ $other->id }}">
                                {{ $other->subject->name }} — Grupo {{ $other->group->name }}
                            </option>
                        @endif
                    @endforeach
                </select>

                <button class="btn btn-outline-primary">
                    Clonar
                </button>
            </form>
        @endif

    @endif

@endif