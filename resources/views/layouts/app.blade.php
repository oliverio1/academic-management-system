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

                .datatable-card-wrap {
                    padding-left: .75rem !important;
                    padding-right: .75rem !important;
                    padding-bottom: .5rem !important;
                }

                .select2-container--default .select2-selection--multiple {
                    border: 1px solid #ced4da;
                    min-height: 38px;
                    border-radius: .25rem;
                }

                .select2-container .select2-selection--multiple .select2-selection__rendered {
                    display: flex !important;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 6px !important;
                }

                .select2-container--default .select2-selection--multiple .select2-selection__choice {
                    background-color: #0d6efd;
                    border-color: #0d6efd;
                    color: #fff;
                    position: relative;
                    padding: 2px 10px 2px 22px;
                    font-size: .9rem;
                    line-height: 1.2;
                }

                .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                    color: #fff;
                    position: absolute;
                    left: 7px;
                    top: 50%;
                    transform: translateY(-50%);
                    margin-right: 0;
                    border-right: 0;
                    padding-right: 0;
                }

                .select2-container--default .select2-selection--multiple .select2-search--inline {
                    display: inline-flex;
                    align-items: center;
                    flex: 1 0 140px;
                }

                .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
                    width: 100% !important;
                    min-width: 120px;
                    margin-top: 0 !important;
                    height: 24px;
                    line-height: 24px;
                    padding: 0 4px;
                }

                .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
                    background-color: #0d6efd;
                    color: #fff;
                }

                .select2-container--default .select2-results__option--selected {
                    background-color: #e7f1ff;
                    color: #0b3d91;
                }

                .chat-fab {
                    position: fixed;
                    right: 18px;
                    bottom: 18px;
                    z-index: 1050;
                    width: 58px;
                    height: 58px;
                    border-radius: 999px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 10px 24px rgba(0, 0, 0, .22);
                    font-size: 1.25rem;
                }

                .chat-fab-badge {
                    position: absolute;
                    top: -4px;
                    right: -4px;
                    min-width: 22px;
                    height: 22px;
                    border-radius: 11px;
                    background: #dc3545;
                    color: #fff;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    font-size: 11px;
                    font-weight: 700;
                    padding: 0 6px;
                    border: 2px solid #fff;
                }

                #chatModal .modal-dialog {
                    max-width: 980px;
                }

                #chatModal .chat-list {
                    max-height: 60vh;
                    overflow-y: auto;
                    border-right: 1px solid #e9ecef;
                }

                #chatModal .chat-messages {
                    height: 54vh;
                    overflow-y: auto;
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: .4rem;
                    padding: .75rem;
                }

                #chatModal .chat-msg-item {
                    margin-bottom: .5rem;
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

                @auth
                    @if(auth()->user()->hasAnyRole(['coordinator','teacher','student','prefect','guardian','tutor','admin']))
                        <button type="button" id="chat-fab-button" class="btn btn-primary chat-fab" title="Chat interno" aria-label="Chat interno">
                            <i class="fas fa-comments"></i>
                            <span id="chat-fab-badge" class="chat-fab-badge">0</span>
                        </button>
                    @endif
                @endauth

                @auth
                    @if(auth()->user()->hasAnyRole(['coordinator','teacher','student','prefect','guardian','tutor','admin']))
                        <div class="modal fade" id="chatModal" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header py-2">
                                        <h5 class="modal-title mb-0">Chat interno</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body pt-2">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <div class="mb-2">
                                                    <small class="text-muted">Nuevo chat directo</small>
                                                    <div class="input-group input-group-sm">
                                                        <select id="chat-direct-user" class="form-control"></select>
                                                        <div class="input-group-append">
                                                            <button type="button" id="chat-create-direct" class="btn btn-primary">Abrir</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Nuevo chat grupal</small>
                                                    <div class="input-group input-group-sm">
                                                        <select id="chat-group-select" class="form-control"></select>
                                                        <div class="input-group-append">
                                                            <button type="button" id="chat-create-group" class="btn btn-outline-primary">Abrir</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="chat-conversation-list" class="chat-list"></div>
                                            </div>
                                            <div class="col-md-8">
                                                <div id="chat-conversation-title" class="font-weight-bold mb-2">Selecciona una conversación</div>
                                                <div id="chat-messages-box" class="chat-messages mb-2"></div>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" id="chat-message-input" class="form-control" placeholder="Escribe un mensaje..." maxlength="5000">
                                                    <div class="input-group-append">
                                                        <button type="button" id="chat-send-button" class="btn btn-primary">Enviar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endauth

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
                window.initManagedDataTables = function () {
                    if (typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) return;

                    if ($.fn.dataTable && $.fn.dataTable.defaults) {
                        $.extend(true, $.fn.dataTable.defaults, {
                            retrieve: true
                        });
                    }

                    document.querySelectorAll('table[data-datatable=\"true\"]').forEach((table) => {
                        const responsiveWrap = table.closest('.table-responsive');
                        if (responsiveWrap) {
                            responsiveWrap.classList.add('datatable-card-wrap');
                        }

                        if ($.fn.DataTable.isDataTable(table)) return;

                        $(table).DataTable({
                            order: [],
                            language: { url: '/datatables.json' }
                        });
                    });
                };

                document.addEventListener('DOMContentLoaded', function () {
                    window.initManagedDataTables();
                });

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

                (function () {
                    const badge = document.getElementById('chat-fab-badge');
                    const fab = document.getElementById('chat-fab-button');
                    const modalElement = document.getElementById('chatModal');
                    if (!badge || !fab || !modalElement) return;

                    const conversationList = document.getElementById('chat-conversation-list');
                    const titleEl = document.getElementById('chat-conversation-title');
                    const messagesBox = document.getElementById('chat-messages-box');
                    const inputEl = document.getElementById('chat-message-input');
                    const sendBtn = document.getElementById('chat-send-button');
                    const directSelect = document.getElementById('chat-direct-user');
                    const groupSelect = document.getElementById('chat-group-select');
                    const createDirectBtn = document.getElementById('chat-create-direct');
                    const createGroupBtn = document.getElementById('chat-create-group');

                    let conversations = [];
                    let selectedConversationId = null;
                    let pollTimer = null;

                    const refreshUnread = async () => {
                        try {
                            const response = await fetch("{{ route('chat.unread-count') }}", {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const payload = await response.json();
                            const unread = Number(payload.unread || 0);

                            if (unread > 0) {
                                badge.textContent = unread > 99 ? '99+' : String(unread);
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        } catch (e) {
                        }
                    };

                    const escapeHtml = (text) => {
                        const div = document.createElement('div');
                        div.textContent = text || '';
                        return div.innerHTML;
                    };

                    const renderConversations = () => {
                        conversationList.innerHTML = '';
                        if (!conversations.length) {
                            conversationList.innerHTML = '<div class="text-muted p-2">Sin conversaciones.</div>';
                            return;
                        }
                        conversations.forEach((conversation) => {
                            const active = Number(conversation.id) === Number(selectedConversationId);
                            const row = document.createElement('button');
                            row.type = 'button';
                            row.className = `btn btn-block text-left btn-sm ${active ? 'btn-light' : 'btn-outline-light border'}`;
                            row.innerHTML = `<div class="font-weight-bold">${escapeHtml(conversation.label)}</div>
                                             <small class="text-muted">${conversation.type === 'group' ? 'Grupo' : 'Directo'} ${conversation.last_message_at ? '· ' + conversation.last_message_at : ''}</small>`;
                            row.addEventListener('click', () => openConversation(conversation.id, true));
                            conversationList.appendChild(row);
                        });
                    };

                    const renderMessages = (messages, append = false) => {
                        if (!append) {
                            messagesBox.innerHTML = '';
                        }
                        if (!messages.length && !append) {
                            messagesBox.innerHTML = '<div class="text-muted">No hay mensajes aún.</div>';
                        }
                        messages.forEach((message) => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'chat-msg-item';
                            wrapper.setAttribute('data-message-id', message.id);
                            wrapper.innerHTML = `<div class="small text-muted">${escapeHtml(message.user_name)} · ${escapeHtml(message.time)}</div>
                                                 <div class="border rounded bg-white px-2 py-1">${escapeHtml(message.body)}</div>`;
                            messagesBox.appendChild(wrapper);
                        });
                        messagesBox.scrollTop = messagesBox.scrollHeight;
                    };

                    const getLastMessageId = () => {
                        const items = messagesBox.querySelectorAll('[data-message-id]');
                        if (!items.length) return 0;
                        return Number(items[items.length - 1].getAttribute('data-message-id')) || 0;
                    };

                    const openConversation = async (conversationId, fetchAll = false) => {
                        selectedConversationId = Number(conversationId);
                        const current = conversations.find((conversation) => Number(conversation.id) === selectedConversationId);
                        titleEl.textContent = current ? current.label : 'Conversación';
                        renderConversations();
                        try {
                            const afterId = fetchAll ? 0 : getLastMessageId();
                            const response = await fetch(`{{ url('/chat/conversations') }}/${selectedConversationId}/messages?after_id=${afterId}`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const payload = await response.json();
                            renderMessages(payload.messages || [], !fetchAll && afterId > 0);
                            refreshUnread();
                        } catch (e) {
                        }
                    };

                    const loadBootstrap = async () => {
                        try {
                            const response = await fetch("{{ route('chat.bootstrap') }}", {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const payload = await response.json();

                            conversations = payload.conversations || [];
                            selectedConversationId = payload.selected_conversation_id || null;

                            directSelect.innerHTML = '<option value="">Selecciona usuario</option>';
                            (payload.available_users || []).forEach((user) => {
                                directSelect.innerHTML += `<option value="${user.id}">${escapeHtml(user.name)}</option>`;
                            });

                            groupSelect.innerHTML = '<option value="">Selecciona grupo</option>';
                            (payload.available_groups || []).forEach((group) => {
                                groupSelect.innerHTML += `<option value="${group.id}">${escapeHtml(group.name)}</option>`;
                            });

                            renderConversations();
                            if (selectedConversationId) {
                                const selected = conversations.find((conversation) => Number(conversation.id) === Number(selectedConversationId));
                                titleEl.textContent = selected ? selected.label : 'Conversación';
                                renderMessages(payload.messages || []);
                            } else {
                                titleEl.textContent = 'Selecciona una conversación';
                                renderMessages([]);
                            }

                            const unread = Number(payload.unread || 0);
                            if (unread > 0) {
                                badge.textContent = unread > 99 ? '99+' : String(unread);
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        } catch (e) {
                        }
                    };

                    const sendMessage = async () => {
                        if (!selectedConversationId) return;
                        const body = (inputEl.value || '').trim();
                        if (!body) return;

                        try {
                            const response = await fetch(`{{ url('/chat/conversations') }}/${selectedConversationId}/messages`, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                },
                                body: new URLSearchParams({ body }).toString(),
                            });
                            const payload = await response.json();
                            if (payload.message) {
                                renderMessages([payload.message], true);
                                inputEl.value = '';
                                await loadBootstrap();
                                await openConversation(selectedConversationId, false);
                            }
                        } catch (e) {
                        }
                    };

                    const createDirect = async () => {
                        const userId = directSelect.value;
                        if (!userId) return;
                        try {
                            const response = await fetch("{{ route('chat.direct.store') }}", {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                },
                                body: new URLSearchParams({ user_id: userId }).toString(),
                            });
                            const payload = await response.json();
                            await loadBootstrap();
                            if (payload.conversation_id) {
                                await openConversation(payload.conversation_id, true);
                            }
                        } catch (e) {
                        }
                    };

                    const createGroup = async () => {
                        const groupId = groupSelect.value;
                        if (!groupId) return;
                        try {
                            const response = await fetch("{{ route('chat.group.store') }}", {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                },
                                body: new URLSearchParams({ group_id: groupId }).toString(),
                            });
                            const payload = await response.json();
                            await loadBootstrap();
                            if (payload.conversation_id) {
                                await openConversation(payload.conversation_id, true);
                            }
                        } catch (e) {
                        }
                    };

                    $(modalElement).on('shown.bs.modal', async () => {
                        await loadBootstrap();
                        if (pollTimer) clearInterval(pollTimer);
                        pollTimer = setInterval(async () => {
                            if (selectedConversationId) {
                                await openConversation(selectedConversationId, false);
                            } else {
                                await loadBootstrap();
                            }
                        }, 4000);
                    });

                    $(modalElement).on('hidden.bs.modal', () => {
                        if (pollTimer) clearInterval(pollTimer);
                        pollTimer = null;
                    });

                    fab.addEventListener('click', () => {
                        $('#chatModal').modal('show');
                    });
                    sendBtn.addEventListener('click', sendMessage);
                    inputEl.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            sendMessage();
                        }
                    });
                    createDirectBtn.addEventListener('click', createDirect);
                    createGroupBtn.addEventListener('click', createGroup);

                    refreshUnread();
                    setInterval(refreshUnread, 10000);
                })();
            </script>
            @stack('third_party_scripts')
            @yield('page_scripts')
        </body>
    </html>
