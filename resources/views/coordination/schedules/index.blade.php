@extends('layouts.app')
@section('title', 'Horarios')
@section('content')
<div class="content px-3"><div class="row"><div class="col-md-12 mt-3"><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h3 class="mb-0">Horarios semanales</h3><a href="{{ route('coordination.schedules.create', ['school_cycle_id' => $filters['school_cycle_id'] ?? null]) }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Nuevo horario</a></div><div class="card-body">@include('coordination.schedules._index_content')</div></div></div></div></div>
@endsection
@section('page_scripts')
<script>(function(){const cycleSelect=document.getElementById('school_cycle_id');const filterForm=document.getElementById('scheduleFilterForm');if(cycleSelect&&filterForm){cycleSelect.addEventListener('change',function(){filterForm.submit();});}})();</script>
@endsection
