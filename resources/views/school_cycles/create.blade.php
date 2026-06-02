@extends('layouts.app')

@section('title', 'Nuevo ciclo escolar')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Nuevo ciclo escolar</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('school-cycles.store') }}">
                        @csrf
                        <div class="row">
                            @include('school_cycles._form')
                        </div>
                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('school-cycles.index') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        if (window.jQuery && $.fn.select2) {
            $('.js-campus-select').select2({
                width: '100%',
                placeholder: 'Selecciona uno o más campus',
                allowClear: true,
                closeOnSelect: false
            });

            $('.js-modality-select').select2({
                width: '100%',
                placeholder: 'Selecciona modalidades',
                closeOnSelect: false
            });
        }

        const modalitySelect = document.querySelector('select[name="modality_ids[]"]');
        const campusSelect = document.querySelector('select[name="campus_ids[]"]');
        const cloneCheckbox = document.getElementById('clone_configuration');
        const sourceWrapper = document.getElementById('source_cycle_wrapper');
        const cloneGroupsWrapper = document.getElementById('clone_groups_wrapper');
        const sourceSelect = document.getElementById('source_cycle_id');

        if (!modalitySelect || !campusSelect || !cloneCheckbox || !sourceWrapper || !sourceSelect || !cloneGroupsWrapper) {
            return;
        }

        function filterSourceOptions() {
            const selectedIds = Array.from(modalitySelect.selectedOptions).map((opt) => String(opt.value));
            const campusIds = Array.from(campusSelect.selectedOptions).map((opt) => String(opt.value));
            const options = Array.from(sourceSelect.options);

            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                const optionIds = (option.dataset.modalityIds || '').split(',').filter(Boolean);
                const optionCampusIds = (option.dataset.campusIds || '').split(',').filter(Boolean);
                const sharesModality = selectedIds.length === 0
                    ? true
                    : optionIds.some((id) => selectedIds.includes(id));
                const sharesCampus = campusIds.length === 0
                    ? true
                    : optionCampusIds.some((id) => campusIds.includes(id));
                option.hidden = !(sharesModality && sharesCampus);
            });

            const selected = sourceSelect.options[sourceSelect.selectedIndex];
            if (selected && selected.hidden) {
                sourceSelect.value = '';
            }
        }

        function toggleSource() {
            sourceWrapper.style.display = cloneCheckbox.checked ? '' : 'none';
            cloneGroupsWrapper.style.display = cloneCheckbox.checked ? '' : 'none';
            if (!cloneCheckbox.checked) {
                sourceSelect.value = '';
            } else {
                filterSourceOptions();
            }
        }

        modalitySelect.addEventListener('change', filterSourceOptions);
        campusSelect.addEventListener('change', filterSourceOptions);
        if (window.jQuery) {
            $(modalitySelect).on('change.select2', filterSourceOptions);
            $(campusSelect).on('change.select2', filterSourceOptions);
        }
        cloneCheckbox.addEventListener('change', toggleSource);

        filterSourceOptions();
        toggleSource();
    })();
</script>
@endsection
