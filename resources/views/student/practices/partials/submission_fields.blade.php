@foreach($practice->delivery_field_definitions as $field)
    @php
        $fieldId = $field['id'];
        $legacyField = $field['legacy_field'] ?? null;
        $customAnswers = (array) $submission->custom_field_answers;
        $value = old(
            'custom_field_answers.' . $fieldId,
            ($customAnswers[$fieldId] ?? ($legacyField ? ($submission->{$legacyField} ?? '') : ''))
        );
    @endphp
    <div class="form-group">
        <label>
            {{ $field['label'] }}
            @if($field['required'])
                <span class="text-danger">*</span>
            @endif
        </label>
        <textarea name="custom_field_answers[{{ $fieldId }}]"
                  class="form-control"
                  rows="6"
                  placeholder="Escribe aqui tu respuesta">{{ $value }}</textarea>
    </div>
@endforeach
