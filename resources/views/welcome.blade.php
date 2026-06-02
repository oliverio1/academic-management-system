<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Sistema Escolar - Inicio</title>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic,700italic" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800" rel="stylesheet" type="text/css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet" />
        <style>
            :root {
                --brand-1: #0f766e;
                --brand-2: #155e75;
                --text-main: #0f172a;
                --text-muted: #475569;
            }

            .announcements-wrap {
                background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 45%, #ffffff 100%);
                border-radius: 18px;
                padding: 2.25rem;
            }

            .announcements-title {
                color: var(--text-main);
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .announcement-card {
                border: 0;
                border-radius: 14px;
                overflow: hidden;
                background: #ffffff;
                box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
                transition: transform .2s ease, box-shadow .2s ease;
                height: 100%;
                padding: 1.15rem 1.15rem 1.25rem;
            }

            .announcement-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 16px 35px rgba(15, 23, 42, 0.14);
            }

            .announcement-image {
                display: block;
                width: auto;
                max-width: 100%;
                height: auto;
                max-height: 420px;
                margin: .5rem auto .9rem;
                object-fit: contain;
                background: #e2e8f0;
            }

            .announcement-image-placeholder {
                width: 100%;
                height: 220px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                letter-spacing: .08em;
                color: #334155;
                background:
                    linear-gradient(135deg, rgba(15,118,110,.35), rgba(21,94,117,.4)),
                    repeating-linear-gradient(
                        45deg,
                        rgba(255,255,255,.15),
                        rgba(255,255,255,.15) 10px,
                        rgba(255,255,255,0) 10px,
                        rgba(255,255,255,0) 20px
                    );
            }

            .announcement-body {
                padding: 0;
            }

            .announcement-tag {
                display: inline-block;
                font-size: .72rem;
                font-weight: 700;
                color: #fff;
                background: linear-gradient(120deg, var(--brand-1), var(--brand-2));
                border-radius: 999px;
                padding: .2rem .55rem;
                margin-bottom: .5rem;
            }

            .announcement-heading {
                color: var(--text-main);
                font-size: 1.1rem;
                line-height: 1.3;
                margin-bottom: .45rem;
            }

            .announcement-excerpt {
                color: var(--text-muted);
                margin-bottom: .55rem;
            }

            .announcement-date {
                color: #64748b;
                font-size: .84rem;
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light" id="mainNav">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="{{ url('/') }}">Sistema Escolar</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="#avisos">Avisos</a></li>
                        <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="#novedades">Novedades</a></li>

                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link px-lg-3 py-3 py-lg-4 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('dashboard') }}">Dashboard</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Cerrar sesión</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="{{ route('login') }}">Ingresar</a></li>
                            @if (Route::has('register'))
                                <li class="nav-item"><a class="nav-link px-lg-3 py-3 py-lg-4" href="{{ route('register') }}">Registro</a></li>
                            @endif
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <header class="masthead" style="background-image: url('assets/img/home-bg.jpg')">
            <div class="container position-relative px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-md-10 col-lg-8 col-xl-7">
                        <div class="site-heading">
                            <h1>UNIVERSIDAD LATINOAMERICANA</h1>
                            <span class="subheading">CAMPUS VALLE</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section id="avisos" class="container-fluid px-4 px-lg-5 my-5">
            <div class="announcements-wrap">
                <h2 class="announcements-title">Avisos</h2>
                <div class="row g-4">
                    @forelse(($announcements ?? collect()) as $announcement)
                        <div class="col-12">
                            <article class="announcement-card">
                                @if($announcement->images->isNotEmpty())
                                    <div class="announcement-body">
                                        <span class="announcement-tag">Comunicado</span>
                                        <h4 class="announcement-heading">{{ $announcement->title }}</h4>
                                    </div>
                                    @php
                                        $imagePath = (string) $announcement->images->first()->path;
                                        $imageUrl = \Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://'])
                                            ? $imagePath
                                            : (\Illuminate\Support\Str::startsWith($imagePath, 'storage/')
                                                ? asset($imagePath)
                                                : \Illuminate\Support\Facades\Storage::url($imagePath));
                                    @endphp
                                    <img
                                        class="announcement-image"
                                        src="{{ $imageUrl }}"
                                        alt="{{ $announcement->title }}"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="announcement-image-placeholder">AVISO INSTITUCIONAL</div>
                                @endif

                                <div class="announcement-body">
                                    @if($announcement->images->isEmpty())
                                        <span class="announcement-tag">Comunicado</span>
                                        <h4 class="announcement-heading">{{ $announcement->title }}</h4>
                                    @endif
                                    <div class="announcement-excerpt mb-0">
                                        {!! nl2br(e($announcement->body)) !!}
                                    </div>
                                    <div class="announcement-date mt-2">
                                        Publicado:
                                        {{ optional($announcement->published_at)->format('d/m/Y H:i') ?? $announcement->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </article>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">
                                No hay avisos públicos disponibles por ahora.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <footer class="border-top">
            <div class="container px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-md-10 col-lg-8 col-xl-7">
                        <ul class="list-inline text-center">
                            <li class="list-inline-item">
                                <a href="#!">
                                    <span class="fa-stack fa-lg">
                                        <i class="fas fa-circle fa-stack-2x"></i>
                                        <i class="fab fa-facebook-f fa-stack-1x fa-inverse"></i>
                                    </span>
                                </a>
                            </li>
                            <li class="list-inline-item">
                                <a href="#!">
                                    <span class="fa-stack fa-lg">
                                        <i class="fas fa-circle fa-stack-2x"></i>
                                        <i class="fab fa-twitter fa-stack-1x fa-inverse"></i>
                                    </span>
                                </a>
                            </li>
                            <li class="list-inline-item">
                                <a href="#!">
                                    <span class="fa-stack fa-lg">
                                        <i class="fas fa-circle fa-stack-2x"></i>
                                        <i class="fab fa-instagram fa-stack-1x fa-inverse"></i>
                                    </span>
                                </a>
                            </li>
                        </ul>
                        <div class="small text-center text-muted fst-italic">Copyright &copy; Sistema Escolar 2023</div>
                    </div>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.querySelectorAll('a.nav-link[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        </script>
    </body>
</html>
