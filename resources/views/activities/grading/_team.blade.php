<form id="team-grades-form" method="POST" action="{{ route('activities.grade.store', $activity) }}">
    @csrf

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Equipo</th>
                <th>Integrantes</th>
                <th>Calificación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activity->teachingAssignment->teams as $team)
                <tr>
                    <td>{{ $team->name }}</td>
                    <td>
                        <ul class="mb-0">
                            @foreach ($team->students as $student)
                                <li>{{ $student->user->name }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <input type="number"
                               name="team_grades[{{ $team->id }}]"
                               class="form-control"
                               min="0"
                               max="10"
                               step="0.1"
                               value="{{ $teamScores[$team->id] ?? '' }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <button type="submit" class="btn btn-primary">Guardar calificaciones</button>
</form>