<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>BLAB | Cambiar contraseña</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
        <link rel="stylesheet" href="{{ asset('admin/plugins/fontawesome-free/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/dist/css/adminlte.min.css') }}">
    </head>
    <body class="hold-transition login-page">
        <div class="login-box" style="width: 430px; max-width: 92vw;">
            <div class="login-logo">
                <a href="{{ route('temporary-password.edit') }}"><b>OlicaTi!</b></a>
            </div>
            <div class="card bg-light mb-3 shadow-sm">
                <div class="card-header text-center">
                    <h5 class="mb-1"><strong>Actualiza tu contraseña</strong></h5>
                    <p class="mb-0 text-muted small">Por seguridad, no puedes continuar usando la contraseña temporal.</p>
                </div>
                <div class="card-body login-card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="post" action="{{ route('temporary-password.update') }}">
                        @csrf
                        @method('PUT')

                        <label for="current_password" class="small font-weight-bold">Contraseña actual</label>
                        <div class="input-group mb-3">
                            <input id="current_password"
                                type="password"
                                name="current_password"
                                placeholder="Contraseña temporal"
                                autocomplete="current-password"
                                class="form-control @error('current_password') is-invalid @enderror">
                            <div class="input-group-append">
                                <div class="input-group-text"><span class="fas fa-lock"></span></div>
                            </div>
                        </div>

                        <label for="password" class="small font-weight-bold">Nueva contraseña</label>
                        <div class="input-group mb-3">
                            <input id="password"
                                type="password"
                                name="password"
                                placeholder="Mínimo 8 caracteres"
                                autocomplete="new-password"
                                class="form-control @error('password') is-invalid @enderror">
                            <div class="input-group-append">
                                <div class="input-group-text"><span class="fas fa-key"></span></div>
                            </div>
                        </div>

                        <label for="password_confirmation" class="small font-weight-bold">Confirmar nueva contraseña</label>
                        <div class="input-group mb-3">
                            <input id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                placeholder="Repite la nueva contraseña"
                                autocomplete="new-password"
                                class="form-control">
                            <div class="input-group-append">
                                <div class="input-group-text"><span class="fas fa-check"></span></div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-info btn-block">Guardar nueva contraseña</button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                        @csrf
                        <button type="submit" class="btn btn-link text-muted p-0">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
        <script src="{{ asset('admin/plugins/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('admin/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('admin/dist/js/adminlte.min.js') }}"></script>
    </body>
</html>
