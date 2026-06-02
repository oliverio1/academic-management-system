@extends('layouts.app')

@section('title', $isEdit ? 'Editar planeacion' : 'Nueva planeacion')

@section('content')
@php
    $existingItems = old('items');
    $existingUnitsPayload = old('units_payload');
    $defaultCycleId = optional($cycles->firstWhere('is_active', true) ?: $cycles->first())->id;
    $selectedCycleId = (string) old('school_cycle_id', $plan->school_cycle_id ?: $defaultCycleId);
    $cyclesForJs = $cycles->map(function ($cycle) {
        return [
            'id' => (int) $cycle->id,
            'name' => (string) $cycle->name,
            'start_date' => optional($cycle->start_date)->format('Y-m-d'),
            'end_date' => optional($cycle->end_date)->format('Y-m-d'),
            'is_active' => (bool) ($cycle->is_active ?? false),
        ];
    })->values();

    $unitsById = collect($unitOptions)->keyBy(fn ($u) => (string) $u['id']);
    $existingUnitBlocks = [];

    if (is_string($existingUnitsPayload) && trim($existingUnitsPayload) !== '') {
        $existingItems = null;
        $decoded = json_decode($existingUnitsPayload, true);
        if (is_array($decoded)) {
            $groups = collect($decoded)->groupBy(function ($item) {
                return ($item['field_training_point_id'] ?? '') . '|' . ($item['objective'] ?? '');
            });
            $existingUnitBlocks = $groups->map(function ($items, $key) use ($unitsById) {
                [$unitId, $objective] = array_pad(explode('|', (string) $key, 2), 2, '');
                return [
                    'field_training_point_id' => $unitId !== '' ? (int) $unitId : null,
                    'field_training_text' => optional($unitsById->get((string) $unitId))['text'] ?? 'Sin unidad',
                    'objective' => $objective !== '' ? $objective : null,
                    'items' => collect($items)->values()->all(),
                ];
            })->values()->all();
        }
    } elseif ($isEdit) {
        $groups = $plan->items->groupBy(function ($item) {
            return ((string) ($item->field_training_point_id ?? '')) . '|' . ((string) ($item->objective ?? ''));
        });

        $existingUnitBlocks = $groups->map(function ($items, $key) use ($unitsById) {
            [$unitId, $objective] = array_pad(explode('|', (string) $key, 2), 2, '');
            return [
                'field_training_point_id' => $unitId !== '' ? (int) $unitId : null,
                'field_training_text' => optional($unitsById->get((string) $unitId))['text'] ?? 'Sin unidad',
                'objective' => $objective !== '' ? $objective : null,
                'items' => $items->map(fn ($item) => [
                    'temario_point_id' => $item->temario_point_id,
                    'temario_subtopic_ids' => $item->temario_subtopic_ids ?? [],
                    'opening' => $item->opening,
                    'development' => $item->development,
                    'closing' => $item->closing,
                    'resources' => $item->resources,
                    'evaluation' => $item->evaluation,
                    'start_date' => optional($item->start_date)->format('Y-m-d'),
                    'end_date' => optional($item->end_date)->format('Y-m-d'),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    if (!$existingItems) {
        $existingItems = [[
            'temario_point_id' => null,
            'temario_subtopic_ids' => [],
            'opening' => null,
            'development' => null,
            'closing' => null,
            'resources' => null,
            'evaluation' => null,
            'start_date' => null,
            'end_date' => null,
        ]];
    }

    $resourceOptions = [
        'Pizarrón',
        'Libros de texto',
        'Cuaderno de trabajo',
        'Presentación (diapositivas)',
        'Internet',
        "TIC's / plataformas digitales",
        'Videos educativos',
        'Simuladores / laboratorio virtual',
        'Material impreso',
        'Calculadora / software especializado',
    ];

    $evaluationOptions = [
        'Lista de cotejo',
        'Rúbrica',
        'Guía de observación',
        'Examen diagnóstico',
        'Examen parcial',
        'Examen final',
        'Proyecto',
        'Práctica / laboratorio',
        'Exposición',
        'Portafolio de evidencias',
        'Autoevaluación',
        'Coevaluación',
    ];
@endphp
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        {{ $isEdit ? 'Editar planeacion didactica' : 'Nueva planeacion didactica' }}
                    </h4>
                    <small class="text-muted">{{ $assignment->subject->name }} - Grupo {{ $assignment->group->name }}</small>
                </div>
                <form method="POST"
                      action="{{ $isEdit ? route('teacher.didactic-plans.update', $plan) : route('teacher.didactic-plans.store', $assignment) }}">
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="units_payload" id="units_payload" value="{{ old('units_payload') }}">
                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <strong>Revisa la informacion antes de guardar.</strong>
                                <ul class="mb-0 mt-2 pl-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Titulo</label>
                                    <input type="text" name="title" class="form-control"
                                           value="{{ old('title', $plan->title ?: 'Planeacion ' . $assignment->subject->name . ' - ' . $assignment->group->name) }}"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Ciclo</label>
                                    <select name="school_cycle_id" id="school_cycle_id" class="form-control" required>
                                        <option value="">Seleccionar</option>
                                        @foreach($cycles as $cycle)
                                            <option value="{{ $cycle->id }}"
                                                {{ (string) $selectedCycleId === (string) $cycle->id ? 'selected' : '' }}>
                                                {{ $cycle->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-light border" id="cycle-range-box">
                            <div>
                                <strong>Rango de planeacion del ciclo:</strong>
                                <span id="cycle-range-text" class="text-muted">Selecciona un ciclo para ver el rango permitido.</span>
                            </div>
                            <div class="mt-1 small text-muted" id="cycle-weeks-box" style="display:none;">
                                <span class="font-weight-bold">Semanas:</span>
                                <span id="cycle-weeks-ranges" class="ml-1"></span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Campo formativo (unidad)</label>
                                    <select id="field_training_point_id" name="current_field_training_point_id" class="form-control">
                                        <option value="">Seleccionar unidad</option>
                                        @foreach($unitOptions as $unit)
                                            <option value="{{ $unit['id'] }}">{{ $unit['text'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label>Objetivo / progresion</label>
                                    <textarea id="unit_objective" name="current_unit_objective" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Renglones de planeacion (tema y actividades)</label>
                            <small class="d-block text-muted mb-2">En Recursos y Evaluación puedes seleccionar varias opciones con Ctrl (Windows) o Cmd (Mac).</small>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="items-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="min-width: 230px;">Tema</th>
                                            <th style="min-width: 230px;">Subtemas</th>
                                            <th style="min-width: 200px;">Apertura</th>
                                            <th style="min-width: 200px;">Desarrollo</th>
                                            <th style="min-width: 200px;">Cierre</th>
                                            <th style="min-width: 180px;">Recursos</th>
                                            <th style="min-width: 180px;">Evaluacion</th>
                                            <th style="min-width: 130px;">Inicio</th>
                                            <th style="min-width: 130px;">Termino</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($existingItems as $i => $item)
                                            @php
                                                $selectedSubtopics = collect($item['temario_subtopic_ids'] ?? [])->map(fn ($id) => (string) $id);
                                            @endphp
                                            <tr data-row-index="{{ $i }}">
                                                <td>
                                                    <select name="items[{{ $i }}][temario_point_id]"
                                                            class="form-control form-control-sm js-topic-select">
                                                        <option value="">Seleccionar tema</option>
                                                        @foreach($topicOptions as $topic)
                                                            <option value="{{ $topic['id'] }}"
                                                                    data-unit-id="{{ $topic['unit_id'] }}"
                                                                    {{ (string) ($item['temario_point_id'] ?? '') === (string) $topic['id'] ? 'selected' : '' }}>
                                                                {{ $topic['text'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="items[{{ $i }}][temario_subtopic_ids][]"
                                                            class="form-control form-control-sm js-subtopic-select"
                                                            multiple
                                                            size="5">
                                                        @foreach($subtopicOptions as $subtopic)
                                                            <option value="{{ $subtopic['id'] }}"
                                                                    data-topic-id="{{ $subtopic['topic_id'] }}"
                                                                    {{ $selectedSubtopics->contains((string) $subtopic['id']) ? 'selected' : '' }}>
                                                                {{ $subtopic['text'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><textarea name="items[{{ $i }}][opening]" class="form-control form-control-sm" rows="2">{{ $item['opening'] ?? '' }}</textarea></td>
                                                <td><textarea name="items[{{ $i }}][development]" class="form-control form-control-sm" rows="2">{{ $item['development'] ?? '' }}</textarea></td>
                                                <td><textarea name="items[{{ $i }}][closing]" class="form-control form-control-sm" rows="2">{{ $item['closing'] ?? '' }}</textarea></td>
                                                @php
                                                    $selectedResources = collect(preg_split('/[;,\\r\\n]+/u', (string) ($item['resources'] ?? '')) ?: [])
                                                        ->map(fn ($value) => trim((string) $value))
                                                        ->filter()
                                                        ->values();
                                                    $selectedEvaluations = collect(preg_split('/[;,\\r\\n]+/u', (string) ($item['evaluation'] ?? '')) ?: [])
                                                        ->map(fn ($value) => trim((string) $value))
                                                        ->filter()
                                                        ->values();
                                                @endphp
                                                <td>
                                                    <select class="form-control form-control-sm js-resource-select" multiple size="4">
                                                        @foreach($resourceOptions as $option)
                                                            <option value="{{ $option }}" {{ $selectedResources->contains($option) ? 'selected' : '' }}>{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="hidden" name="items[{{ $i }}][resources]" class="js-resource-hidden" value="{{ $item['resources'] ?? '' }}">
                                                </td>
                                                <td>
                                                    <select class="form-control form-control-sm js-evaluation-select" multiple size="4">
                                                        @foreach($evaluationOptions as $option)
                                                            <option value="{{ $option }}" {{ $selectedEvaluations->contains($option) ? 'selected' : '' }}>{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="hidden" name="items[{{ $i }}][evaluation]" class="js-evaluation-hidden" value="{{ $item['evaluation'] ?? '' }}">
                                                </td>
                                                <td><input type="date" name="items[{{ $i }}][start_date]" class="form-control form-control-sm js-date-start" value="{{ $item['start_date'] ?? '' }}"></td>
                                                <td><input type="date" name="items[{{ $i }}][end_date]" class="form-control form-control-sm js-date-end" value="{{ $item['end_date'] ?? '' }}"></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-sm js-remove-row">x</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="add-row-btn">+ Agregar renglon</button>
                            <button type="button" class="btn btn-info btn-sm" id="save-unit-btn">+ Guardar unidad en tabla temporal</button>
                            <span class="ml-2 badge badge-light" id="units-temp-counter">0 unidades en temporal</span>
                        </div>

                        <div class="form-group">
                            <label>Unidades capturadas</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Unidad</th>
                                            <th>Objetivo / progresion</th>
                                            <th>Renglones</th>
                                            <th style="width: 180px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="units-temp-body">
                                        <tr id="units-temp-empty">
                                            <td colspan="4" class="text-muted">Sin unidades capturadas.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bibliografia</label>
                                    <textarea name="bibliography" class="form-control" rows="3">{{ old('bibliography', $plan->bibliography) }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bibliografia complementaria</label>
                                    <textarea name="complementary_bibliography" class="form-control" rows="3">{{ old('complementary_bibliography', $plan->complementary_bibliography) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notas</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $plan->notes) }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('teacher.didactic-plans.plans', $assignment) }}" class="btn btn-secondary">Cancelar</a>
                        <button class="btn btn-primary">Guardar planeacion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="row-template">
    <tr data-row-index="__INDEX__">
        <td>
            <select name="items[__INDEX__][temario_point_id]" class="form-control form-control-sm js-topic-select">
                <option value="">Seleccionar tema</option>
                @foreach($topicOptions as $topic)
                    <option value="{{ $topic['id'] }}" data-unit-id="{{ $topic['unit_id'] }}">{{ $topic['text'] }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="items[__INDEX__][temario_subtopic_ids][]" class="form-control form-control-sm js-subtopic-select" multiple size="5">
                @foreach($subtopicOptions as $subtopic)
                    <option value="{{ $subtopic['id'] }}" data-topic-id="{{ $subtopic['topic_id'] }}">{{ $subtopic['text'] }}</option>
                @endforeach
            </select>
        </td>
        <td><textarea name="items[__INDEX__][opening]" class="form-control form-control-sm" rows="2"></textarea></td>
        <td><textarea name="items[__INDEX__][development]" class="form-control form-control-sm" rows="2"></textarea></td>
        <td><textarea name="items[__INDEX__][closing]" class="form-control form-control-sm" rows="2"></textarea></td>
        <td>
            <select class="form-control form-control-sm js-resource-select" multiple size="4">
                @foreach($resourceOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <input type="hidden" name="items[__INDEX__][resources]" class="js-resource-hidden">
        </td>
        <td>
            <select class="form-control form-control-sm js-evaluation-select" multiple size="4">
                @foreach($evaluationOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <input type="hidden" name="items[__INDEX__][evaluation]" class="js-evaluation-hidden">
        </td>
        <td><input type="date" name="items[__INDEX__][start_date]" class="form-control form-control-sm js-date-start"></td>
        <td><input type="date" name="items[__INDEX__][end_date]" class="form-control form-control-sm js-date-end"></td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm js-remove-row">x</button>
        </td>
    </tr>
</template>
@endsection

@section('page_scripts')
<script>
(() => {
    const form = document.querySelector('form');
    const tableBody = document.querySelector('#items-table tbody');
    const addBtn = document.getElementById('add-row-btn');
    const saveUnitBtn = document.getElementById('save-unit-btn');
    const rowTemplate = document.getElementById('row-template');
    const cycleSelect = document.getElementById('school_cycle_id');
    const fieldTrainingSelect = document.getElementById('field_training_point_id');
    const unitObjectiveInput = document.getElementById('unit_objective');
    const unitsPayloadInput = document.getElementById('units_payload');
    const unitsTempCounter = document.getElementById('units-temp-counter');
    const unitsTempBody = document.getElementById('units-temp-body');
    const unitsTempEmpty = document.getElementById('units-temp-empty');
    const rangeText = document.getElementById('cycle-range-text');
    const weeksBox = document.getElementById('cycle-weeks-box');
    const weeksRanges = document.getElementById('cycle-weeks-ranges');
    const cycles = @json($cyclesForJs);
    const initialCycleId = @json($selectedCycleId);
    const initialUnitBlocks = @json($existingUnitBlocks);
    const unitOptions = @json($unitOptions);

    let index = tableBody.querySelectorAll('tr').length;
    let unitBlocks = Array.isArray(initialUnitBlocks) ? initialUnitBlocks : [];
    let renderedWeeks = [];
    let editingUnit = false;
    let previousFieldTrainingValue = fieldTrainingSelect ? fieldTrainingSelect.value : '';

    function filterTopicsByUnit(row) {
        const topicSelect = row.querySelector('.js-topic-select');
        if (!topicSelect || !fieldTrainingSelect) return;

        const selectedUnitId = fieldTrainingSelect.value;
        Array.from(topicSelect.options).forEach((option) => {
            if (option.value === '') {
                option.hidden = false;
                return;
            }
            const belongsToUnit = option.getAttribute('data-unit-id');
            const visible = selectedUnitId !== '' && belongsToUnit === selectedUnitId;
            option.hidden = !visible;
        });

        const selectedOption = topicSelect.options[topicSelect.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            topicSelect.value = '';
        }
    }

    function filterSubtopics(row) {
        const topicSelect = row.querySelector('.js-topic-select');
        const subtopicSelect = row.querySelector('.js-subtopic-select');
        if (!topicSelect || !subtopicSelect) {
            return;
        }

        const topicId = topicSelect.value;
        Array.from(subtopicSelect.options).forEach((option) => {
            const belongsTo = option.getAttribute('data-topic-id');
            const visible = topicId !== '' && belongsTo === topicId;
            option.hidden = !visible;
            if (!visible) option.selected = false;
        });
    }

    function bindRow(row) {
        const topicSelect = row.querySelector('.js-topic-select');
        const removeBtn = row.querySelector('.js-remove-row');
        const resourceSelect = row.querySelector('.js-resource-select');
        const evaluationSelect = row.querySelector('.js-evaluation-select');
        setMultiSelectValues(
            row,
            '.js-resource-select',
            '.js-resource-hidden',
            row.querySelector('.js-resource-hidden')?.value || ''
        );
        setMultiSelectValues(
            row,
            '.js-evaluation-select',
            '.js-evaluation-hidden',
            row.querySelector('.js-evaluation-hidden')?.value || ''
        );
        if (topicSelect) {
            topicSelect.addEventListener('change', () => filterSubtopics(row));
        }
        if (resourceSelect) {
            resourceSelect.addEventListener('change', () => syncMultiSelectHidden(row, '.js-resource-select', '.js-resource-hidden'));
        }
        if (evaluationSelect) {
            evaluationSelect.addEventListener('change', () => syncMultiSelectHidden(row, '.js-evaluation-select', '.js-evaluation-hidden'));
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                const rows = tableBody.querySelectorAll('tr');
                if (rows.length > 1) {
                    row.remove();
                }
            });
        }
        filterTopicsByUnit(row);
        filterSubtopics(row);
        syncMultiSelectHidden(row, '.js-resource-select', '.js-resource-hidden');
        syncMultiSelectHidden(row, '.js-evaluation-select', '.js-evaluation-hidden');
        applyCycleLimitsToRow(row);
    }

    function unitTextById(id) {
        const found = unitOptions.find((u) => String(u.id) === String(id));
        return found ? found.text : 'Sin unidad';
    }

    function unitObjectiveById(id) {
        const found = unitOptions.find((u) => String(u.id) === String(id));
        return found ? (found.objective || '') : '';
    }

    function rowDataFromDom(row) {
        const topic = row.querySelector('.js-topic-select')?.value || '';
        const subtopicSelect = row.querySelector('.js-subtopic-select');
        const selectedSubtopics = subtopicSelect
            ? Array.from(subtopicSelect.selectedOptions).map((o) => parseInt(o.value, 10)).filter((v) => !Number.isNaN(v))
            : [];

        return {
            temario_point_id: topic !== '' ? parseInt(topic, 10) : null,
            temario_subtopic_ids: selectedSubtopics,
            opening: row.querySelector('textarea[name*="[opening]"]')?.value || null,
            development: row.querySelector('textarea[name*="[development]"]')?.value || null,
            closing: row.querySelector('textarea[name*="[closing]"]')?.value || null,
            resources: row.querySelector('.js-resource-hidden')?.value || null,
            evaluation: row.querySelector('.js-evaluation-hidden')?.value || null,
            start_date: row.querySelector('.js-date-start')?.value || null,
            end_date: row.querySelector('.js-date-end')?.value || null,
        };
    }

    function syncMultiSelectHidden(row, selectClass, hiddenClass) {
        const select = row.querySelector(selectClass);
        const hidden = row.querySelector(hiddenClass);
        if (!select || !hidden) return;
        hidden.value = Array.from(select.selectedOptions).map((option) => option.value).join(', ');
    }

    function setMultiSelectValues(row, selectClass, hiddenClass, rawValue) {
        const select = row.querySelector(selectClass);
        const hidden = row.querySelector(hiddenClass);
        if (!select || !hidden) return;

        const selectedValues = String(rawValue || '')
            .split(/[,;\r\n]+/u)
            .map((value) => value.trim())
            .filter((value) => value !== '');

        const currentValues = new Set(Array.from(select.options).map((option) => option.value));
        selectedValues.forEach((value) => {
            if (!currentValues.has(value)) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                select.appendChild(option);
                currentValues.add(value);
            }
        });

        const selectedSet = new Set(selectedValues);
        Array.from(select.options).forEach((option) => {
            option.selected = selectedSet.has(option.value);
        });

        syncMultiSelectHidden(row, selectClass, hiddenClass);
    }

    function rowHasContent(item) {
        return !!(
            item.temario_point_id ||
            (item.temario_subtopic_ids && item.temario_subtopic_ids.length) ||
            item.opening || item.development || item.closing ||
            item.resources || item.evaluation || item.start_date || item.end_date
        );
    }

    function collectCurrentRows() {
        return Array.from(tableBody.querySelectorAll('tr'))
            .map(rowDataFromDom)
            .filter(rowHasContent);
    }

    function clearCurrentRows() {
        tableBody.innerHTML = '';
        index = 0;
        createRow();
    }

    function createRow(item = null) {
        const html = rowTemplate.innerHTML.replaceAll('__INDEX__', String(index));
        tableBody.insertAdjacentHTML('beforeend', html);
        const row = tableBody.querySelector(`tr[data-row-index="${index}"]`);
        bindRow(row);

        if (item) {
            const topicSelect = row.querySelector('.js-topic-select');
            const subtopicSelect = row.querySelector('.js-subtopic-select');
            if (topicSelect && item.temario_point_id) {
                topicSelect.value = String(item.temario_point_id);
            }
            filterSubtopics(row);
            if (subtopicSelect && Array.isArray(item.temario_subtopic_ids)) {
                const ids = item.temario_subtopic_ids.map((id) => String(id));
                Array.from(subtopicSelect.options).forEach((opt) => {
                    opt.selected = ids.includes(opt.value);
                });
            }

            const setText = (sel, val) => {
                const el = row.querySelector(sel);
                if (el) el.value = val || '';
            };
            setText('textarea[name*="[opening]"]', item.opening);
            setText('textarea[name*="[development]"]', item.development);
            setText('textarea[name*="[closing]"]', item.closing);
            setMultiSelectValues(row, '.js-resource-select', '.js-resource-hidden', item.resources);
            setMultiSelectValues(row, '.js-evaluation-select', '.js-evaluation-hidden', item.evaluation);
            const startInput = row.querySelector('.js-date-start');
            const endInput = row.querySelector('.js-date-end');
            if (startInput) startInput.value = item.start_date || '';
            if (endInput) endInput.value = item.end_date || '';
        }

        index += 1;
        return row;
    }

    function loadUnitForEditing(idx) {
        const block = unitBlocks[idx];
        if (!block) return;

        const firstItem = Array.isArray(block.items) && block.items.length ? block.items[0] : null;
        fieldTrainingSelect.value = String(
            (firstItem && firstItem.field_training_point_id) || block.field_training_point_id || ''
        );
        previousFieldTrainingValue = fieldTrainingSelect.value;
        unitObjectiveInput.value = (firstItem && firstItem.objective) || block.objective || '';

        tableBody.innerHTML = '';
        index = 0;
        const items = Array.isArray(block.items) && block.items.length ? block.items : [{}];
        items.forEach((item) => createRow(item));
        tableBody.querySelectorAll('tr').forEach((row) => {
            filterTopicsByUnit(row);
            filterSubtopics(row);
            applyCycleLimitsToRow(row);
        });
    }

    function renderTempUnits() {
        if (unitsTempCounter) {
            const total = unitBlocks.length;
            unitsTempCounter.textContent = `${total} ${total === 1 ? 'unidad' : 'unidades'} en temporal`;
        }
        if (unitsTempBody) {
            const rowsHtml = unitBlocks.map((block, idx) => `
                <tr>
                    <td>${block.field_training_text || unitTextById(block.field_training_point_id)}</td>
                    <td>${block.objective || '-'}</td>
                    <td class="text-center">${(block.items || []).length}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-warning js-edit-unit" data-index="${idx}">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger js-delete-unit" data-index="${idx}">Quitar</button>
                    </td>
                </tr>
            `).join('');
            unitsTempBody.innerHTML = rowsHtml;
            if (!rowsHtml && unitsTempEmpty) {
                unitsTempBody.appendChild(unitsTempEmpty);
            }
        }
        if (unitsPayloadInput) {
            const flatItems = unitBlocks.flatMap((block) =>
                (block.items || []).map((item) => ({
                    ...item,
                    field_training_point_id: item.field_training_point_id || block.field_training_point_id,
                    objective: typeof item.objective !== 'undefined' ? item.objective : block.objective,
                }))
            );
            unitsPayloadInput.value = flatItems.length ? JSON.stringify(flatItems) : '';
        }
        refreshWeekHighlights();
    }

    function saveCurrentUnitToTemp(showAlerts = true) {
        const unitId = fieldTrainingSelect?.value || '';
        const objective = (unitObjectiveInput?.value || '').trim();
        const items = collectCurrentRows();

        if (!unitId) {
            if (showAlerts) alert('Selecciona un campo formativo para guardar la unidad.');
            return false;
        }

        if (!items.length) {
            if (showAlerts) alert('Agrega al menos un renglon antes de guardar la unidad.');
            return false;
        }

        const invalid = items.find((item) => !item.temario_point_id || !item.start_date || !item.end_date);
        if (invalid) {
            if (showAlerts) alert('Cada renglon debe tener tema, fecha de inicio y fecha de termino.');
            return false;
        }

        const unitIdInt = parseInt(unitId, 10);
        const objectiveValue = objective !== '' ? objective : null;
        const normalizedItems = items.map((item) => ({
            ...item,
            field_training_point_id: unitIdInt,
            objective: objectiveValue,
        }));

        const payload = {
            field_training_point_id: unitIdInt,
            field_training_text: unitTextById(unitId),
            objective: objectiveValue,
            items: normalizedItems,
        };

        if (editingUnit) {
            const editIdx = parseInt(saveUnitBtn.getAttribute('data-edit-index') || '-1', 10);
            if (editIdx >= 0 && editIdx < unitBlocks.length) {
                unitBlocks[editIdx] = payload;
            } else {
                unitBlocks.push(payload);
            }
        } else {
            unitBlocks.push(payload);
        }

        editingUnit = false;
        saveUnitBtn.removeAttribute('data-edit-index');
        saveUnitBtn.textContent = '+ Guardar unidad en tabla temporal';

        renderTempUnits();
        clearCurrentRows();
        return true;
    }

    function selectedCycle() {
        if (!cycleSelect) return null;
        const selectedId = parseInt(cycleSelect.value || '0', 10);
        if (!selectedId) return null;
        return cycles.find((c) => parseInt(c.id, 10) === selectedId) || null;
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '-';
        const [y,m,d] = dateStr.split('-');
        if (!y || !m || !d) return dateStr;
        return `${d}/${m}/${y}`;
    }

    function fmtShortStart(dateStr) {
        if (!dateStr) return '-';
        const [y,m,d] = dateStr.split('-');
        if (!y || !m || !d) return dateStr;
        return `${parseInt(d, 10)}/${parseInt(m, 10)}`;
    }

    function fmtShortEnd(dateStr) {
        if (!dateStr) return '-';
        const [y,m,d] = dateStr.split('-');
        if (!y || !m || !d) return dateStr;
        return `${parseInt(d, 10)}/${parseInt(m, 10)}/${y.slice(-2)}`;
    }

    function buildWeeks(startStr, endStr) {
        const rows = [];
        const start = new Date(startStr + 'T00:00:00');
        const end = new Date(endStr + 'T00:00:00');
        if (isNaN(start.getTime()) || isNaN(end.getTime()) || start > end) return rows;

        let currentStart = new Date(start);
        let i = 1;
        while (currentStart <= end) {
            const currentEnd = new Date(currentStart);
            currentEnd.setDate(currentEnd.getDate() + 6);
            if (currentEnd > end) currentEnd.setTime(end.getTime());

            const s = currentStart.toISOString().slice(0, 10);
            const e = currentEnd.toISOString().slice(0, 10);
            rows.push({ week: i, start: s, end: e });

            currentStart = new Date(currentEnd);
            currentStart.setDate(currentStart.getDate() + 1);
            i += 1;
        }
        return rows;
    }

    function toLocalDate(dateStr) {
        if (!dateStr) return null;
        const [y, m, d] = String(dateStr).split('-').map((v) => parseInt(v, 10));
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }

    function normalizeDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) return null;
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function weekIsSelected(week) {
        const weekStart = normalizeDate(toLocalDate(week.start));
        const weekEnd = normalizeDate(toLocalDate(week.end));
        if (!weekStart || !weekEnd) return false;

        return unitBlocks.some((block) =>
            (block.items || []).some((item) => {
                const itemStart = normalizeDate(toLocalDate(item.start_date));
                const itemEnd = normalizeDate(toLocalDate(item.end_date));
                if (!itemStart || !itemEnd) return false;
                return itemStart <= weekEnd && itemEnd >= weekStart;
            })
        );
    }

    function refreshWeekHighlights() {
        if (!Array.isArray(renderedWeeks) || !renderedWeeks.length) {
            return;
        }

        weeksRanges.innerHTML = renderedWeeks
            .map((w) => {
                const selected = weekIsSelected(w);
                const badgeClass = selected ? 'badge-success' : 'badge-info';
                const title = selected ? 'Semana ya planificada en temporal' : 'Semana disponible';
                return `<span class="badge ${badgeClass} mr-1 mb-1" title="${title}">${fmtShortStart(w.start)} a ${fmtShortEnd(w.end)}</span>`;
            })
            .join('');
    }

    function applyCycleLimitsToRow(row) {
        const cycle = selectedCycle();
        const startInput = row.querySelector('.js-date-start');
        const endInput = row.querySelector('.js-date-end');
        if (!startInput || !endInput) return;

        if (!cycle || !cycle.start_date || !cycle.end_date) {
            startInput.removeAttribute('min');
            startInput.removeAttribute('max');
            endInput.removeAttribute('min');
            endInput.removeAttribute('max');
            return;
        }

        startInput.min = cycle.start_date;
        startInput.max = cycle.end_date;
        endInput.min = cycle.start_date;
        endInput.max = cycle.end_date;
    }

    function refreshCycleInfo() {
        const cycle = selectedCycle();

        if (!cycle || !cycle.start_date || !cycle.end_date) {
            rangeText.textContent = 'Selecciona un ciclo para ver el rango permitido.';
            weeksBox.style.display = 'none';
            weeksRanges.innerHTML = '';
            tableBody.querySelectorAll('tr').forEach(applyCycleLimitsToRow);
            return;
        }

        rangeText.textContent = `${fmtDate(cycle.start_date)} a ${fmtDate(cycle.end_date)}`;
        renderedWeeks = buildWeeks(cycle.start_date, cycle.end_date);
        refreshWeekHighlights();
        weeksBox.style.display = renderedWeeks.length ? '' : 'none';

        tableBody.querySelectorAll('tr').forEach(applyCycleLimitsToRow);
    }

    addBtn.addEventListener('click', () => createRow());

    if (saveUnitBtn) {
        saveUnitBtn.addEventListener('click', () => {
            saveCurrentUnitToTemp(true);
        });
    }

    if (fieldTrainingSelect) {
        fieldTrainingSelect.addEventListener('change', () => {
            const currentValue = fieldTrainingSelect.value || '';
            const rows = collectCurrentRows();

            if (rows.length && currentValue !== previousFieldTrainingValue) {
                const shouldClear = confirm('Detecte renglones capturados. ¿Quieres limpiarlos al cambiar la unidad?');
                if (shouldClear) {
                    clearCurrentRows();
                }
            }

            previousFieldTrainingValue = fieldTrainingSelect.value || '';
            if (unitObjectiveInput) {
                unitObjectiveInput.value = unitObjectiveById(currentValue);
            }
            tableBody.querySelectorAll('tr').forEach((row) => {
                filterTopicsByUnit(row);
                filterSubtopics(row);
            });
        });
    }

    if (unitsTempBody) {
        unitsTempBody.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.js-edit-unit');
            if (editBtn) {
                const rows = collectCurrentRows();
                if (rows.length) {
                    alert('Guarda o limpia primero los renglones actuales antes de editar otra unidad.');
                    return;
                }
                const idx = parseInt(editBtn.getAttribute('data-index') || '-1', 10);
                if (idx >= 0 && idx < unitBlocks.length) {
                    editingUnit = true;
                    saveUnitBtn.setAttribute('data-edit-index', String(idx));
                    saveUnitBtn.textContent = 'Guardar cambios de unidad';
                    loadUnitForEditing(idx);
                }
                return;
            }

            const delBtn = event.target.closest('.js-delete-unit');
            if (delBtn) {
                const idx = parseInt(delBtn.getAttribute('data-index') || '-1', 10);
                if (idx >= 0 && idx < unitBlocks.length) {
                    unitBlocks.splice(idx, 1);
                    if (editingUnit) {
                        editingUnit = false;
                        saveUnitBtn.removeAttribute('data-edit-index');
                        saveUnitBtn.textContent = '+ Guardar unidad en tabla temporal';
                    }
                    renderTempUnits();
                }
            }
        });
    }

    if (cycleSelect) {
        if (initialCycleId && !cycleSelect.value) {
            cycleSelect.value = initialCycleId;
        }
        if (!cycleSelect.value && cycles.length > 0) {
            const active = cycles.find((c) => c.is_active);
            cycleSelect.value = String((active || cycles[0]).id);
        }
        cycleSelect.addEventListener('change', refreshCycleInfo);
    }

    if (tableBody.querySelectorAll('tr').length === 0) {
        createRow();
    } else {
        tableBody.querySelectorAll('tr').forEach(bindRow);
    }

    if (fieldTrainingSelect && unitObjectiveInput && fieldTrainingSelect.value && !unitObjectiveInput.value) {
        unitObjectiveInput.value = unitObjectiveById(fieldTrainingSelect.value);
    }

    refreshCycleInfo();
    renderTempUnits();

    if (form) {
        form.addEventListener('submit', (event) => {
            const rows = collectCurrentRows();
            if (rows.length) {
                const saved = saveCurrentUnitToTemp(false);
                if (!saved) {
                    event.preventDefault();
                    alert('Falta seleccionar campo formativo o completar renglones antes de guardar la planeacion.');
                    return;
                }
            }

            const flatItems = unitBlocks.flatMap((block) =>
                (block.items || []).map((item) => ({
                    ...item,
                    field_training_point_id: item.field_training_point_id || block.field_training_point_id,
                    objective: typeof item.objective !== 'undefined' ? item.objective : block.objective,
                }))
            );

            if (!flatItems.length) {
                event.preventDefault();
                alert('Agrega al menos una unidad en la tabla temporal antes de guardar.');
                return;
            }

            if (unitsPayloadInput) {
                unitsPayloadInput.value = JSON.stringify(flatItems);
            }
        });
    }
})();
</script>
@endsection
