@php
    $meta = is_array($question->meta ?? null) ? $question->meta : [];
    $supportTitle = trim((string) ($meta['support_title'] ?? ''));
    $supportText = trim((string) ($meta['support_text'] ?? ''));
    $supportImagePath = trim((string) ($meta['support_image_path'] ?? ''));
    $supportImageUrl = $supportImagePath !== ''
        ? '/storage/' . ltrim($supportImagePath, '/')
        : trim((string) ($meta['support_image_url'] ?? ''));
    $hasSupport = $supportTitle !== '' || $supportText !== '' || $supportImageUrl !== '';
@endphp

@if($hasSupport)
    <div class="question-support border rounded bg-light p-3 mb-3">
        @if($supportTitle !== '')
            <div class="font-weight-bold mb-2">{{ $supportTitle }}</div>
        @endif

        @if($supportImageUrl !== '')
            <div class="mb-3 text-center">
                <img src="{{ $supportImageUrl }}" alt="{{ $supportTitle ?: 'Material de apoyo' }}" class="img-fluid rounded border" loading="lazy" decoding="async" style="max-height: 420px; object-fit: contain;">
            </div>
        @endif

        @if($supportText !== '')
            <div class="support-text" style="white-space: pre-wrap; line-height: 1.55;">{{ $supportText }}</div>
        @endif
    </div>
@endif
