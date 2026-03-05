<!DOCTYPE html>
    <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>
                @yield('title')
            </title>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
            <link rel="stylesheet" href="{{ asset('admin/plugins/fontawesome-free/css/all.min.css') }}">
            <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
            <link rel="stylesheet" href="{{ asset('admin/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/plugins/jqvmap/jqvmap.min.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/dist/css/adminlte.min.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/plugins/daterangepicker/daterangepicker.css') }}">
            <link rel="stylesheet" href="{{ asset('admin/plugins/summernote/summernote-bs4.min.css') }}">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            {{-- Datatables --}}
            <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css"/>
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4-4.6.0/dt-1.12.1/b-2.2.3/b-html5-2.2.3/datatables.min.css"/>
            {{-- Select2 --}}
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
            {{-- CKEditor4 --}}
            <script src="https://raw.githubusercontent.com/unisharp/laravel-ckeditor/master/vendor/unisharp/laravel-ckeditor/ckeditor.js"></script>

            {{-- FullCalendar --}}
            <link rel="stylesheet" href="{{ asset('admin/plugins/fullcalendar/main.min.css') }}">
            {{-- CKEditor --}}
            <style>
                .ck-editor__editable_inline {
                    min-height: 300px;
                }
                .nav-sidebar .nav-link {
                    border-radius: .5rem;
                    margin: 2px 6px;
                }

                /* Activo más claro */
                .nav-sidebar .nav-link.active {
                    background-color: rgba(0,123,255,.15);
                    color: #0d6efd;
                    font-weight: 500;
                }

                /* Submenú más compacto */
                .nav-treeview > .nav-item > .nav-link {
                    padding-left: 2.5rem;
                    font-size: .95rem;
                }

                /* Íconos más sutiles */
                .nav-icon {
                    opacity: .85;
                }
            </style>
            @stack('third_party_stylesheets')
            @yield('page_css')
        </head>
        <body class="hold-transition sidebar-mini layout-fixed">
            <div class="wrapper">
                <div class="preloader flex-column justify-content-center align-items-center">
                    <img class="animation__shake" src="{{ asset('logotext.png') }}" alt="AdminLTELogo" width="150">
                </div>
                <!-- Navbar -->
                @include('layouts.navbar')
                {{-- Sidebar --}}
                @include('layouts.sidebar')
                {{-- Content --}}
                <div class="content-wrapper">
                @yield('content')
                </div>
                {{-- Footer --}}
                <footer class="main-footer">
                    <strong>Copyright &copy; 2014-2024 OliCati!.</strong>
                    All rights reserved.
                    <div class="float-right d-none d-sm-inline-block">
                        <b>Version</b> 4.1.0
                    </div>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </footer>
            </div>
            <script src="{{ asset('admin/plugins/jquery/jquery.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/jquery-ui/jquery-ui.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/chart.js/Chart.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/sparklines/sparkline.js') }}"></script>
            {{-- <script src="{{ asset('plugins/jqvmap/jquery.vmap.min.js') }}"></script> --}}
            {{-- <script src="{{ asset('plugins/jqvmap/maps/jquery.vmap.usa.js') }}"></script> --}}
            <script src="{{ asset('admin/plugins/jquery-knob/jquery.knob.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/moment/moment.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/daterangepicker/daterangepicker.js') }}"></script>
            <script src="{{ asset('admin/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/summernote/summernote-bs4.min.js') }}"></script>
            <script src="{{ asset('admin/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
            <script src="{{ asset('admin/dist/js/adminlte.js') }}"></script>
            {{-- <script src="{{ asset('dist/js/demo.js') }}"></script> --}}
            {{-- Datatables --}}
            <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
            <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
            <script type="text/javascript" src="https://cdn.datatables.net/v/bs4-4.6.0/dt-1.12.1/b-2.2.3/b-html5-2.2.3/datatables.min.js"></script>
            <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.min.js"></script>
            {{-- Select2 --}}
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            {{-- CKEDITOR --}}
            <script src="https://cdn.ckeditor.com/ckeditor5/37.1.0/classic/ckeditor.js"></script>
            {{-- FullCalendar --}}
            <script src="{{ asset('admin/plugins/fullcalendar/main.min.js') }}"></script>
            <script>
                const INACTIVITY_TIME = 1800000;
                let inactivityTimer;
                function resetInactivityTimer() {
                    clearTimeout(inactivityTimer);
                    inactivityTimer = setTimeout(() => {
                        logoutUser ();
                    }, INACTIVITY_TIME);
                }
                function logoutUser () {
                    const logoutForm = document.getElementById('logout-form');
                    if (logoutForm) {
                        logoutForm.submit();
                        setTimeout(() => {
                            window.location.href = '/login';
                        }, 1000);
                    }
                }
                function setupActivityListeners() {
                    const events = ['mousemove', 'keypress', 'scroll', 'click'];
                    events.forEach(event => {
                        document.addEventListener(event, resetInactivityTimer);
                    });
                }
                window.onload = () => {
                    resetInactivityTimer();
                    setupActivityListeners();
                };
            </script>
            @stack('third_party_scripts')
            @yield('page_scripts')
        </body>
    </html>
