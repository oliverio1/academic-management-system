@extends('layouts.app')

@section('title', 'Editar aviso')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Editar aviso</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.announcement._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
