@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <h1>Edición de usuario</h1>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                @include('users._form', ['user' => $user,'mode' => 'edit'])
                <div class="card-footer text-right">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button class="btn btn-primary">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@push('page_scripts')
<script>
    function toggleRoleFields(role) {
        document.querySelectorAll('.role-fields').forEach(el => {
            el.classList.add('d-none');
        });

        if (role === 'student') {
            document.getElementById('student-fields')?.classList.remove('d-none');
        }

        if (role === 'teacher') {
            document.getElementById('teacher-fields')?.classList.remove('d-none');
        }

        if (role === 'coordination') {
            document.getElementById('coordination-fields')?.classList.remove('d-none');
        }
    }

    document.getElementById('role')?.addEventListener('change', function () {
        toggleRoleFields(this.value);
    });

    // Inicializar en edición
    toggleRoleFields(document.getElementById('role')?.value);
</script>
@endpush

@section('page_scripts')
<script>
    function toggleRoleFields(role) {
        document.querySelectorAll('.role-fields').forEach(el => {
            el.classList.add('d-none');
        });
        if (role === 'student') {
            document.getElementById('student-fields')?.classList.remove('d-none');
        }
        if (role === 'teacher') {
            document.getElementById('teacher-fields')?.classList.remove('d-none');
        }
        if (role === 'coordination') {
            document.getElementById('coordination-fields')?.classList.remove('d-none');
        }
    }
    document.getElementById('role')?.addEventListener('change', function () {
        toggleRoleFields(this.value);
    });
    toggleRoleFields(document.getElementById('role')?.value);
</script>
<script>
    (function () {
        'use strict'
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation')
    
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>
@endsection
