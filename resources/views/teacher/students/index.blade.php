
@extends('layouts.app')

@section('title', 'Asistencia')

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            Revisa la información antes de guardar.
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-3">Alumnos</h4>
                    </div>
                    <div class="card-body">
                        @if($groups->isEmpty())
                            <div class="alert alert-info">
                                No tienes grupos asignados actualmente.
                            </div>
                        @else
                            <div class="row">
                                @foreach($groups as $group)
                                    <div class="col-md-4">
                                        <div class="card mb-3 h-100">

                                            <div class="card-body text-center">
                                                <h5 class="mb-2">
                                                    {{ $group->name }}
                                                </h5>

                                                <a href="{{ route('teacher.students.group', $group) }}"
                                                class="btn btn-primary btn-sm btn-block">
                                                    Ver alumnos
                                                </a>
                                            </div>

                                        </div>
                                    </div>
                                @endforeach
                            </div>
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
@endsection