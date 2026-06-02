<?php

$quoteBinary = static function (?string $path): ?string {
    if (!$path) {
        return $path;
    }

    $path = trim($path);

    if (str_starts_with($path, '"') && str_ends_with($path, '"')) {
        return $path;
    }

    return '"' . $path . '"';
};

$defaultPdfBinary = PHP_OS_FAMILY === 'Windows'
    ? 'C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe'
    : 'wkhtmltopdf';

$defaultImgBinary = PHP_OS_FAMILY === 'Windows'
    ? 'C:/Program Files/wkhtmltopdf/bin/wkhtmltoimage.exe'
    : 'wkhtmltoimage';

return [

    /*
    |--------------------------------------------------------------------------
    | Snappy PDF / Image Configuration
    |--------------------------------------------------------------------------
    |
    | This option contains settings for PDF generation.
    |
    | Enabled:
    |    
    |    Whether to load PDF / Image generation.
    |
    | Binary:
    |    
    |    The file path of the wkhtmltopdf / wkhtmltoimage executable.
    |
    | Timeout:
    |    
    |    The amount of time to wait (in seconds) before PDF / Image generation is stopped.
    |    Setting this to false disables the timeout (unlimited processing time).
    |
    | Options:
    |
    |    The wkhtmltopdf command options. These are passed directly to wkhtmltopdf.
    |    See https://wkhtmltopdf.org/usage/wkhtmltopdf.txt for all options.
    |
    | Env:
    |
    |    The environment variables to set while running the wkhtmltopdf process.
    |
    */
    
    'pdf' => [
        'enabled' => true,
        'binary'  => $quoteBinary(env('WKHTML_PDF_BINARY', $defaultPdfBinary)),
        'timeout' => false,
        'options' => [
            'encoding' => 'UTF-8',
            'enable-local-file-access' => true,
            'no-stop-slow-scripts' => true,
            'disable-javascript' => true,
        ],
        'env'     => [],
    ],
    
    'image' => [
        'enabled' => true,
        'binary'  => $quoteBinary(env('WKHTML_IMG_BINARY', $defaultImgBinary)),
        'timeout' => false,
        'options' => [],
        'env'     => [],
    ],

];
