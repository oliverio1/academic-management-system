@extends('layouts.app')

@section('title', 'Promocion de ciclo')

@section('content')
<div class="content px-3">
    @if(session('info'))
        <div class="alert alert-success mt-3">
            {{ session('info') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mt-3">
            {{ session('error') }}
        </div>
    @endif

    @if(!empty($errorMessage))
        <div class="alert alert-danger mt-3">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Promocion automatica de ciclo</h4>
                </div>
                <div class="card-body">
                    @php
                        $modalities = $cycles->flatMap(fn ($cycle) => $cycle->modalities)->unique('id')->sortBy('name')->values();
                    @endphp
                    <form method="POST" action="{{ route('coordination.cycle-promotions.preview') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="source_cycle_id">Ciclo origen</label>
                                <select class="form-control @error('source_cycle_id') is-invalid @enderror" name="source_cycle_id" id="source_cycle_id" required>
                                    <option value="">Seleccione ciclo origen</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}" {{ (string) old('source_cycle_id', $sourceCycleId) === (string) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }}) - {{ $cycle->modalities->pluck('name')->implode(', ') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('source_cycle_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-5 mb-3">
                                <label for="target_cycle_id">Ciclo destino</label>
                                <select class="form-control @error('target_cycle_id') is-invalid @enderror" name="target_cycle_id" id="target_cycle_id" required>
                                    <option value="">Seleccione ciclo destino</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}" {{ (string) old('target_cycle_id', $targetCycleId) === (string) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }}) - {{ $cycle->modalities->pluck('name')->implode(', ') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_cycle_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-10 mb-3">
                                <label for="modality_id">Modalidad para promocion</label>
                                <select class="form-control @error('modality_id') is-invalid @enderror" name="modality_id" id="modality_id" required>
                                    <option value="">Seleccione modalidad</option>
                                    @foreach($modalities as $modality)
                                        <option value="{{ $modality->id }}" {{ (string) old('modality_id', $selectedModalityId ?? '') === (string) $modality->id ? 'selected' : '' }}>
                                            {{ $modality->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('modality_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Vista previa</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if($summary)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-2"><strong>Total:</strong> {{ $summary['total'] }}</div>
                            <div class="col-md-2 text-success"><strong>Listos:</strong> {{ $summary['ready'] }}</div>
                            <div class="col-md-3 text-warning"><strong>Sin nivel siguiente:</strong> {{ $summary['without_next_level'] }}</div>
                            <div class="col-md-3 text-warning"><strong>Sin grupo destino:</strong> {{ $summary['without_target_group'] }}</div>
                            <div class="col-md-2 text-danger"><strong>Sin grupo:</strong> {{ $summary['without_group'] }}</div>
                        </div>

                        @if($previewRows->count() > 0 && $sourceCycleId && $targetCycleId)
                            <hr>
                            <form method="POST" action="{{ route('coordination.cycle-promotions.execute') }}" onsubmit="return confirm('Se aplicara la promocion de ciclo. Deseas continuar?');">
                                @csrf
                                <input type="hidden" name="source_cycle_id" value="{{ $sourceCycleId }}">
                                <input type="hidden" name="target_cycle_id" value="{{ $targetCycleId }}">
                                <input type="hidden" name="modality_id" value="{{ $selectedModalityId }}">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Matricula</th>
                                                <th>Alumno</th>
                                                <th>Nivel actual</th>
                                                <th>Grupo actual</th>
                                                <th>Nivel destino</th>
                                                <th>Grupo destino</th>
                                                <th>Grupo para promover</th>
                                                <th>Grupo para repetir</th>
                                                <th>Reprobadas</th>
                                                <th>Regla</th>
                                                <th>Estatus</th>
                                                <th>Accion</th>
                                                <th>Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($previewRows as $row)
                                                @php
                                                    $defaultAction = $row['default_action'] ?? 'skip';
                                                @endphp
                                                <tr>
                                                    <td>{{ $row['enrollment_number'] ?: '---' }}</td>
                                                    <td>{{ $row['student_name'] }}</td>
                                                    <td>{{ $row['current_level'] ?? '---' }}</td>
                                                    <td>{{ $row['current_group'] ?? '---' }}</td>
                                                    <td>{{ $row['next_level'] ?? '---' }}</td>
                                                    <td>{{ $row['target_group'] ?? '---' }}</td>
                                                    <td>
                                                        @php
                                                            $promoteOptions = collect($row['promote_group_options'] ?? []);
                                                            $defaultPromote = (int) ($row['default_promote_group_id'] ?? 0);
                                                        @endphp
                                                        @if($promoteOptions->isEmpty())
                                                            ---
                                                        @else
                                                            <select name="promote_groups[{{ $row['student_id'] }}]" class="form-control form-control-sm">
                                                                @if($promoteOptions->count() > 1)
                                                                    <option value="">Seleccionar grupo</option>
                                                                @endif
                                                                @foreach($promoteOptions as $option)
                                                                    <option value="{{ $option['id'] }}" {{ $defaultPromote === (int) $option['id'] ? 'selected' : '' }}>
                                                                        {{ $option['name'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            $repeatOptions = collect($row['repeat_group_options'] ?? []);
                                                            $defaultRepeat = (int) ($row['default_repeat_group_id'] ?? 0);
                                                        @endphp
                                                        @if($repeatOptions->isEmpty())
                                                            ---
                                                        @else
                                                            <select name="repeat_groups[{{ $row['student_id'] }}]" class="form-control form-control-sm">
                                                                @if($repeatOptions->count() > 1)
                                                                    <option value="">Seleccionar grupo</option>
                                                                @endif
                                                                @foreach($repeatOptions as $option)
                                                                    <option value="{{ $option['id'] }}" {{ $defaultRepeat === (int) $option['id'] ? 'selected' : '' }}>
                                                                        {{ $option['name'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </td>
                                                    <td>{{ $row['failed_subjects'] ?? 0 }}</td>
                                                    <td><small>{{ $row['promotion_rule'] ?? '---' }}</small></td>
                                                    <td>
                                                        @if($row['status'] === 'listo')
                                                            <span class="badge badge-success">Listo</span>
                                                        @elseif($row['status'] === 'sin_nivel_siguiente')
                                                            <span class="badge badge-warning">Sin nivel siguiente</span>
                                                        @elseif($row['status'] === 'sin_grupo_destino')
                                                            <span class="badge badge-warning">Sin grupo destino</span>
                                                        @else
                                                            <span class="badge badge-danger">Incompleto</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <select name="actions[{{ $row['student_id'] }}]" class="form-control form-control-sm">
                                                            @if($row['status'] === 'listo' && ($row['default_action'] ?? '') === 'promote')
                                                                <option value="promote" {{ $defaultAction === 'promote' ? 'selected' : '' }}>Promover</option>
                                                            @endif
                                                            <option value="repeat" {{ $defaultAction === 'repeat' ? 'selected' : '' }}>Repetir</option>
                                                            @if($row['status'] === 'sin_nivel_siguiente')
                                                                <option value="graduate" {{ $defaultAction === 'graduate' ? 'selected' : '' }}>Egresar</option>
                                                            @endif
                                                            <option value="skip" {{ $defaultAction === 'skip' ? 'selected' : '' }}>Omitir</option>
                                                        </select>
                                                    </td>
                                                    <td>{{ $row['message'] }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="13" class="text-center text-muted py-4">No hay alumnos para esta vista previa.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success">Ejecutar promocion</button>
                                </div>
                            </form>                          
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
