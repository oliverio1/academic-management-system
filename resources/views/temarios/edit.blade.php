@extends('layouts.app')

@section('title', 'Editar temario')

@section('content')
    <div class="content px-3">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h4>Editar temario</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('temarios.update', $temario) }}">
                            @csrf
                            @method('PUT')

                            @include('temarios._form')

                            <button class="btn btn-primary">Guardar cambios</button>
                            <a href="{{ route('temarios.index', $subject) }}" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_scripts')
    @php
        $unitsForJs = old('units');
        if (!$unitsForJs) {
            $unitsForJs = [];
            $current = null;
            foreach ($temario->points as $point) {
                $level = (int) ($point->level ?? 1);
                if ($level === 1) {
                    $raw = (string) $point->content;
                    $unitTitle = $raw;
                    $unitObjective = '';
                    if (str_contains($raw, '|')) {
                        [$left, $right] = array_pad(explode('|', $raw, 2), 2, '');
                        $unitTitle = trim((string) $left);
                        $right = trim((string) $right);
                        $unitObjective = preg_replace('/^Objetivo\s+espec[ií]fico\s*[:\-]?\s*/iu', '', $right) ?? $right;
                        $unitObjective = trim((string) $unitObjective);
                    } elseif (preg_match('/^(.*?)\s*Objetivo\s+espec[ií]fico\s*[:\-]\s*(.+)$/ui', $raw, $matches) === 1) {
                        $unitTitle = trim((string) ($matches[1] ?? ''));
                        $unitObjective = trim((string) ($matches[2] ?? ''));
                    }

                    if ($current) {
                        $unitsForJs[] = $current;
                    }
                    $current = [
                        'title' => $unitTitle,
                        'objective' => $unitObjective,
                        'hours' => $point->hours,
                        'points' => [],
                    ];
                    continue;
                }

                if (!$current) {
                    $current = ['title' => 'Unidad', 'points' => []];
                }

                $current['points'][] = [
                    'label' => $point->label,
                    'type' => $point->type,
                    'content' => $point->content,
                ];
            }

            if ($current) {
                if (empty($current['points'])) {
                    $current['points'][] = ['label' => '', 'type' => 'conceptual', 'content' => ''];
                }
                $unitsForJs[] = $current;
            }
        }
    @endphp
    <script>
        const initialUnits = @json($unitsForJs);
        const typeOptions = {
            conceptual: 'Conceptual',
            procedimental: 'Procedimental',
            actitudinal: 'Actitudinal',
            otro: 'Otro'
        };

        const unitsContainer = document.getElementById('units-container');
        const addUnitButton = document.getElementById('add-unit-btn');

        const getDefaultPoint = () => ({
            label: '',
            type: 'conceptual',
            content: ''
        });

        const getDefaultUnit = () => ({
            title: '',
            objective: '',
            hours: '',
            points: [getDefaultPoint()]
        });

        const units = Array.isArray(initialUnits) && initialUnits.length
            ? initialUnits
            : [getDefaultUnit()];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function unitCard(unit, unitIndex) {
            const pointsHtml = (unit.points || []).map((point, pointIndex) => `
                <div class="border rounded p-2 mb-2 point-row" data-point-index="${pointIndex}">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Clave</label>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   name="units[${unitIndex}][points][${pointIndex}][label]"
                                   value="${escapeHtml(point.label)}"
                                   placeholder="1.1 o a)">
                        </div>
                        <div class="col-md-3">
                            <label>Tipo</label>
                            <select class="form-control form-control-sm" name="units[${unitIndex}][points][${pointIndex}][type]">
                                ${Object.entries(typeOptions).map(([value, label]) =>
                                    `<option value="${value}" ${point.type === value ? 'selected' : ''}>${label}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Punto</label>
                            <textarea class="form-control form-control-sm"
                                      rows="2"
                                      name="units[${unitIndex}][points][${pointIndex}][content]"
                                      placeholder="Describe el punto del temario">${escapeHtml(point.content)}</textarea>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 js-remove-point"
                                    data-unit-index="${unitIndex}" data-point-index="${pointIndex}">
                                X
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            return `
                <div class="card mb-3 unit-card" data-unit-index="${unitIndex}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Unidad ${unitIndex + 1}</strong>
                        <button type="button" class="btn btn-outline-danger btn-sm js-remove-unit" data-unit-index="${unitIndex}">
                            Eliminar unidad
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Unidad</label>
                            <input type="text"
                                   class="form-control"
                                   name="units[${unitIndex}][title]"
                                   value="${escapeHtml(unit.title)}"
                                   placeholder="Ejemplo: Geografia como ciencia">
                        </div>
                        <div class="form-group">
                            <label>Horas de la unidad</label>
                            <input type="number"
                                   min="0"
                                   step="0.25"
                                   class="form-control"
                                   name="units[${unitIndex}][hours]"
                                   value="${escapeHtml(unit.hours || '')}"
                                   placeholder="Ejemplo: 12">
                        </div>
                        <div class="form-group">
                            <label>Objetivo específico de la unidad</label>
                            <textarea class="form-control"
                                      rows="2"
                                      name="units[${unitIndex}][objective]"
                                      placeholder="Describe el objetivo específico de esta unidad">${escapeHtml(unit.objective || '')}</textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Puntos del temario</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm js-add-point" data-unit-index="${unitIndex}">
                                Agregar punto
                            </button>
                        </div>
                        <div class="unit-points">${pointsHtml}</div>
                    </div>
                </div>
            `;
        }

        function renderUnits() {
            unitsContainer.innerHTML = units.map((unit, unitIndex) => unitCard(unit, unitIndex)).join('');
            bindEvents();
        }

        function syncUnitsFromInputs() {
            const unitCards = unitsContainer.querySelectorAll('.unit-card');
            if (!unitCards.length) return;

            unitCards.forEach((card, unitIndex) => {
                if (!units[unitIndex]) units[unitIndex] = getDefaultUnit();

                const titleInput = card.querySelector(`input[name="units[${unitIndex}][title]"]`);
                units[unitIndex].title = titleInput ? titleInput.value : '';
                const objectiveInput = card.querySelector(`textarea[name="units[${unitIndex}][objective]"]`);
                units[unitIndex].objective = objectiveInput ? objectiveInput.value : '';
                const hoursInput = card.querySelector(`input[name="units[${unitIndex}][hours]"]`);
                units[unitIndex].hours = hoursInput ? hoursInput.value : '';

                const pointRows = card.querySelectorAll('.point-row');
                units[unitIndex].points = Array.from(pointRows).map((row, pointIndex) => {
                    const labelInput = row.querySelector(`input[name="units[${unitIndex}][points][${pointIndex}][label]"]`);
                    const typeSelect = row.querySelector(`select[name="units[${unitIndex}][points][${pointIndex}][type]"]`);
                    const contentInput = row.querySelector(`textarea[name="units[${unitIndex}][points][${pointIndex}][content]"]`);
                    return {
                        label: labelInput ? labelInput.value : '',
                        type: typeSelect ? typeSelect.value : 'conceptual',
                        content: contentInput ? contentInput.value : '',
                    };
                });

                if (!units[unitIndex].points.length) {
                    units[unitIndex].points = [getDefaultPoint()];
                }
            });
        }

        function bindEvents() {
            unitsContainer.querySelectorAll('.js-add-point').forEach((btn) => {
                btn.addEventListener('click', () => {
                    syncUnitsFromInputs();
                    const unitIndex = parseInt(btn.getAttribute('data-unit-index'), 10);
                    units[unitIndex].points.push(getDefaultPoint());
                    renderUnits();
                });
            });

            unitsContainer.querySelectorAll('.js-remove-point').forEach((btn) => {
                btn.addEventListener('click', () => {
                    syncUnitsFromInputs();
                    const unitIndex = parseInt(btn.getAttribute('data-unit-index'), 10);
                    const pointIndex = parseInt(btn.getAttribute('data-point-index'), 10);
                    if (units[unitIndex].points.length === 1) {
                        units[unitIndex].points[0] = getDefaultPoint();
                    } else {
                        units[unitIndex].points.splice(pointIndex, 1);
                    }
                    renderUnits();
                });
            });

            unitsContainer.querySelectorAll('.js-remove-unit').forEach((btn) => {
                btn.addEventListener('click', () => {
                    syncUnitsFromInputs();
                    const unitIndex = parseInt(btn.getAttribute('data-unit-index'), 10);
                    if (units.length === 1) {
                        units[0] = getDefaultUnit();
                    } else {
                        units.splice(unitIndex, 1);
                    }
                    renderUnits();
                });
            });
        }

        addUnitButton.addEventListener('click', () => {
            syncUnitsFromInputs();
            units.push(getDefaultUnit());
            renderUnits();
        });

        renderUnits();
    </script>
@endsection
