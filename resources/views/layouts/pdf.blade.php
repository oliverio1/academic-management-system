<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 1.5cm; }

body {
    font-family: "DejaVu Serif", serif;
    font-size: 12px;
    line-height: 1.6;
    letter-spacing: 0.3px;
    font-kerning: normal;
}

.center { text-align: center; }
.right { text-align: right; }
.bold { font-weight: bold; }

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    font-size: 11px;
    font-weight: bold;
    line-height: 1.4;
}

td {
    font-size: 12px;
    line-height: 1.6;
}

p {
    margin: 4px 0;
}

th, td {
    border: 1px solid #000;
    padding: 4px;
}

.no-border td {
    border: none;
    padding: 2px 0;
}

.title {
    font-size: 14px;
    font-weight: bold;
    letter-spacing: 1px;
}

.subtitle {
    font-size: 12px;
    margin-top: 4px;
}

.signature-line {
    height: 40px;
}
</style>
</head>
<body>
@yield('content')
</body>
</html>
