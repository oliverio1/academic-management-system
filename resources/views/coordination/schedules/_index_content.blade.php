@if($activeCycle)
    <div class="alert alert-info"><strong>Ciclo seleccionado:</strong> {{ $activeCycle->name }} ({{ $activeCycle->code }})
        @if($usesCyclePlanning)<span class="badge badge-primary ml-2">Usando planeación por ciclo</span>
        @else<span class="badge badge-secondary ml-2">Sin planeación activa: usando configuración global</span>@endif
    </div>
@else
    <div class="alert alert-warning">No hay ciclo activo. Se usará configuración global de grupos y materias.</div>
@endif
@if(session('info'))<div class="alert alert-success">{{ session('info') }}</div>@endif
@if(session('schedule_warnings'))<div class="alert alert-warning"><h6 class="mb-2"><strong>Horarios guardados con traslapes:</strong></h6><ul class="mb-0">@foreach((array) session('schedule_warnings') as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card mb-3"><div class="card-header"><strong>Filtros</strong></div><div class="card-body">
<form method="GET" action="{{ route('coordination.schedules.index') }}" id="scheduleFilterForm"><div class="row">
<div class="col-md-4 mb-2"><select name="school_cycle_id" id="school_cycle_id" class="form-control"><option value="">Todos los ciclos</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" {{ (string) ($filters['school_cycle_id'] ?? '') === (string) $cycle->id ? 'selected' : '' }}>{{ $cycle->name }} ({{ $cycle->code }})</option>@endforeach</select></div>
<div class="col-md-4 mb-2"><select name="group_id" class="form-control"><option value="">Todos los grupos</option>@foreach($groups as $group)<option value="{{ $group->id }}" {{ (string) ($filters['group_id'] ?? '') === (string) $group->id ? 'selected' : '' }}>{{ $group->name }}</option>@endforeach</select></div>
<div class="col-md-4 mb-2"><select name="teacher_id" class="form-control"><option value="">Todos los profesores</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" {{ (string) ($filters['teacher_id'] ?? '') === (string) $teacher->id ? 'selected' : '' }}>{{ $teacher->user->name }}</option>@endforeach</select></div>
<div class="col-md-4 mb-2"><button class="btn btn-outline-primary">Filtrar</button> <a href="{{ route('coordination.schedules.index') }}" class="btn btn-outline-secondary">Limpiar</a></div>
</div></form></div></div>

<div class="card"><div class="card-body table-responsive p-3"><table class="table table-hover mb-0"><thead><tr><th>Grupo</th><th>Materia</th><th>Profesor</th><th>Sección</th><th>Ciclo</th><th>Día</th><th>Inicio</th><th>Fin</th><th>Tipo</th><th style="width:170px;">Acciones</th></tr></thead><tbody>@forelse($schedules as $schedule)<tr><td>{{ $schedule->assignment->group->name }}</td><td>{{ $schedule->assignment->subject->name }}</td><td>{{ $schedule->assignment->teacher->user->name }}</td><td>{{ (int) ($schedule->section_number ?: ($schedule->assignment->section_number ?? 1)) }}</td><td>@if($schedule->schoolCycle){{ $schedule->schoolCycle->name }} ({{ $schedule->schoolCycle->code }})@else-@endif</td><td>{{ $dayOptions[$schedule->day_of_week] ?? ucfirst($schedule->day_of_week) }}</td><td>{{ \Illuminate\Support\Str::of($schedule->start_time)->substr(0, 5) }}</td><td>{{ \Illuminate\Support\Str::of($schedule->end_time)->substr(0, 5) }}</td><td>{{ $schedule->type ?: '-' }}</td><td><a href="{{ route('coordination.schedules.edit', $schedule) }}" class="btn btn-sm btn-warning">Editar</a> <form action="{{ route('coordination.schedules.destroy', $schedule) }}" method="POST" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" onclick="return confirm('Deseas eliminar este horario?')">Eliminar</button></form></td></tr>@empty<tr><td colspan="10" class="text-center text-muted py-4">No hay horarios registrados.</td></tr>@endforelse</tbody></table></div></div>

