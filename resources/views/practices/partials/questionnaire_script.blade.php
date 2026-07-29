<script>
    let rawQuestions = @json(old('questionnaire', $practice->questionnaire ?? []));
    let rawDeliveryFields = @json(old('custom_submission_fields', $practice->custom_submission_fields ?? $practice->delivery_field_definitions ?? []));
    let questionCounter = 0;
    let deliveryFieldCounter = 0;

    window.questions = Array.isArray(rawQuestions)
        ? rawQuestions
        : (rawQuestions ? JSON.parse(rawQuestions) : []);

    window.deliveryFields = Array.isArray(rawDeliveryFields)
        ? rawDeliveryFields
        : (rawDeliveryFields ? JSON.parse(rawDeliveryFields) : []);

    function uniqueQuestionId() {
        questionCounter += 1;
        return 'q' + Date.now() + '_' + questionCounter;
    }

    function uniqueDeliveryFieldId() {
        deliveryFieldCounter += 1;
        return 'field_' + Date.now() + '_' + deliveryFieldCounter;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.addQuestion = function () {
        window.questions.push({
            id: uniqueQuestionId(),
            type: 'text',
            question: '',
            required: false,
            options: []
        });
        renderQuestions();
    };

    window.addDeliveryField = function () {
        window.deliveryFields.push({
            id: uniqueDeliveryFieldId(),
            label: '',
            required: false,
            legacy_field: null
        });
        renderDeliveryFields();
    };

    window.removeDeliveryField = function (index) {
        window.deliveryFields.splice(index, 1);
        renderDeliveryFields();
    };

    window.updateDeliveryFieldLabel = function (index, value) {
        window.deliveryFields[index].label = value;
        syncDeliveryFieldsInput();
    };

    window.updateDeliveryFieldRequired = function (index, checked) {
        window.deliveryFields[index].required = checked;
        syncDeliveryFieldsInput();
    };

    window.removeQuestion = function (index) {
        window.questions.splice(index, 1);
        renderQuestions();
    };

    window.changeType = function (index, type) {
        window.questions[index].type = type;
        if (type !== 'multiple_choice') {
            window.questions[index].options = [];
        }
        renderQuestions();
    };

    window.updateQuestionText = function (index, value) {
        window.questions[index].question = value;
        syncQuestionnaireInput();
    };

    window.updateOptionText = function (index, optionIndex, value) {
        window.questions[index].options[optionIndex] = value;
        syncQuestionnaireInput();
    };

    window.addOption = function (index) {
        window.questions[index].options = window.questions[index].options || [];
        window.questions[index].options.push('');
        renderQuestions();
    };

    window.removeOption = function (index, optionIndex) {
        window.questions[index].options.splice(optionIndex, 1);
        renderQuestions();
    };

    window.renderQuestions = function () {
        const container = document.getElementById('questionnaire-builder');
        container.innerHTML = window.questions.map((q, index) => {
            const options = (q.options || []).map((opt, optionIndex) => `
                <div class="input-group mb-1">
                    <input class="form-control"
                        value="${escapeHtml(opt)}"
                        oninput="updateOptionText(${index}, ${optionIndex}, this.value)">
                    <div class="input-group-append">
                        <button type="button"
                            class="btn btn-outline-danger"
                            onclick="removeOption(${index}, ${optionIndex})">
                            Quitar
                        </button>
                    </div>
                </div>
            `).join('');

            const optionsBlock = q.type === 'multiple_choice'
                ? `
                    <div class="form-group">
                        <label>Opciones</label>
                        ${options}
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            onclick="addOption(${index})">
                            Agregar opcion
                        </button>
                    </div>
                `
                : '';

            return `
                <div class="delivery-builder-card">
                    <div class="form-group">
                        <label>Pregunta ${index + 1}</label>
                        <input type="text" class="form-control"
                            value="${escapeHtml(q.question)}"
                            oninput="updateQuestionText(${index}, this.value)">
                    </div>

                    <div class="form-group">
                        <label>Tipo</label>
                        <select class="form-control"
                            onchange="changeType(${index}, this.value)">
                            <option value="text" ${q.type==='text'?'selected':''}>Abierta</option>
                            <option value="multiple_choice" ${q.type==='multiple_choice'?'selected':''}>Opcion multiple</option>
                            <option value="boolean" ${q.type==='boolean'?'selected':''}>Si / No</option>
                        </select>
                    </div>

                    ${optionsBlock}

                    <button type="button"
                        class="btn btn-sm btn-danger"
                        onclick="removeQuestion(${index})">
                        Eliminar
                    </button>
                </div>
            `;
        }).join('');

        syncQuestionnaireInput();
    };

    window.renderDeliveryFields = function () {
        const container = document.getElementById('delivery-fields-builder');
        container.innerHTML = window.deliveryFields.map((field, index) => `
            <div class="delivery-builder-card">
                <div class="form-group mb-2">
                    <label>Nombre del campo ${index + 1}</label>
                    <input type="text"
                           class="form-control"
                           placeholder="Ej. Desarrollo, Evidencia, Reflexion, Conclusiones..."
                           value="${escapeHtml(field.label)}"
                           oninput="updateDeliveryFieldLabel(${index}, this.value)">
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox"
                               class="custom-control-input"
                               id="delivery_field_required_${index}"
                               ${field.required ? 'checked' : ''}
                               onchange="updateDeliveryFieldRequired(${index}, this.checked)">
                        <label class="custom-control-label" for="delivery_field_required_${index}">
                            Obligatorio
                        </label>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-danger"
                            onclick="removeDeliveryField(${index})">
                        Eliminar
                    </button>
                </div>
            </div>
        `).join('');

        syncDeliveryFieldsInput();
    };

    function syncQuestionnaireInput() {
        document.getElementById('questionnaire-input').value = JSON.stringify(window.questions);
    }

    function syncDeliveryFieldsInput() {
        document.getElementById('delivery-fields-input').value = JSON.stringify(window.deliveryFields);
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.deliveryFields = window.deliveryFields.map((field) => ({
            id: field.id || uniqueDeliveryFieldId(),
            label: field.label || '',
            required: !!field.required,
            legacy_field: field.legacy_field || null
        })).filter((field) => field.label !== '' || window.deliveryFields.length === 1);

        if (window.deliveryFields.length === 0) {
            window.deliveryFields = [
                { id: uniqueDeliveryFieldId(), label: 'Objetivo', required: false, legacy_field: null },
                { id: uniqueDeliveryFieldId(), label: 'Resultados', required: false, legacy_field: null },
                { id: uniqueDeliveryFieldId(), label: 'Conclusiones', required: false, legacy_field: null }
            ];
        }

        window.questions = window.questions.map((question) => ({
            id: question.id || uniqueQuestionId(),
            type: question.type || 'text',
            question: question.question || '',
            required: !!question.required,
            options: Array.isArray(question.options) ? question.options : []
        }));
        renderDeliveryFields();
        renderQuestions();
    });
</script>
