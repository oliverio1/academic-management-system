<nav class="main-header navbar navbar-expand navbar-white navbar-light">

    {{-- Izquierda --}}
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>

        <li class="nav-item d-none d-sm-inline-block">
            <span class="nav-link text-muted">
                @yield('page-title')
            </span>
        </li>
    </ul>

    {{-- Derecha --}}
    <ul class="navbar-nav ml-auto">
        {{-- Usuario --}}
        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <span class="d-none d-md-inline">
                    {{ Auth::user()->name }}
                </span>
            </a>

            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">

                <li class="user-header bg-secondary">
                    <p>
                        {{ Auth::user()->name }} <br>
                        <small>
                            {{ auth()->user()->role_label }}
                        </small>
                    </p>
                </li>

                <li class="user-footer">
                    <form action="{{ route('logout') }}" method="POST" class="w-100">
                        @csrf
                        <button class="btn btn-danger btn-block">
                            Cerrar sesión
                        </button>
                    </form>
                </li>

            </ul>
        </li>
    </ul>
</nav>
