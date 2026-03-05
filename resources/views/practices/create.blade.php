@extends('layouts.app')

@section('title', 'Nueva práctica')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Nueva práctica</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('practices.store', $assignment) }}">
                        @csrf

                        @include('practices._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('practices.index', $assignment) }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    let rawQuestions = @json(old('questionnaire', $practice->questionnaire ?? []));

    window.questions = Array.isArray(rawQuestions)
        ? rawQuestions
        : (rawQuestions ? JSON.parse(rawQuestions) : []);
        
    window.addQuestion = function () {
        window.questions.push({
            id: 'q' + Date.now(),
            type: 'text',
            question: '',
            required: false
        });
        renderQuestions();
    };

    window.removeQuestion = function (index) {
        window.questions.splice(index, 1);
        renderQuestions();
    };

    window.changeType = function (index, type) {
        window.questions[index].type = type;
        if (type !== 'multiple_choice') {
            delete window.questions[index].options;
        }
        renderQuestions();
    };

    window.renderQuestions = function () {
        const container = document.getElementById('questionnaire-builder');
        container.innerHTML = '';

        window.questions.forEach((q, index) => {
            container.innerHTML += `
                <div class="card mb-2 p-2">
                    <div class="form-group">
                        <label>Pregunta</label>
                        <input type="text" class="form-control"
                            value="${q.question}"
                            onchange="questions[${index}].question=this.value">
                    </div>

                    <div class="form-group">
                        <label>Tipo</label>
                        <select class="form-control"
                            onchange="changeType(${index}, this.value)">
                            <option value="text" ${q.type==='text'?'selected':''}>Abierta</option>
                            <option value="multiple_choice" ${q.type==='multiple_choice'?'selected':''}>Opción múltiple</option>
                            <option value="boolean" ${q.type==='boolean'?'selected':''}>Sí / No</option>
                        </select>
                    </div>

                    <div id="options-${index}"></div>

                    <button type="button"
                        class="btn btn-sm btn-danger"
                        onclick="removeQuestion(${index})">
                        Eliminar
                    </button>
                </div>
            `;
            renderOptions(index);
        });

        document.getElementById('questionnaire-input').value =
            JSON.stringify(window.questions);
    };

    window.renderOptions = function (index) {
        const q = window.questions[index];
        if (q.type !== 'multiple_choice') return;

        q.options = q.options || [];

        const div = document.getElementById(`options-${index}`);
        div.innerHTML = `
            <label>Opciones</label>
            ${q.options.map((opt, i) => `
                <input class="form-control mb-1"
                    value="${opt}"
                    onchange="questions[${index}].options[${i}]=this.value">
            `).join('')}
            <button type="button"
                class="btn btn-sm btn-outline-secondary"
                onclick="questions[${index}].options.push(''); renderQuestions();">
                Agregar opción
            </button>
        `;
    };

    document.addEventListener('DOMContentLoaded', () => {
        renderQuestions();
    });
</script>
@endsection
