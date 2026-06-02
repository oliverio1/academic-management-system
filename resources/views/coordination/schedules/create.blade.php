@extends('layouts.app')

@section('title', 'Nuevo horario')

@php
    $cycleNameById = $cycles->mapWithKeys(fn ($cycle) => [$cycle->id => $cycle->name . ' (' . $cycle->code . ')'])->toArray();
    $groupNameById = $groups->pluck('name', 'id')->toArray();
    $subjectNameById = $subjects->pluck('name', 'id')->toArray();
    $teacherNameById = $teachers->mapWithKeys(fn ($t) => [$t->id => $t->user->name])->toArray();
    $teacherOptions = $teachers->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->user->name])->values()->all();
    $oldEntries = old('entries', []);

@endphp

@section('content')
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Registrar horarios semanales</h3>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            @if(session('overlap_warnings'))
                <div class="alert alert-warning">
                    <h6 class="mb-2"><strong>Se detectaron traslapes de horario.</strong></h6>
                    <ul class="mb-3">
                        @foreach((array) session('overlap_warnings') as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                    <div class="d-flex">
                        <button type="button" id="confirmOverlapBtn" class="btn btn-warning mr-2">
                            Guardar con traslapes
                        </button>
                        <span class="text-muted align-self-center">Si prefieres, corrige la captura y vuelve a guardar.</span>
                    </div>
                </div>
            @endif

            @if($activeCycle)
                <div class="alert alert-info">
                    <strong>Ciclo seleccionado:</strong> {{ $activeCycle->name }} ({{ $activeCycle->code }})
                    @if($usesCyclePlanning)
                        <span class="badge badge-primary ml-2">Usando planeacion por ciclo</span>
                    @else
                        <span class="badge badge-secondary ml-2">Sin planeacion activa: usando configuracion global</span>
                    @endif
                </div>
            @else
                <div class="alert alert-warning">
                    No hay ciclo activo. Se usara configuracion global de grupos y materias.
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('coordination.schedules.store') }}" id="bulkScheduleForm">
                @csrf
                <input type="hidden" name="allow_overlaps" id="allow_overlaps" value="0">

                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Captura</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="school_cycle_id" class="form-label">Ciclo escolar</label>
                                <select id="school_cycle_id" class="form-control">
                                    <option value="">Seleccione ciclo</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}" {{ (string) optional($selectedCycle)->id === (string) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="group_id" class="form-label">Grupo</label>
                                <select id="group_id" class="form-control">
                                    <option value="">Seleccione grupo</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="subject_id" class="form-label">Materia</label>
                                <select id="subject_id" class="form-control">
                                    <option value="">Seleccione materia</option>
                                </select>
                                <small class="text-muted">Solo materias asignadas al grupo.</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="teacher_id" class="form-label">Profesor</label>
                                <select id="teacher_id" class="form-control">
                                    <option value="">Seleccione profesor</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                                    @endforeach
                                </select>
                                <small id="teacher_help" class="text-muted d-block mt-1"></small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="section_number" class="form-label">Sección</label>
                                <select id="section_number" class="form-control">
                                    <option value="1">Sección 1</option>
                                </select>
                                <small class="text-muted">Disponible según el grupo en el ciclo.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="day_of_week" class="form-label">Dia</label>
                                <select id="day_of_week" class="form-control">
                                    <option value="">Seleccione dia</option>
                                    @foreach($dayOptions as $dayCode => $dayLabel)
                                        <option value="{{ $dayCode }}">{{ $dayLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="start_time" class="form-label">Hora inicio</label>
                                <input type="time" id="start_time" class="form-control">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="end_time" class="form-label">Hora fin</label>
                                <input type="time" id="end_time" class="form-control">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="type" class="form-label">Tipo (opcional)</label>
                                <input type="text" id="type" class="form-control" placeholder="Clase, laboratorio, taller">
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary" id="addRowBtn">
                            <i class="fas fa-plus mr-1"></i> Agregar
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <strong>Horarios por guardar</strong>
                    </div>
                    <div class="card-body table-responsive p-3">
                        <table class="table table-hover mb-0" id="entriesTable">
                            <thead>
                                <tr>
                                    <th>Ciclo</th>
                                    <th>Grupo</th>
                                    <th>Materia</th>
                                    <th>Profesor</th>
                                    <th>Sección</th>
                                    <th>Dia</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Tipo</th>
                                    <th style="width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="entriesBody">
                                @foreach($oldEntries as $index => $entry)
                                    <tr data-index="{{ $index }}">
                                        <td>
                                            @php $entryCycleId = $entry['school_cycle_id'] ?? optional($selectedCycle)->id; @endphp
                                            {{ $cycleNameById[$entryCycleId] ?? ('Ciclo #' . ($entryCycleId ?? 'N/D')) }}
                                        </td>
                                        <td>{{ $groupNameById[$entry['group_id']] ?? ('Grupo #' . $entry['group_id']) }}</td>
                                        <td>{{ $subjectNameById[$entry['subject_id']] ?? ('Materia #' . $entry['subject_id']) }}</td>
                                        <td>{{ $teacherNameById[$entry['teacher_id']] ?? ('Profesor #' . $entry['teacher_id']) }}</td>
                                        <td>{{ (int) ($entry['section_number'] ?? 1) }}</td>
                                        <td>{{ $dayOptions[$entry['day_of_week']] ?? $entry['day_of_week'] }}</td>
                                        <td>{{ $entry['start_time'] }}</td>
                                        <td>{{ $entry['end_time'] }}</td>
                                        <td>{{ $entry['type'] ?? '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger remove-row-btn">Quitar</button>
                                            <div class="d-none">
                                                <input type="hidden" name="entries[{{ $index }}][school_cycle_id]" value="{{ $entry['school_cycle_id'] ?? optional($selectedCycle)->id }}">
                                                <input type="hidden" name="entries[{{ $index }}][group_id]" value="{{ $entry['group_id'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][subject_id]" value="{{ $entry['subject_id'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][teacher_id]" value="{{ $entry['teacher_id'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][section_number]" value="{{ (int) ($entry['section_number'] ?? 1) }}">
                                                <input type="hidden" name="entries[{{ $index }}][day_of_week]" value="{{ $entry['day_of_week'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][start_time]" value="{{ $entry['start_time'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][end_time]" value="{{ $entry['end_time'] }}">
                                                <input type="hidden" name="entries[{{ $index }}][type]" value="{{ $entry['type'] ?? '' }}">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-success" id="submitBtn">Guardar horarios</button>
                        <a href="{{ route('coordination.schedules.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const cycleGroupsMap = @json($cycleGroupsMap ?? []);
        const dayOptions = @json($dayOptions);
        const cycleNames = @json($cycleNameById);
        const groupNames = @json($groupNameById);
        const subjectNames = @json($subjectNameById);
        const teacherNames = @json($teacherNameById);
        const teacherSubjectsMap = @json($teacherSubjectsMap ?? []);
        const teacherOptions = @json($teacherOptions);

        const cycleSelect = document.getElementById('school_cycle_id');
        const groupSelect = document.getElementById('group_id');
        const subjectSelect = document.getElementById('subject_id');
        const teacherSelect = document.getElementById('teacher_id');
        const sectionSelect = document.getElementById('section_number');
        const daySelect = document.getElementById('day_of_week');
        const startInput = document.getElementById('start_time');
        const endInput = document.getElementById('end_time');
        const typeInput = document.getElementById('type');
        const addRowBtn = document.getElementById('addRowBtn');
        const teacherHelp = document.getElementById('teacher_help');
        const entriesBody = document.getElementById('entriesBody');
        const form = document.getElementById('bulkScheduleForm');
        const allowOverlapsInput = document.getElementById('allow_overlaps');
        const confirmOverlapBtn = document.getElementById('confirmOverlapBtn');

        let nextIndex = entriesBody.querySelectorAll('tr').length;

        function refreshSubjects() {
            const selectedCycleId = cycleSelect.value;
            const selectedGroupId = groupSelect.value;
            const selectedSubjectId = subjectSelect.value;
            const groupsInCycle = cycleGroupsMap[selectedCycleId] || [];
            const currentGroup = groupsInCycle.find((group) => String(group.id) === String(selectedGroupId));
            const subjects = currentGroup ? (currentGroup.subjects || []) : [];

            subjectSelect.innerHTML = '<option value="">Seleccione materia</option>';
            subjects.forEach((subject) => {
                const option = document.createElement('option');
                option.value = String(subject.id);
                option.textContent = subject.name;
                option.dataset.sectionCount = String(subject.section_count || 1);
                if (String(subject.id) === String(selectedSubjectId)) {
                    option.selected = true;
                }
                subjectSelect.appendChild(option);
            });

            refreshTeachers();
        }

        function refreshGroups() {
            const selectedCycleId = cycleSelect.value;
            const selectedGroupId = groupSelect.value;
            const groupsInCycle = cycleGroupsMap[selectedCycleId] || [];

            groupSelect.innerHTML = '<option value="">Seleccione grupo</option>';
            groupsInCycle.forEach((group) => {
                const option = document.createElement('option');
                option.value = String(group.id);
                option.textContent = group.name;
                if (String(group.id) === String(selectedGroupId)) {
                    option.selected = true;
                }
                groupSelect.appendChild(option);
            });

            if (!groupsInCycle.some((group) => String(group.id) === String(selectedGroupId))) {
                groupSelect.value = '';
            }

            refreshSubjects();
            refreshSections();
        }

        function refreshSections() {
            const selectedSubjectOption = subjectSelect.options[subjectSelect.selectedIndex];
            const sectionCount = Math.max(1, Math.min(3, Number(selectedSubjectOption?.dataset?.sectionCount || 1)));
            const selected = sectionSelect.value || '1';

            sectionSelect.innerHTML = '';
            for (let i = 1; i <= sectionCount; i++) {
                const option = document.createElement('option');
                option.value = String(i);
                option.textContent = `Sección ${i}`;
                if (String(i) === String(selected)) {
                    option.selected = true;
                }
                sectionSelect.appendChild(option);
            }
        }

        function refreshTeachers() {
            const selectedSubjectId = subjectSelect.value;
            const selectedTeacherId = teacherSelect.value;
            const allowedTeacherIds = selectedSubjectId
                ? teacherOptions
                    .filter((teacher) => (teacherSubjectsMap[String(teacher.id)] || []).map(String).includes(String(selectedSubjectId)))
                    .map((teacher) => String(teacher.id))
                : teacherOptions.map((teacher) => String(teacher.id));

            teacherSelect.innerHTML = '<option value="">Seleccione profesor</option>';
            teacherOptions
                .filter((teacher) => allowedTeacherIds.includes(String(teacher.id)))
                .forEach((teacher) => {
                    const option = document.createElement('option');
                    option.value = String(teacher.id);
                    option.textContent = teacher.name;
                    if (String(teacher.id) === String(selectedTeacherId)) {
                        option.selected = true;
                    }
                    teacherSelect.appendChild(option);
                });

            const hasTeachers = teacherSelect.options.length > 1;
            if (!hasTeachers && selectedSubjectId) {
                if (teacherHelp) {
                    teacherHelp.textContent = 'No hay docentes asignados a esta materia.';
                    teacherHelp.classList.remove('text-muted');
                    teacherHelp.classList.add('text-danger');
                }
                addRowBtn.disabled = true;
            } else {
                if (teacherHelp) {
                    teacherHelp.textContent = selectedSubjectId ? 'Solo se muestran docentes vinculados a la materia.' : '';
                    teacherHelp.classList.remove('text-danger');
                    teacherHelp.classList.add('text-muted');
                }
                addRowBtn.disabled = false;
            }
        }

        function addHiddenInput(container, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            container.appendChild(input);
        }

        function addRow() {
            const schoolCycleId = cycleSelect.value;
            const groupId = groupSelect.value;
            const subjectId = subjectSelect.value;
            const teacherId = teacherSelect.value;
            const sectionNumber = sectionSelect.value;
            const day = daySelect.value;
            const start = startInput.value;
            const end = endInput.value;
            const type = typeInput.value.trim();

            if (!schoolCycleId || !groupId || !subjectId || !teacherId || !sectionNumber || !day || !start || !end) {
                alert('Completa ciclo, grupo, materia, profesor, sección, dia y horas.');
                return;
            }

            if (end <= start) {
                alert('La hora de fin debe ser mayor que la hora de inicio.');
                return;
            }

            const tr = document.createElement('tr');
            tr.dataset.index = String(nextIndex);

            tr.innerHTML = `
                <td>${cycleNames[schoolCycleId] || ('Ciclo #' + schoolCycleId)}</td>
                <td>${groupNames[groupId] || ('Grupo #' + groupId)}</td>
                <td>${subjectNames[subjectId] || ('Materia #' + subjectId)}</td>
                <td>${teacherNames[teacherId] || ('Profesor #' + teacherId)}</td>
                <td>${sectionNumber}</td>
                <td>${dayOptions[day] || day}</td>
                <td>${start}</td>
                <td>${end}</td>
                <td>${type || '-'}</td>
                <td><button type="button" class="btn btn-sm btn-danger remove-row-btn">Quitar</button><div class="d-none"></div></td>
            `;

            const hiddenBox = tr.querySelector('.d-none');
            addHiddenInput(hiddenBox, `entries[${nextIndex}][school_cycle_id]`, schoolCycleId);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][group_id]`, groupId);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][subject_id]`, subjectId);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][teacher_id]`, teacherId);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][section_number]`, sectionNumber);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][day_of_week]`, day);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][start_time]`, start);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][end_time]`, end);
            addHiddenInput(hiddenBox, `entries[${nextIndex}][type]`, type);

            entriesBody.appendChild(tr);
            nextIndex += 1;

            daySelect.value = '';
            startInput.value = '';
            endInput.value = '';
            typeInput.value = '';
        }

        cycleSelect.addEventListener('change', refreshGroups);
        groupSelect.addEventListener('change', function () {
            refreshSubjects();
            refreshSections();
        });
        subjectSelect.addEventListener('change', function () {
            refreshSections();
            refreshTeachers();
        });
        addRowBtn.addEventListener('click', addRow);

        entriesBody.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-row-btn')) {
                event.target.closest('tr').remove();
            }
        });

        form.addEventListener('submit', function (event) {
            if (entriesBody.querySelectorAll('tr').length === 0) {
                event.preventDefault();
                alert('Agrega al menos un horario antes de guardar.');
            }
        });

        if (confirmOverlapBtn) {
            confirmOverlapBtn.addEventListener('click', function () {
                if (allowOverlapsInput) {
                    allowOverlapsInput.value = '1';
                }
                form.submit();
            });
        }

        refreshGroups();
        refreshSections();
        refreshTeachers();
    })();
</script>
@endsection

