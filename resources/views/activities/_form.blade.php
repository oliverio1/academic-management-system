<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Nombre de la actividad</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $activity->title ?? '') }}" required>
        </div>
    </div>

    <div class="col-md-3">
        <div class="form-group">
            <label>Tipo de evaluación</label>
            <select name="evaluation_criterion_id" class="form-control" required>
                <option value="">Seleccione…</option>
                @foreach($assignment->evaluationCriteria as $criterion)
                    <option value="{{ $criterion->id }}"
                        @selected(old('evaluation_criterion_id', $activity->evaluation_criterion_id ?? null) == $criterion->id)>
                        {{ $criterion->name }} ({{ $criterion->percentage }}%)
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-md-3">
        <div class="form-group">
            <label>Calificación máxima</label>
            <input type="number" step="1.0" min="0" max="10" name="max_score" class="form-control" value="{{ old('max_score', $activity->max_score ?? '') }}" required>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label>Periodo académico</label>
            <select name="academic_period_id" class="form-control" required>
                <option value="">Seleccione un periodo</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">
                        {{ $period->name }} ({{ $period->start_date }} – {{ $period->end_date }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label>Fecha de entrega</label>
            <input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($activity->due_date ?? null)->format('Y-m-d')) }}" required>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $activity->description ?? '') }}</textarea>
        </div>
    </div>
</div>
