@extends('layouts.app')

@section('title', 'Desempeño del grupo')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4>Selecciona un grupo</h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach($assignments as $a)
                            <li class="list-group-item d-flex justify-content-between">
                                {{ $a->group->name }} — {{ $a->subject->name }}
                                <a href="{{ route('teacher.performance.show', $a) }}"
                                class="btn btn-primary btn-sm">
                                    Ver desempeño
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


