@extends('layouts.app')

@section('title', 'Actividades')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Actividades</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right" href="{{ route('activities.create', $assignment) }}">Nuevo</a>
                                <a class="btn btn-success float-right" href="{{ route('grades.massive', $assignment) }}">Calificar</a>
                                <button class="btn btn-outline-secondary"
                                        id="cloneSelectedBtn"
                                        disabled
                                        data-toggle="modal"
                                        data-target="#cloneActivitiesModal">
                                    <i class="fas fa-copy"></i> Clonar seleccionadas
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de actividades</h3>
                        <hr>
                        <table class="table table-hover mb-0" id="activities">
                            <thead class="table-light">
                                <tr>
                                    <th>Actividad</th>
                                    <th>Periodo</th>
                                    <th class="text-center">Ponderación</th>
                                    <th class="text-center">Fecha de entrega</th>
                                    <th class="text-center">Calificaciones</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($activities as $activity)
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            class="activity-check"
                                            value="{{ $activity->id }}"
                                            data-title="{{ $activity->title }}"
                                            data-due="{{ $activity->due_date?->format('Y-m-d') }}">
                                    </td>
                                    <td>
                                    <a href="{{route('activities.show', $activity)}}" class="btn btn-outline-primary btn-sm text-left">{{ $activity->title }}</a>
                                        
                                    </td>
                                    <td>{{ $activity->academicPeriod->name }}</td>
                                    <td class="text-center">{{ $activity->max_score }} %</td>
                                    <td class="text-center">{{ $activity->due_date->format('d/m/Y') }}</td>
                                    <td class="text-center">
                                        @if($activity->grades->count())
                                            <span class="badge bg-success">{{ $activity->grades->count() }} de {{ $activity->assignment->group->students->count() }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ $activity->grades->count() }} de {{ $activity->assignment->group->students->count() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No hay actividades registradas</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cloneActivitiesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST"
                action="{{ route('activities.clone', $assignment) }}"
                class="modal-content">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Previsualizar clonación</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Actividad</th>
                                <th>Fecha original</th>
                            </tr>
                        </thead>
                        <tbody id="previewTable"></tbody>
                    </table>

                    <hr>

                    <div class="form-group">
                        <label>Grupo destino</label>
                        <select name="to_assignment_id"
                                class="form-control"
                                required>
                            @foreach($otherAssignments as $a)
                                <option value="{{ $a->id }}">
                                    {{ $a->group->name }} — {{ $a->subject->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nueva fecha de entrega (opcional)</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>

                    <input type="hidden" name="activity_ids" id="activityIds">

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Confirmar clonación</button>
                </div>

            </form>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#activities').DataTable({
                dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
                "columnDefs": [
                    { "type": "num", "targets": 0 }
                ],
                "order": [[ 0, "asc" ]],
                buttons: [
                    'excelHtml5',
                    'pdfHtml5'
                ],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
    <script>
        const checks = document.querySelectorAll('.activity-check');
        const cloneBtn = document.getElementById('cloneSelectedBtn');

        checks.forEach(c => c.addEventListener('change', () => {
            cloneBtn.disabled = ![...checks].some(c => c.checked);
        }));
    </script>
    <script>
        $('#cloneActivitiesModal').on('show.bs.modal', () => {

            const selected = [...document.querySelectorAll('.activity-check:checked')];

            document.getElementById('activityIds').value =
                selected.map(c => c.value).join(',');

            const tbody = document.getElementById('previewTable');
            tbody.innerHTML = '';

            selected.forEach(c => {
                tbody.innerHTML += `
                    <tr>
                        <td>${c.dataset.title}</td>
                        <td>${c.dataset.due ?? '—'}</td>
                    </tr>
                `;
            });
        });
    </script>
@endsection