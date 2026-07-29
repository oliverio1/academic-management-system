@extends('layouts.app')

@section('title', $documentLabel)

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">{{ $documentLabel }}</h4>
                        <small class="text-muted">
                            {{ $item->assignment->subject->name ?? '-' }} / Grupo {{ $item->assignment->group->name ?? '-' }}
                        </small>
                    </div>
                    <a href="{{ route('teacher.document-requests.index') }}" class="btn btn-secondary btn-sm">Volver</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('teacher.document-requests.content.update', $item) }}" id="document-editor-form">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label>Titulo del documento</label>
                            <input type="text" name="title" class="form-control" required
                                   value="{{ old('title', $content->title ?? $documentLabel) }}">
                        </div>

                        <div class="form-group">
                            <label>Contenido</label>
                            <textarea id="content_html" name="content_html" class="form-control" rows="18">{{ old('content_html', $content->content_html ?? $defaultHtml) }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                @if($content?->submitted_at)
                                    Ultima entrega desde editor: {{ $content->submitted_at->format('d/m/Y H:i') }}
                                @else
                                    Guarda como borrador o entrega el documento como PDF.
                                @endif
                            </div>
                            <div>
                                <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">Guardar borrador</button>
                                <button type="submit" name="action" value="submit" class="btn btn-primary">Guardar y entregar PDF</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.querySelector('#content_html');
        if (!textarea || typeof ClassicEditor === 'undefined') return;

        ClassicEditor
            .create(textarea, {
                toolbar: [
                    'heading', '|',
                    'bold', 'italic', 'link',
                    'bulletedList', 'numberedList', '|',
                    'insertTable', 'blockQuote', 'undo', 'redo'
                ]
            })
            .catch(function () {});
    });
</script>
@endsection
