<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28mm 26mm 24mm 26mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #111;
            line-height: 1.45;
        }
        .brand-header {
            width: 100%;
            text-align: left;
            margin: 0 0 10mm;
        }
        .brand-header img {
            max-width: 31.5mm;
            max-height: 14mm;
        }
        .ula-wordmark {
            font-family: Arial, sans-serif;
            font-size: 31px;
            line-height: 1;
            font-weight: 900;
            font-style: italic;
            letter-spacing: -2px;
        }
        .brand-name {
            font-size: 9px;
            margin-top: 1px;
        }
        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 18mm;
        }
        .document-body {
            font-size: 15px;
            margin-top: 0;
        }
        .document-body h1,
        .document-body h2,
        .document-body h3 {
            font-size: 15px;
            margin: 12px 0 7px;
        }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #777; padding: 5px; vertical-align: top; }
        th { background: #f2f2f2; }
        ul, ol { margin-top: 6px; }
        .document-body table { border-collapse: collapse; width: 100%; }
        .document-body table td,
        .document-body table th { border: 1px solid #777; padding: 5px; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/ula-logo.png');
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
        $title = $content->title ?: $documentLabel;
    @endphp

    <div class="brand-header">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="ULA">
        @else
            <div class="ula-wordmark">ULA</div>
            <div class="brand-name">Universidad Latinoamericana</div>
        @endif
    </div>

    <div class="document-title">{{ $title }}</div>

    <div class="document-body">
        {!! $content->content_html !!}
    </div>
</body>
</html>
