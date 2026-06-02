@extends('layouts.app')

@section('title', 'Nuevo concepto')

@section('content')
<div class="content px-3"><div class="row"><div class="col-md-8 mt-3"><div class="card"><div class="card-header"><h4 class="mb-0">Nuevo concepto</h4></div><div class="card-body">
    <form method="POST" action="{{ route('coordination.finance.concepts.store') }}">
        @csrf
        @include('coordination.finance.concepts.form', ['concept' => null])
    </form>
</div></div></div></div></div>
@endsection

