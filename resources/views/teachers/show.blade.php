@extends('layouts.app')

@section('title', 'Detalle del profesor')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h2>{{ $teacher->user->name }}</h2><br>
                    <strong>Email:</strong> {{ $teacher->user->email }}
                </div>
                <div class="card-body">
                    @php
                        $dayLabels = [
                            'lunes'     => 'LUNES',
                            'martes'    => 'MARTES',
                            'miercoles' => 'MIÉRCOLES',
                            'jueves'    => 'JUEVES',
                            'viernes'   => 'VIERNES',
                        ];
                    @endphp
                    <table id="schedule" class="table table-bordered table-striped schedule">
                        <thead>
                            <tr>
                                <th class="hora" style="text-align:center">Hora</th>
                                @foreach($days as $day)
                                    <th style="text-align:center">{{ $dayLabels[$day] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $breakHours = ['09:30', '11:40'];
                            @endphp
                            @foreach($hours as $hour)
                                <tr>
                                    <td style="text-align:center; vertical-align: middle;"><strong>{{ $hour }}</strong></td>
                                    @if(in_array($hour, $breakHours))
                                        <td colspan="{{ count($days) }}"
                                            style="
                                                text-align:center;
                                                font-weight:bold;
                                                background:#6c757d;
                                                color:white;
                                                vertical-align: middle;
                                            ">
                                            DESCANSO
                                        </td>

                                    {{-- FILA NORMAL --}}
                                    @else
                                        @foreach($days as $day)
                                            @php $assignment = $grid[$hour][$day]; @endphp
                                            @if($assignment)
                                                <td style="background-color:#198754; text-align:center; vertical-align: middle;">
                                                    <strong>{{ $assignment->group->name }}</strong><br>
                                                    {{ $assignment->subject->name }}
                                                </td>
                                            @else
                                                <td style="background-color:#ffc107"></td>
                                            @endif
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
    <style>
        .schedule {
            table-layout: fixed;
            width: 100%;
        }
        .hora {
            width: 90px;
        }
        .schedule th:not(:first-child),
        .schedule td:not(:first-child) {
            width: calc((100% - 90px) / 5);
        }
        .schedule th {
            text-align: center;
            vertical-align: middle;
        }
        .schedule td {
            height: 60px;
        }
    </style>
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#schedule').DataTable({
                dom: '',
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
@endsection
