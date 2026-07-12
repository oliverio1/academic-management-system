<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    @php
        $currentUser = auth()->user();
        $unreadNotifications = $currentUser
            ? $currentUser->unreadNotifications()->latest()->take(8)->get()
            : collect();
        $unreadCount = $unreadNotifications->count();
        $availableCampuses = $currentUser ? $currentUser->campuses()->orderBy('name')->get() : collect();
        $activeCampusId = (int) session('active_campus_id', (int) ($currentUser->default_campus_id ?? 0));
    @endphp

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
        @if($availableCampuses->isNotEmpty())
            <li class="nav-item mr-2 d-flex align-items-center">
                <form action="{{ route('active-campus.update') }}" method="POST" class="form-inline campus-switcher">
                    @csrf
                    <label class="campus-switcher__label mb-0 mr-2 d-none d-lg-inline">Campus</label>
                    <select name="campus_id" class="form-control form-control-sm campus-switcher__select" onchange="this.form.submit()">
                        @foreach($availableCampuses as $campus)
                            <option value="{{ $campus->id }}" {{ $activeCampusId === (int) $campus->id ? 'selected' : '' }}>
                                {{ $campus->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </li>
            @php
                $activeCampusName = optional($availableCampuses->firstWhere('id', $activeCampusId))->name;
            @endphp
            <li class="nav-item d-none d-md-flex align-items-center mr-2">
                <span class="campus-active-chip">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    {{ $activeCampusName ?: 'Campus no definido' }}
                </span>
            </li>
        @endif
        <li class="nav-item dropdown">
            <a id="navbar-bell-trigger" class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
                <i class="far fa-bell"></i>
                @if($unreadCount > 0)
                    <span id="navbar-notification-count" class="badge badge-warning navbar-badge">{{ $unreadCount }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="navbar-notification-menu">
                <span class="dropdown-item dropdown-header" id="navbar-notification-header">{{ $unreadCount }} Notificaciones</span>
                <div class="dropdown-divider"></div>

                @forelse($unreadNotifications as $notification)
                    @php
                        $title = $notification->data['title'] ?? 'Notificacion';
                        $message = $notification->data['message'] ?? ($notification->data['excerpt'] ?? 'Tienes una nueva notificacion.');
                    @endphp

                    <button
                        type="button"
                        class="dropdown-item text-wrap js-mark-notification"
                        data-read-url="{{ route('notifications.read', $notification) }}">
                        <i class="fas fa-bell mr-2"></i> {{ $title }}
                        <span class="float-right text-muted text-sm">{{ $notification->created_at->diffForHumans() }}</span>
                        <br>
                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($message, 90) }}</small>
                    </button>
                    <div class="dropdown-divider"></div>
                @empty
                    <span class="dropdown-item text-muted" id="navbar-empty-notification">Sin notificaciones nuevas.</span>
                    <div class="dropdown-divider"></div>
                @endforelse

                <a href="{{ route('dashboard') }}" class="dropdown-item dropdown-footer">Ver dashboard</a>
            </div>
        </li>

        {{-- Usuario --}}
        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <span class="d-none d-md-inline">
                    {{ $currentUser?->name ?? 'Invitado' }}
                </span>
            </a>

            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">

                <li class="user-header bg-secondary">
                    <p>
                        {{ $currentUser?->name ?? 'Invitado' }} <br>
                        <small>
                            {{ $currentUser?->role_label ?? 'Sin sesion' }}
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

<style>
    .campus-switcher {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.2rem 0.45rem;
    }
    .campus-switcher__label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #4b5563;
        letter-spacing: .01em;
    }
    .campus-switcher__select {
        min-width: 180px;
        border: 0;
        background: transparent;
        color: #111827;
        font-weight: 600;
        padding-left: .2rem;
        box-shadow: none !important;
    }
    .campus-switcher__select:focus {
        outline: none;
    }
    .campus-active-chip {
        display: inline-flex;
        align-items: center;
        background: #eef2ff;
        color: #1e3a8a;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        padding: .28rem .65rem;
        font-size: .78rem;
        font-weight: 600;
        line-height: 1;
    }
</style>

<script>
    (function () {
        const csrf = '{{ csrf_token() }}';

        function refreshCount(newCount) {
            const badge = document.getElementById('navbar-notification-count');
            const header = document.getElementById('navbar-notification-header');
            const menu = document.getElementById('navbar-notification-menu');

            if (header) {
                header.textContent = `${newCount} Notificaciones`;
            }

            if (!badge && newCount > 0) {
                const anchor = document.getElementById('navbar-bell-trigger');
                if (!anchor) return;
                const span = document.createElement('span');
                span.id = 'navbar-notification-count';
                span.className = 'badge badge-warning navbar-badge';
                span.textContent = newCount;
                anchor.appendChild(span);
                return;
            }

            if (badge && newCount <= 0) {
                badge.remove();
            } else if (badge) {
                badge.textContent = newCount;
            }

            if (newCount <= 0 && menu && !document.getElementById('navbar-empty-notification')) {
                const divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                const empty = document.createElement('span');
                empty.id = 'navbar-empty-notification';
                empty.className = 'dropdown-item text-muted';
                empty.textContent = 'Sin notificaciones nuevas.';
                menu.insertBefore(divider, menu.querySelector('.dropdown-item.dropdown-footer'));
                menu.insertBefore(empty, menu.querySelector('.dropdown-item.dropdown-footer'));
            }
        }

        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('.js-mark-notification');
            if (!btn) return;

            e.preventDefault();

            const readUrl = btn.dataset.readUrl;
            if (!readUrl) return;

            try {
                const res = await fetch(readUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                });

                if (!res.ok) return;
                const payload = await res.json();

                const divider = btn.nextElementSibling && btn.nextElementSibling.classList.contains('dropdown-divider')
                    ? btn.nextElementSibling
                    : null;

                btn.remove();
                if (divider) divider.remove();

                refreshCount(Number(payload.unread_count ?? 0));
            } catch (err) {
                // silencioso para no romper UX
            }
        });
    })();
</script>
