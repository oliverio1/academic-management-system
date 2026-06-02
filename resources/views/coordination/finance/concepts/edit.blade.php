@extends('layouts.app')

@section('title', 'Editar concepto')

@section('content')
<div class="content px-3"><div class="row"><div class="col-md-8 mt-3"><div class="card"><div class="card-header"><h4 class="mb-0">Editar concepto</h4></div><div class="card-body">
    <form method="POST" action="{{ route('coordination.finance.concepts.update', $concept) }}">
        @csrf
        @method('PUT')
        @include('coordination.finance.concepts.form', ['concept' => $concept])
    </form>
</div></div></div></div></div>
@endsection

