<?php

if (! function_exists('subjectColor')) {
    function subjectColor(int $subjectId): string
    {
        $colors = [
            '#E3F2FD', // azul
            '#E8F5E9', // verde
            '#FFF3E0', // naranja
            '#FCE4EC', // rosa
            '#F3E5F5', // morado
            '#E0F2F1', // teal
            '#FFFDE7', // amarillo
        ];

        return $colors[$subjectId % count($colors)];
    }
}