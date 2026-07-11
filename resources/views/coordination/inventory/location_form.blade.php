<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Nueva ubicacion</h5>
    </div>
    <form method="POST" action="{{ route('coordination.inventory.locations.store') }}">
        @csrf
        <div class="card-body">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="name" class="form-control" placeholder="Laboratorio de quimica">
            </div>
            <div class="form-group">
                <label>Tipo de area</label>
                <select name="area_type" class="form-control">
                    @foreach($areaTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Responsable</label>
                <input type="text" name="responsible" class="form-control">
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-secondary btn-block" type="submit">
                <i class="fas fa-map-marker-alt"></i> Agregar ubicacion
            </button>
        </div>
    </form>
</div>
