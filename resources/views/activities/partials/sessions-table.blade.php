<div class="card-body table-responsive">

<table class="table table-bordered table-sm">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Rubro</th>
            <th>Max</th>
            <th>Descripcion</th>
            <th>Accion</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sessions as $date)
            @php
                $key = $date->toDateString();
                $activity = $activities[$key] ?? null;
            @endphp
            <tr data-date="{{ $key }}" data-activity-id="{{ $activity?->id }}">
                <td>{{ $date->format('d/m/Y') }}</td>

                <td>
                    <span class="cell-display">{{ $activity->title ?? '-' }}</span>
                    <input type="text"
                        class="form-control form-control-sm d-none cell-edit"
                        data-field="title"
                        value="{{ $activity->title ?? '' }}">
                </td>

                <td>
                    <span class="cell-display">{{ $activity?->evaluationCriterion?->name ?? '-' }}</span>
                    <select class="form-control form-control-sm d-none cell-edit"
                            data-field="evaluation_criterion_id">
                        <option value="">-</option>
                        @foreach($criteria as $c)
                            <option value="{{ $c->id }}" @selected($activity?->evaluation_criterion_id === $c->id)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </td>

                <td>
                    <span class="cell-display">{{ $activity->max_score ?? 10 }}</span>
                    <input type="number"
                        class="form-control form-control-sm d-none cell-edit"
                        data-field="max_score"
                        value="{{ $activity->max_score ?? 10 }}">
                </td>

                <td>
                    <span class="cell-display">{{ $activity->description ?? '-' }}</span>
                    <textarea class="form-control form-control-sm d-none cell-edit"
                            data-field="description">{{ $activity->description ?? '' }}</textarea>
                </td>

                <td>
                    <button class="btn btn-sm btn-outline-primary btn-edit">Editar</button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

</div>
