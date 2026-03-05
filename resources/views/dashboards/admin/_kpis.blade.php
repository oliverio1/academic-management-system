<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $stats['groups'] }}</h3>
                <p>Grupos</p>
            </div>
            <div class="icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <a href="{{ route('groups.index') }}" class="small-box-footer">
                Gestionar <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $stats['students'] }}</h3>
                <p>Alumnos</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <a href="{{ route('students.index') }}" class="small-box-footer">
                Ver alumnos <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['teachers'] }}</h3>
                <p>Profesores</p>
            </div>
            <div class="icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <a href="{{ route('teachers.index') }}" class="small-box-footer">
                Ver profesores <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['subjects'] }}</h3>
                <p>Materias</p>
            </div>
            <div class="icon">
                <i class="fas fa-book"></i>
            </div>
            <a href="{{ route('subjects.index') }}" class="small-box-footer">
                Ver materias <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

</div>
