@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3"><strong>{{ session('success') }}</strong></div>
    @endif

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $summary['items'] }}</h3>
                    <p>Articulos</p>
                </div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $summary['low_stock'] }}</h3>
                    <p>Stock bajo</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $summary['reactives'] }}</h3>
                    <p>Reactivos</p>
                </div>
                <div class="icon"><i class="fas fa-flask"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $summary['equipment'] }}</h3>
                    <p>Equipos</p>
                </div>
                <div class="icon"><i class="fas fa-microscope"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">Inventario institucional</h4>
                </div>
                <div class="col-md-6 text-right">
                    <a href="{{ route('coordination.inventory.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo articulo
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3">
                <div class="col-md-3">
                    <select name="type" class="form-control">
                        <option value="">Todos los tipos</option>
                        @foreach($itemTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">Todos los estatus</option>
                        @foreach(['disponible' => 'Disponible', 'prestado' => 'Prestado', 'mantenimiento' => 'Mantenimiento', 'agotado' => 'Agotado', 'baja' => 'Baja'] as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="low_stock" {{ request('low_stock') ? 'checked' : '' }}>
                        <label for="low_stock" class="form-check-label">Solo stock bajo</label>
                    </div>
                </div>
                <div class="col-md-3 text-right">
                    <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="{{ route('coordination.inventory.index') }}" class="btn btn-light">Limpiar</a>
                </div>
            </form>

            <table data-datatable="true" id="inventory" class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Articulo</th>
                        <th>Tipo</th>
                        <th>Ubicacion</th>
                        <th>Existencia</th>
                        <th>Minimo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr class="{{ $item->is_low_stock ? 'table-warning' : '' }}">
                            <td>{{ $item->code ?: '-' }}</td>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                <div class="text-muted small">{{ $item->category->name ?? 'Sin categoria' }}</div>
                            </td>
                            <td>{{ $itemTypes[$item->item_type] ?? $item->item_type }}</td>
                            <td>{{ $item->location->name ?? 'Sin ubicacion' }}</td>
                            <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td>{{ number_format((float) $item->minimum_quantity, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $item->status === 'disponible' ? 'success' : ($item->status === 'agotado' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('coordination.inventory.show', $item) }}" class="btn btn-primary btn-sm" title="Ver movimientos">
                                    <i class="far fa-eye"></i>
                                </a>
                                <a href="{{ route('coordination.inventory.edit', $item) }}" class="btn btn-warning btn-sm" title="Editar">
                                    <i class="far fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    $(document).ready(function () {
        $('#inventory').DataTable({
            dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
            order: [[1, "asc"]],
            buttons: ['excelHtml5', 'pdfHtml5'],
            language: { url: '/datatables.json' }
        });
    });
</script>
@endsection
