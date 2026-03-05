<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>BLAB | Ingreso</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
        <link rel="stylesheet" href="{{ asset('admin/plugins/fontawesome-free/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/dist/css/adminlte.min.css') }}">
    </head>
    <body class="hold-transition login-page">
        <div class="login-box">
            <div class="login-logo">
                <a href="{{ url('/home') }}"><b>OlicaTi!</b></a>
            </div>
            <div class="card bg-light mb-3">
                <div class="card-header">
                    <h6 class="login-box-msg mt-4"><strong>Ingresa tus credenciales</strong></h6>
                </div>
                <div class="card-body login-card-body">
                    <form method="post" action="{{ url('/login') }}">
                        @csrf
                        <div class="input-group mb-3">
                            <input type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="Correo electrónico"
                                class="form-control @error('email') is-invalid @enderror">
                            <div class="input-group-append">
                                <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                            </div>
                            @error('email')
                                <span class="error invalid-feedback">Las credenciales ingresadas son incorrectas</span>
                            @enderror
                        </div>
                        <div class="input-group mb-3">
                            <input type="password"
                                name="password"
                                placeholder="Contraseña"
                                class="form-control @error('password') is-invalid @enderror">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock"></span>
                                </div>
                            </div>
                            @error('password')
                                <span class="error invalid-feedback">Las credenciales ingresadas son incorrectas</span>
                            @enderror
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-info btn-block">Ingresar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script src="{{ asset('admin/plugins/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('admin/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('admin/dist/js/adminlte.min.js') }}"></script>
    </body>
</html>
