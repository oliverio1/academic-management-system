<form id="individual-grades-form" method="POST" action="{{ route('activities.grade.store', $activity) }}">
    @csrf

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Calificación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activity->teachingAssignment->group->students as $student)
                <tr>
                    <td>{{ $student->user->name }}</td>
                    <td>
                        <input type="number"
                               name="grades[{{ $student->id }}]"
                               class="form-control"
                               min="0"
                               max="10"
                               step="0.1"
                               value="{{ $student->gradeForActivity($activity)?->score }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <button type="submit" class="btn btn-primary">Guardar calificaciones</button>
</form>