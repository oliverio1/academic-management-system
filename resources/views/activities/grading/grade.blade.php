@extends('layouts.app')

@section('title', 'Calificaciones')

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
                                <h4>Calificar actividad: {{ $activity->title }}</h4>
                                <p>
                                    Criterio: {{ $activity->evaluationCriterion->name }} |
                                    Modo: <strong>{{ ucfirst($activity->evaluation_mode) }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($activity->evaluation_mode === 'individual')
                            @include('activities.grading._individual')
                        @elseif ($activity->evaluation_mode === 'team')
                            @include('activities.grading._team')
                        @endif
                    </div>
                </div>
            </div>
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