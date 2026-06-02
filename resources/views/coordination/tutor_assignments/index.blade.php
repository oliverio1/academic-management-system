@extends('layouts.app')
@section('title', 'Asignación de tutores')
@section('content')
<div class="content px-3"><div class="row"><div class="col-md-12 mt-3"><div class="card"><div class="card-header"><h3 class="mb-0">Asignación de tutores</h3><small class="text-muted">Un tutor puede quedar asociado a uno o más alumnos.</small></div><div class="card-body">@if(session('info'))<div class="alert alert-success">{{ session('info') }}</div>@endif @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @include('coordination.tutor_assignments._content')</div></div></div></div></div>
@endsection
