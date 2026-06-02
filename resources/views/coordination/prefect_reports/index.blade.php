@extends('layouts.app')
@section('title', 'Reportes de prefectos')
@section('content')
<div class="content px-3"><div class="row"><div class="col-md-12 mt-3"><div class="card"><div class="card-header"><h3 class="mb-0">Reportes de prefectos</h3></div><div class="card-body">@if(session('info'))<div class="alert alert-success">{{ session('info') }}</div>@endif @include('coordination.prefect_reports._table')</div></div></div></div></div>
@endsection
