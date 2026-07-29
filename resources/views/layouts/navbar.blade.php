<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    @php
        $currentUser = auth()->user();
        $notificationSummary = $currentUser
            ? app(\App\Services\NotificationCenterService::class)->navbar($currentUser)
            : ['unread_count' => 0, 'items' => []];
        $unreadNotifications = collect($notificationSummary['items'] ?? []);
        $unreadCount = (int) ($notificationSummary['unread_count'] ?? 0);
        $availableCampuses = $currentUser
            ? \Illuminate\Support\Facades\Cache::remember(
                'navbar:user:'.$currentUser->id.':campuses:'.optional($currentUser->updated_at)->timestamp,
                now()->addMinutes(30),
                fn () => $currentUser->campuses()->orderBy('name')->get()
            )
            : collect();
        $activeCampusId = (int) session('active_campus_id', (int) ($currentUser->default_campus_id ?? 0));
        $cycleContext = $currentUser ? app(\App\Services\CurrentSchoolCycle::class) : null;
        $availableSchoolCycles = $cycleContext ? $cycleContext->available($currentUser, $activeCampusId) : collect();
        $activeSchoolCycle = $cycleContext ? $cycleContext->get($currentUser, $activeCampusId) : null;
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
        @if($currentUser?->hasAnyRole(['coordinator', 'admin', 'teacher']) && $availableSchoolCycles->isNotEmpty())
            <li class="nav-item mr-2 d-flex align-items-center">
                <form action="{{ route('active-school-cycle.update') }}" method="POST" class="form-inline cycle-switcher">
                    @csrf
                    <label class="cycle-switcher__label mb-0 mr-2 d-none d-lg-inline">Ciclo</label>
                    <select name="school_cycle_id" class="form-control form-control-sm cycle-switcher__select" onchange="this.form.submit()">
                        @foreach($availableSchoolCycles as $cycle)
                            <option value="{{ $cycle->id }}" {{ optional($activeSchoolCycle)->id === $cycle->id ? 'selected' : '' }}>
                                {{ $cycle->name }}{{ $cycle->code ? ' ('.$cycle->code.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
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
                    <button
                        type="button"
                        class="dropdown-item text-wrap js-mark-notification"
                        data-read-url="{{ $notification['read_url'] }}">
                        <i class="fas fa-bell mr-2"></i> {{ $notification['title'] }}
                        <span class="float-right text-muted text-sm">{{ $notification['created_at_human'] }}</span>
                        <br>
                        <small class="text-muted">{{ $notification['message'] }}</small>
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
    .cycle-switcher {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 0.2rem 0.45rem;
    }
    .cycle-switcher__label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #4b5563;
        letter-spacing: .01em;
    }
    .cycle-switcher__select {
        min-width: 180px;
        border: 0;
        background: transparent;
        color: #111827;
        font-weight: 600;
        padding-left: .2rem;
        box-shadow: none !important;
    }
    .cycle-switcher__select {
        min-width: 210px;
    }
    .cycle-switcher__select:focus {
        outline: none;
    }
</style>

<script>
    (function () {
        const csrf = '{{ csrf_token() }}';
        const summaryUrl = @json(route('notifications.summary'));
        const dashboardUrl = @json(route('dashboard'));

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

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

        function renderItems(items) {
            const menu = document.getElementById('navbar-notification-menu');
            if (!menu) return;

            const header = document.getElementById('navbar-notification-header');
            const footer = menu.querySelector('.dropdown-item.dropdown-footer');
            menu.innerHTML = '';
            if (header) {
                menu.appendChild(header);
            } else {
                const fallbackHeader = document.createElement('span');
                fallbackHeader.id = 'navbar-notification-header';
                fallbackHeader.className = 'dropdown-item dropdown-header';
                menu.appendChild(fallbackHeader);
            }
            const firstDivider = document.createElement('div');
            firstDivider.className = 'dropdown-divider';
            menu.appendChild(firstDivider);

            if (!items || items.length === 0) {
                const empty = document.createElement('span');
                empty.id = 'navbar-empty-notification';
                empty.className = 'dropdown-item text-muted';
                empty.textContent = 'Sin notificaciones nuevas.';
                menu.appendChild(empty);
                const divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                menu.appendChild(divider);
            } else {
                items.forEach(item => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'dropdown-item text-wrap js-mark-notification';
                    button.dataset.readUrl = item.read_url || '';
                    button.innerHTML = `
                        <i class="fas fa-bell mr-2"></i> ${escapeHtml(item.title || 'Notificacion')}
                        <span class="float-right text-muted text-sm">${escapeHtml(item.created_at_human || '')}</span>
                        <br>
                        <small class="text-muted">${escapeHtml(item.message || '')}</small>
                    `;
                    menu.appendChild(button);

                    const divider = document.createElement('div');
                    divider.className = 'dropdown-divider';
                    menu.appendChild(divider);
                });
            }

            const footerLink = footer || document.createElement('a');
            footerLink.href = dashboardUrl;
            footerLink.className = 'dropdown-item dropdown-footer';
            footerLink.textContent = 'Ver dashboard';
            menu.appendChild(footerLink);
        }

        async function refreshNotifications() {
            try {
                const res = await fetch(summaryUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const payload = await res.json();
                refreshCount(Number(payload.unread_count ?? 0));
                renderItems(payload.items || []);
            } catch (err) {
                // silencioso para no romper UX
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

                refreshCount(Number(payload.unread_count ?? 0));
                renderItems(payload.items || []);
            } catch (err) {
                // silencioso para no romper UX
            }
        });

        setInterval(refreshNotifications, 60000);
    })();
</script>
