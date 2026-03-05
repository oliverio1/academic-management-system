@if(request('tab') === 'teams')

<div class="row">

    {{-- EQUIPOS --}}
    <div class="col-md-6">
        <h5>Equipos</h5>

        @foreach ($teams as $team)
            <div class="card mb-2">
                <div class="card-header d-flex justify-content-between">
                    <strong>{{ $team->name }}</strong>
                    <span class="badge bg-secondary">
                        {{ $team->students->count() }} alumnos
                    </span>
                </div>

                <div class="card-body">
                    <ul class="mb-0">
                        @foreach ($team->students as $student)
                            <li>
                                {{ $student->user->name }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-outline-primary">
                        Editar
                    </button>
                    <button class="btn btn-sm btn-outline-danger">
                        Eliminar
                    </button>
                </div>
            </div>
        @endforeach

        <button class="btn btn-sm btn-success mt-2"
            data-toggle="modal"
            data-target="#createTeamModal">
            + Crear equipo
        </button>
    </div>

    {{-- ALUMNOS SIN EQUIPO --}}
    <div class="col-md-6">
        <h5>Alumnos sin equipo</h5>

        @if ($studentsWithoutTeam->isEmpty())
            <div class="alert alert-success">
                Todos los alumnos tienen equipo 🎉
            </div>
        @else
            <ul class="list-group">
                @foreach ($studentsWithoutTeam as $student)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ $student->user->name }}

                        <form method="POST"
                              action="{{ route('teams.assign') }}">
                            @csrf
                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                            <input type="hidden" name="teaching_assignment_id" value="{{ $teachingAssignment->id }}">

                            <select name="team_id"
                                    class="form-select form-control-sm d-inline w-auto"
                                    onchange="this.form.submit()">
                                <option value="">Asignar a...</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">
                                        {{ $team->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="modal fade" id="createTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST"
              action="{{ route('teams.store') }}"
              class="modal-content">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">Crear equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden"
                       name="teaching_assignment_id"
                       value="{{ $teachingAssignment->id }}">

                <div class="mb-3">
                    <label class="form-label">Nombre del equipo</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           required>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary"
                        data-bs-dismiss="modal"
                        type="button">
                    Cancelar
                </button>
                <button class="btn btn-success" type="submit">
                    Crear
                </button>
            </div>
        </form>
    </div>
</div>


@endif