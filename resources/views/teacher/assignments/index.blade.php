@extends('layouts.app')

@section('title', 'Mis materias')

@section('content')
<div class="container-fluid">
    <div class="row">
        @foreach($assignments as $assignment)
            <div class="col-md-4">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h5 class="card-title">
                            {{ $assignment->subject->name }}
                        </h5>
                    </div>

                    <div class="card-body">
                        <p><strong>Grupo:</strong> {{ $assignment->group->name }}</p>
                    </div>

                    <div class="card-footer text-right">
                        <a href="{{ route('assignments.show', $assignment) }}"
                           class="btn btn-primary btn-sm">
                            Entrar
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div> 
@endsection
