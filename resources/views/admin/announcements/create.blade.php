@extends('layouts.app')

@section('title', 'Avisos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Avisos</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            @include('admin.announcements._form')
                        </div>
                    </div>
                    <div class="card-footer">
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
