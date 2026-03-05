<table class="table table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Nombre</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($days as $day)
            <tr>
                <td>{{ $day->date->format('d/m/Y') }}</td>
                <td>{{ ucfirst($day->type) }}</td>
                <td>{{ $day->name }}</td>
                <td>
                    <form method="POST"
                          action="{{ route('academic-calendar-days.destroy', $day) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>