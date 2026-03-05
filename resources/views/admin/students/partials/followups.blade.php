@if($followUps->isEmpty())
    <p class="text-muted">No hay seguimientos registrados.</p>
@else
<table class="table table-sm table-hover">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Solicitado por</th>
            <th>Estado</th>
            <th>Profesores</th>
        </tr>
    </thead>
    <tbody>
        @foreach($followUps as $item)
            <tr>
                <td>{{ $item->created_at->format('d/m/Y') }}</td>
                <td>{{ ucfirst($item->type) }}</td>
                <td>{{ $item->requester->name ?? '—' }}</td>
                <td>
                    <span class="badge badge-{{ $item->isCompleted() ? 'success' : 'warning' }}">
                        {{ $item->isCompleted() ? 'Completado' : 'En proceso' }}
                    </span>
                </td>
                <td>
                    @foreach($item->teachers as $assignment)
                        <span class="badge badge-secondary d-block mb-1">
                            {{ $assignment->teacher->user->name ?? '—' }}
                            <small>({{ $assignment->status }})</small>
                        </span>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
