<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use App\Models\Schedule;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;
use Exception;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scheduleData = [
            ['5110', 'CÁLCULO DIFERENCIAL', 'FERNANDO GARCÍA', '7:00-7:50', 'Lunes', 'TEÓRICA'],
            ['5110', 'CÁLCULO DIFERENCIAL', 'FERNANDO GARCÍA', '10:50-11:40', 'Martes', 'TEÓRICA'],
            ['5110', 'CÁLCULO DIFERENCIAL', 'FERNANDO GARCÍA', '7:50-8:40', 'Miércoles', 'TEÓRICA'],
            ['3110', 'MATEMÁTICAS III', 'FERNANDO GARCÍA', '7:50-8:40', 'Lunes', 'TEÓRICA'],
            ['3110', 'MATEMÁTICAS III', 'FERNANDO GARCÍA', '7:00-7:50', 'Martes', 'TEÓRICA'],
            ['3110', 'MATEMÁTICAS III', 'FERNANDO GARCÍA', '7:50-8:40', 'Martes', 'TEÓRICA'],
            ['3110', 'MATEMÁTICAS III', 'FERNANDO GARCÍA', '8:40-9:30', 'Miércoles', 'TEÓRICA'],
            ['3110', 'MATEMÁTICAS III', 'FERNANDO GARCÍA', '10:00-10:50', 'Jueves', 'TEÓRICA'],
            ['5120', 'MATEMÁTICAS FINANCIERAS I', 'FERNANDO GARCÍA', '8:40-9:30', 'Lunes', 'TEÓRICA'],
            ['5120', 'MATEMÁTICAS FINANCIERAS I', 'FERNANDO GARCÍA', '10:50-11:40', 'Miércoles', 'TEÓRICA'],
            ['5120', 'MATEMÁTICAS FINANCIERAS I', 'FERNANDO GARCÍA', '8:40-9:30', 'Jueves', 'TEÓRICA'],
            ['4110', 'MATEMÁTICAS IV', 'FERNANDO GARCÍA', '10:00-10:50', 'Lunes', 'TEÓRICA'],
            ['4110', 'MATEMÁTICAS IV', 'FERNANDO GARCÍA', '10:50-11:40', 'Lunes', 'TEÓRICA'],
            ['4110', 'MATEMÁTICAS IV', 'FERNANDO GARCÍA', '10:00-10:50', 'Martes', 'TEÓRICA'],
            ['4110', 'MATEMÁTICAS IV', 'FERNANDO GARCÍA', '13:50-14:40', 'Miércoles', 'TEÓRICA'],
            ['4110', 'MATEMÁTICAS IV', 'FERNANDO GARCÍA', '7:00-7:50', 'Jueves', 'TEÓRICA'],
            ['5110', 'TEMAS SELECTOS DE FÍSICA I', 'FERNANDO GARCÍA', '8:40-9:30', 'Martes', 'TEÓRICA'],
            ['5110', 'TEMAS SELECTOS DE FÍSICA I', 'FERNANDO GARCÍA', '10:00-10:50', 'Miércoles', 'TEÓRICA'],
            ['5110', 'TEMAS SELECTOS DE FÍSICA I', 'FERNANDO GARCÍA', '7:50-8:40', 'Jueves', 'TEÓRICA'],
            ['6120', 'MATEMÁTICAS FINANCIERAS II', 'FERNANDO GARCÍA', '13:50-14:40', 'Martes', 'TEÓRICA'],
            ['6120', 'MATEMÁTICAS FINANCIERAS II', 'FERNANDO GARCÍA', '7:00-7:50', 'Miércoles', 'TEÓRICA'],
            ['6120', 'MATEMÁTICAS FINANCIERAS II', 'FERNANDO GARCÍA', '10:50-11:40', 'Jueves', 'TEÓRICA'],

            ['3110', 'FÍSICA I', 'OLIVER MARTÍNEZ', '13:50-14:40', 'Lunes', 'PRÁCTICA'],
            ['3110', 'FÍSICA I', 'OLIVER MARTÍNEZ', '13:50-14:40', 'Martes', 'PRÁCTICA'],
            ['3110', 'FÍSICA I', 'OLIVER MARTÍNEZ', '12:10-13:00', 'Miércoles', 'PRÁCTICA'],
            ['3110', 'FÍSICA I', 'OLIVER MARTÍNEZ', '10:00-10:50', 'Viernes', 'PRÁCTICA'],
            ['3110', 'FÍSICA I', 'OLIVER MARTÍNEZ', '10:50-11:40', 'Viernes', 'PRÁCTICA'],
            ['6120', 'METODOLOGÍA DE LA INVESTIGACIÓN', 'OLIVER MARTÍNEZ', '13:00-13:50', 'Martes', 'TEÓRICA'],
            ['6120', 'METODOLOGÍA DE LA INVESTIGACIÓN', 'OLIVER MARTÍNEZ', '13:50-14:40', 'Jueves', 'TEÓRICA'],
            ['6120', 'METODOLOGÍA DE LA INVESTIGACIÓN', 'OLIVER MARTÍNEZ', '8:40-9:30', 'Viernes', 'TEÓRICA'],
            ['5110', 'GEOGRAFÍA', 'OLIVER MARTÍNEZ', '13:00-13:50', 'Miércoles', 'TEÓRICA'],
            ['5110', 'GEOGRAFÍA', 'OLIVER MARTÍNEZ', '13:00-13:50', 'Jueves', 'TEÓRICA'],
            ['5110', 'GEOGRAFÍA', 'OLIVER MARTÍNEZ', '7:00-7:50', 'Viernes', 'TEÓRICA'],
            ['5110', 'TEMAS SELECTOS DE QUÍMICA I', 'OLIVER MARTÍNEZ', '13:50-14:40', 'Miércoles', 'PRÁCTICA'],
            ['5110', 'TEMAS SELECTOS DE QUÍMICA I', 'OLIVER MARTÍNEZ', '12:10-13:00', 'Jueves', 'PRÁCTICA'],
            ['5110', 'TEMAS SELECTOS DE QUÍMICA I', 'OLIVER MARTÍNEZ', '7:50-8:40', 'Viernes', 'PRÁCTICA'],

            ['5120', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '10:00-10:50', 'Lunes', 'TEÓRICA'],
            ['5120', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '10:00-10:50', 'Martes', 'TEÓRICA'],
            ['5120', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '10:00-10:50', 'Miércoles', 'TEÓRICA'],
            ['5110', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '10:50-11:40', 'Lunes', 'TEÓRICA'],
            ['5110', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '7:00-7:50', 'Martes', 'TEÓRICA'],
            ['5110', 'HISTORIA UNIVERSAL CONTEMPORÁNEA', 'OSIRIS DOMÍNGUEZ', '8:40-9:30', 'Miércoles', 'TEÓRICA'],
            ['4110', 'ESTRUCTURA SOCIOECONÓMICA DE MÉXICO', 'OSIRIS DOMÍNGUEZ', '8:40-9:30', 'Martes', 'TEÓRICA'],
            ['4110', 'ESTRUCTURA SOCIOECONÓMICA DE MÉXICO', 'OSIRIS DOMÍNGUEZ', '10:50-11:40', 'Miércoles', 'TEÓRICA'],
            ['4110', 'ESTRUCTURA SOCIOECONÓMICA DE MÉXICO', 'OSIRIS DOMÍNGUEZ', '10:50-11:40', 'Viernes', 'TEÓRICA'],
            ['3110', 'HISTORIA DE MÉXICO II', 'OSIRIS DOMÍNGUEZ', '10:50-11:40', 'Martes', 'TEÓRICA'],
            ['3110', 'HISTORIA DE MÉXICO II', 'OSIRIS DOMÍNGUEZ', '7:50-7:40', 'Miércoles', 'TEÓRICA'],
            ['3110', 'HISTORIA DE MÉXICO II', 'OSIRIS DOMÍNGUEZ', '8:40-9:30', 'Viernes', 'TEÓRICA'],

            ['3110', 'LITERATURA I', 'VICTOR FUENTES', '12:10-13:00', 'Lunes', 'TEÓRICA'],
            ['3110', 'LITERATURA I', 'VICTOR FUENTES', '8:40-9:30', 'Martes', 'TEÓRICA'],
            ['3110', 'LITERATURA I', 'VICTOR FUENTES', '12:10-13:00', 'Viernes', 'TEÓRICA'],
            ['4110', 'LITERATURA II', 'VICTOR FUENTES', '13:00-13:50', 'Lunes', 'TEÓRICA'],
            ['4110', 'LITERATURA II', 'VICTOR FUENTES', '12:10-13:00', 'Martes', 'TEÓRICA'],
            ['4110', 'LITERATURA II', 'VICTOR FUENTES', '7:50-8:40', 'Viernes', 'TEÓRICA'],
            ['5120', 'GEOGRAFÍA', 'VICTOR FUENTES', '7:00-7:50', 'Martes', 'TEÓRICA'],
            ['5120', 'GEOGRAFÍA', 'VICTOR FUENTES', '12:10-13:00', 'Jueves', 'TEÓRICA'],
            ['5120', 'GEOGRAFÍA', 'VICTOR FUENTES', '10:00-10:50', 'Viernes', 'TEÓRICA'],
            ['6120', 'FILOSOFÍA', 'VICTOR FUENTES', '10:00-10:50', 'Martes', 'TEÓRICA'],
            ['6120', 'FILOSOFÍA', 'VICTOR FUENTES', '12:10-13:00', 'Miércoles', 'TEÓRICA'],
            ['6120', 'FILOSOFÍA', 'VICTOR FUENTES', '13:00-13:50', 'Jueves', 'TEÓRICA'],
            ['6120', 'FILOSOFÍA', 'VICTOR FUENTES', '10:50-11:40', 'Viernes', 'TEÓRICA'],

            ['5120', 'ORIENTACIÓN EDUCATIVA V', 'JANET PALMA', '7:00-7:50', 'Lunes', 'TEÓRICA'],
            ['5120', 'ORIENTACIÓN EDUCATIVA V', 'JANET PALMA', '10:00-10:50', 'Jueves', 'TEÓRICA'],
            ['5110', 'ORIENTACIÓN EDUCATIVA V', 'JANET PALMA', '7:50-8:40', 'Lunes', 'TEÓRICA'],
            ['5110', 'ORIENTACIÓN EDUCATIVA V', 'JANET PALMA', '8:40-9:30', 'Lunes', 'TEÓRICA'],
            ['3110', 'ORIENTACIÓN EDUCATIVA III', 'JANET PALMA', '10:00-10:50', 'Lunes', 'TEÓRICA'],
            ['3110', 'ORIENTACIÓN EDUCATIVA III', 'JANET PALMA', '10:50-11:40', 'Miércoles', 'TEÓRICA'],
            ['6120', 'ORIENTACIÓN EDUCATIVA VI', 'JANET PALMA', '10:50-11:40', 'Lunes', 'TEÓRICA'],
            ['6120', 'ORIENTACIÓN EDUCATIVA VI', 'JANET PALMA', '8:40-9:30', 'Jueves', 'TEÓRICA'],
            ['3110', 'TUTORÍAS III', 'JANET PALMA', '13:00-13:50', 'Lunes', 'TEÓRICA'],
            ['3110', 'TUTORÍAS III', 'JANET PALMA', '7:00-7:50', 'Jueves', 'TEÓRICA'],
            ['6120', 'PSICOLOGÍA II', 'JANET PALMA', '7:00-7:50', 'Martes', 'TEÓRICA'],
            ['6120', 'PSICOLOGÍA II', 'JANET PALMA', '7:50-8:40', 'Miércoles', 'TEÓRICA'],
            ['6120', 'PSICOLOGÍA II', 'JANET PALMA', '10:00-10:50', 'Viernes', 'TEÓRICA'],
            ['5120', 'TUTORÍAS V', 'JANET PALMA', '7:50-8:40', 'Martes', 'TEÓRICA'],
            ['5120', 'TUTORÍAS V', 'JANET PALMA', '12:10-13:00', 'Viernes', 'TEÓRICA'],
            ['5120', 'PSICOLOGÍA I', 'JANET PALMA', '10:50-11:40', 'Martes', 'TEÓRICA'],
            ['5120', 'PSICOLOGÍA I', 'JANET PALMA', '12:10-13:00', 'Miércoles', 'TEÓRICA'],
            ['5120', 'PSICOLOGÍA I', 'JANET PALMA', '7:50-8:40', 'Jueves', 'TEÓRICA'],
            ['5110', 'TUTORÍAS V', 'JANET PALMA', '12:10-13:00', 'Martes', 'TEÓRICA'],
            ['5110', 'TUTORÍAS V', 'JANET PALMA', '10:50-11:40', 'Viernes', 'TEÓRICA'],
            ['4110', 'TUTORÍAS IV', 'JANET PALMA', '7:00-7:50', 'Miércoles', 'TEÓRICA'],
            ['4110', 'TUTORÍAS IV', 'JANET PALMA', '10:50-11:40', 'Jueves', 'TEÓRICA'],
            ['6120', 'TUTORÍAS VI', 'JANET PALMA', '8:40-9:30', 'Miércoles', 'TEÓRICA'],
            ['6120', 'TUTORÍAS VI', 'JANET PALMA', '12:10-13:00', 'Jueves', 'TEÓRICA'],
            ['4110', 'ORIENTACIÓN EDUCATIVA IV', 'JANET PALMA', '10:00-10:50', 'Miércoles', 'TEÓRICA'],
            ['4110', 'ORIENTACIÓN EDUCATIVA IV', 'JANET PALMA', '7:00-7:50', 'Viernes', 'TEÓRICA'],
            ['6120', 'ORIENTACIÓN EDUCATIVA IV', 'JANET PALMA', '7:00-7:50', 'Viernes', 'TEÓRICA'],

            ['3110', 'POTTENCIA III', 'INFORMÁTICA', '8:40-9:30', 'Lunes', 'TEÓRICA'],
            ['4110', 'POTTENCIA IV', 'INFORMÁTICA', '12:10-13:00', 'Lunes', 'TEÓRICA'],
            ['5120', 'POTTENCIA V', 'INFORMÁTICA', '7:00-7:50', 'Viernes', 'TEÓRICA'],
            ['5110', 'POTTENCIA V', 'INFORMÁTICA', '8:40-9:30', 'Viernes', 'TEÓRICA'],
            ['6120', 'POTTENCIA VI', 'INFORMÁTICA', '12:10-13:00', 'Viernes', 'TEÓRICA'],

            ['3110', 'INGLÉS III', 'INGLÉS BACHILLERATO', '7:00-7:00', 'Lunes', 'TEÓRICA'],
            ['3110', 'INGLÉS III', 'INGLÉS BACHILLERATO', '7:50-8:40', 'Miércoles', 'TEÓRICA'],
            ['3110', 'INGLÉS III', 'INGLÉS BACHILLERATO', '7:50-8:40', 'Jueves', 'TEÓRICA'],
            ['4110', 'INGLÉS IV', 'INGLÉS BACHILLERATO', '7:50-8:40', 'Lunes', 'TEÓRICA'],
            ['4110', 'INGLÉS IV', 'INGLÉS BACHILLERATO', '12:10-13:00', 'Miércoles', 'TEÓRICA'],
            ['4110', 'INGLÉS IV', 'INGLÉS BACHILLERATO', '10:00-10:50', 'Viernes', 'TEÓRICA'],
            ['5110', 'INGLÉS V', 'INGLÉS BACHILLERATO', '7:50-8:40', 'Martes', 'TEÓRICA'],
            ['5110', 'INGLÉS V', 'INGLÉS BACHILLERATO', '10:50-11:40', 'Miércoles', 'TEÓRICA'],
            ['5110', 'INGLÉS V', 'INGLÉS BACHILLERATO', '8:40-9:30', 'Jueves', 'TEÓRICA'],
            ['5120', 'INGLÉS V', 'INGLÉS BACHILLERATO', '10:50-11:40', 'Lunes', 'TEÓRICA'],
            ['5120', 'INGLÉS V', 'INGLÉS BACHILLERATO', '8:40-9:30', 'Miércoles', 'TEÓRICA'],
            ['5120', 'INGLÉS V', 'INGLÉS BACHILLERATO', '10:50-11:40', 'Jueves', 'TEÓRICA'],
            ['6120', 'INGLÉS VI', 'INGLÉS BACHILLERATO', '8:40-9:30', 'Lunes', 'TEÓRICA'],
            ['6120', 'INGLÉS VI', 'INGLÉS BACHILLERATO', '7:00-7:50', 'Jueves', 'TEÓRICA'],

            ['4110', 'FÍSICA II', 'JONATHAN TORRES', '7:00-7:50', 'Lunes', 'PRÁCTICA'],
            ['4110', 'FÍSICA II', 'JONATHAN TORRES', '7:00-7:50', 'Martes', 'PRÁCTICA'],
            ['4110', 'FÍSICA II', 'JONATHAN TORRES', '7:50-8:40', 'Miércoles', 'PRÁCTICA'],
            ['4110', 'FÍSICA II', 'JONATHAN TORRES', '8:40-9:30', 'Miércoles', 'PRÁCTICA'],
            ['4110', 'FÍSICA II', 'JONATHAN TORRES', '12:10-13:00', 'Jueves', 'PRÁCTICA'],
            ['6120', 'ECOLOGÍA Y MEDIO AMBIENTE', 'JONATHAN TORRES', '7:50-8:40', 'Lunes', 'TEÓRICA'],
            ['6120', 'ECOLOGÍA Y MEDIO AMBIENTE', 'JONATHAN TORRES', '10:50-11:40', 'Miércoles', 'TEÓRICA'],
            ['6120', 'ECOLOGÍA Y MEDIO AMBIENTE', 'JONATHAN TORRES', '7:50-8:40', 'Jueves', 'TEÓRICA'],
            ['4110', 'BIOLOGÍA II', 'JONATHAN TORRES', '8:40-9:30', 'Lunes', 'PRÁCTICA'],
            ['4110', 'BIOLOGÍA II', 'JONATHAN TORRES', '7:50-8:40', 'Martes', 'PRÁCTICA'],
            ['4110', 'BIOLOGÍA II', 'JONATHAN TORRES', '13:00-13:50', 'Miércoles', 'PRÁCTICA'],
            ['4110', 'BIOLOGÍA II', 'JONATHAN TORRES', '10:00-10:50', 'Jueves', 'PRÁCTICA'],
            ['5110', 'CIENCIAS DE LA SALUD I', 'JONATHAN TORRES', '10:00-10:50', 'Lunes', 'PRÁCTICA'],
            ['5110', 'CIENCIAS DE LA SALUD I', 'JONATHAN TORRES', '12:10-13:00', 'Miércoles', 'PRÁCTICA'],
            ['5110', 'CIENCIAS DE LA SALUD I', 'JONATHAN TORRES', '10:50-11:40', 'Jueves', 'PRÁCTICA'],
            ['3110', 'BIOLOGÍA I', 'JONATHAN TORRES', '10:50-11:40', 'Lunes', 'PRÁCTICA'],
            ['3110', 'BIOLOGÍA I', 'JONATHAN TORRES', '12:10-13:00', 'Martes', 'PRÁCTICA'],
            ['3110', 'BIOLOGÍA I', 'JONATHAN TORRES', '10:50-11:40', 'Miércoles', 'PRÁCTICA'],

            ['5110', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '12:10-13:00', 'Lunes', 'TEÓRICA'],
            ['5110', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '7:00-7:50', 'Miércoles', 'TEÓRICA'],
            ['5110', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '10:00-10:50', 'Jueves', 'TEÓRICA'],
            ['5110', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '10:00-10:50', 'Viernes', 'TEÓRICA'],
            ['5120', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '13:00-13:50', 'Lunes', 'TEÓRICA'],
            ['5120', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '12:10-13:00', 'Martes', 'TEÓRICA'],
            ['5120', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '7:00-7:50', 'Jueves', 'TEÓRICA'],
            ['5120', 'ATENDER AL CLIENTE EN SU ENTORNO SOCIAL DE MANERA PRESENCIAL', 'HILDA CORTÉS', '10:50-11:40', 'Viernes', 'TEÓRICA'],
            ['3110', 'PLANEAR ACTIVIDADES Y ASIGNAR RECURSOS', 'HILDA CORTÉS', '10:00-10:50', 'Martes', 'TEÓRICA'],
            ['3110', 'PLANEAR ACTIVIDADES Y ASIGNAR RECURSOS', 'HILDA CORTÉS', '10:50-11:40', 'Jueves', 'TEÓRICA'],
            ['3110', 'PLANEAR ACTIVIDADES Y ASIGNAR RECURSOS', 'HILDA CORTÉS', '7:00-7:50', 'Viernes', 'TEÓRICA'],
            ['3110', 'PLANEAR ACTIVIDADES Y ASIGNAR RECURSOS', 'HILDA CORTÉS', '7:50-8:40', 'Viernes', 'TEÓRICA'],
            ['4110', 'GENERAR LA COMUNICACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '10:50-11:40', 'Martes', 'TEÓRICA'],
            ['4110', 'GENERAR LA COMUNICACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '13:00-13:50', 'Jueves', 'TEÓRICA'],
            ['4110', 'GENERAR LA COMUNICACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '13:50-14:40', 'Jueves', 'TEÓRICA'],
            ['4110', 'GENERAR LA COMUNICACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '8:40-9:30', 'Viernes', 'TEÓRICA'],
            ['3110', 'DIRECCIONAR Y EVALUAR EL PLAN DE ACCIÓN', 'HILDA CORTÉS', '13:00-13:50', 'Martes', 'TEÓRICA'],
            ['3110', 'DIRECCIONAR Y EVALUAR EL PLAN DE ACCIÓN', 'HILDA CORTÉS', '12:10-13:00', 'Jueves', 'TEÓRICA'],
            ['3110', 'DIRECCIONAR Y EVALUAR EL PLAN DE ACCIÓN', 'HILDA CORTÉS', '13:00-13:50', 'Viernes', 'TEÓRICA'],
            ['4110', 'CONTROLAR LA INFORMACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '7:50-8:40', 'Jueves', 'TEÓRICA'],
            ['4110', 'CONTROLAR LA INFORMACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '8:40-9:30', 'Jueves', 'TEÓRICA'],
            ['4110', 'CONTROLAR LA INFORMACIÓN DE LA EMPRESA', 'HILDA CORTÉS', '12:10-13:00', 'Viernes', 'TEÓRICA'],

            ['6120', 'ATENDER AL CLIENTE MEDIANTE TICS', 'HAYRI CALDERON', '7:00-7:50', 'Lunes', 'TEÓRICA'],
            ['6120', 'ATENDER AL CLIENTE MEDIANTE TICS', 'HAYRI CALDERON', '10:50-11:40', 'Martes', 'TEÓRICA'],
            ['6120', 'ATENDER AL CLIENTE MEDIANTE TICS', 'HAYRI CALDERON', '10:00-10:50', 'Jueves', 'TEÓRICA'],
            ['5120', 'DERECHO I', 'HAYRI CALDERON', '7:50-8:40', 'Lunes', 'TEÓRICA'],
            ['5120', 'DERECHO I', 'HAYRI CALDERON', '7:00-7:50', 'Miércoles', 'TEÓRICA'],
            ['5120', 'DERECHO I', 'HAYRI CALDERON', '8:40-9:30', 'Viernes', 'TEÓRICA'],
            ['6120', 'DETECTAR Y DAR SEGUIMIENTO', 'HAYRI CALDERON', '10:00-10:50', 'Lunes', 'TEÓRICA'],
            ['6120', 'DETECTAR Y DAR SEGUIMIENTO', 'HAYRI CALDERON', '8:40-9:30', 'Martes', 'TEÓRICA'],
            ['6120', 'DETECTAR Y DAR SEGUIMIENTO', 'HAYRI CALDERON', '7:50-8:40', 'Viernes', 'TEÓRICA'],
            ['6120', 'DERECHO II', 'HAYRI CALDERON', '12:10-13:00', 'Lunes', 'TEÓRICA'],
            ['6120', 'DERECHO II', 'HAYRI CALDERON', '12:10-13:00', 'Martes', 'TEÓRICA'],
            ['6120', 'DERECHO II', 'HAYRI CALDERON', '8:40-9:30', 'Viernes', 'TEÓRICA'],
            ['5120', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '13:50-14:40', 'Lunes', 'TEÓRICA'],
            ['5120', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '13:00-13:50', 'Martes', 'TEÓRICA'],
            ['5120', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '7:50-8:40', 'Miércoles', 'TEÓRICA'],
            ['5110', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '10:00-10:50', 'Martes', 'TEÓRICA'],
            ['5110', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '7:00-7:50', 'Jueves', 'TEÓRICA'],
            ['5110', 'ACTUALIZAR LOS SISTEMAS DE INFORMACIÓN DE LA EMPRESA', 'HAYRI CALDERON', '12:10-13:00', 'Viernes', 'TEÓRICA'],

            ['6120', 'SOCIOLOGIA II', 'ERICK MARQUEZ', '7:50-8:40', 'Martes', 'TEÓRICA'],
            ['6120', 'SOCIOLOGIA II', 'ERICK MARQUEZ', '10:00-10:50', 'Miércoles', 'TEÓRICA'],
            ['6120', 'SOCIOLOGIA II', 'ERICK MARQUEZ', '12:10-13:00', 'Viernes', 'TEÓRICA'],
            ['5120', 'SOCIOLOGIA I', 'ERICK MARQUEZ', '12:10-13:00', 'Lunes', 'TEÓRICA'],
            ['5120', 'SOCIOLOGIA I', 'ERICK MARQUEZ', '8:40-9:30', 'Martes', 'TEÓRICA'],
            ['5120', 'SOCIOLOGIA I', 'ERICK MARQUEZ', '7:50-8:40', 'Viernes', 'TEÓRICA'],
        ];

        $processedRelations = [
            'group_subject' => [],
            'group_teacher' => [],
            'subject_teacher' => []
        ];

        foreach($scheduleData as $index => $data) {
            try {
                $group = Group::where('name', $data[0])->first();
                $normalizedSubjectName = $this->normalizeSubjectName($data[1]);
                $subject = Subject::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U') = ?", [$normalizedSubjectName])->first();
                $normalizedTeacherName = $this->normalizeTeacherName($data[2]);
                $teacher = Teacher::whereHas('user', function($query) use ($normalizedTeacherName) {
                    $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U') = ?", [$normalizedTeacherName]);
                })->first();

                if (!$group || !$subject || !$teacher) {
                    throw new Exception('Datos incompletos');
                }

                $assignment = TeachingAssignment::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'group_id'   => $group->id,
                ]);

                DB::table('subject_teacher')->updateOrInsert([
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                ]);

                DB::table('group_subject')->updateOrInsert([
                    'group_id' => $group->id,
                    'subject_id' => $subject->id,
                ]);

                $day = $this->normalizeDay($data[4]);
                [$startTime, $endTime] = explode('-', $data[3]);

                Schedule::firstOrCreate([
                    'teaching_assignment_id' => $assignment->id,
                    'day_of_week' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'type' => 'teoria',
                ]);
            } catch (\Exception $e) {
                $this->command->error("✗ Error en posición {$index}: " . $e->getMessage());
                $this->command->warn("  Datos: " . implode(', ', $data));
            }
        }
    }

    public function normalizeSubjectName($name) {
        $name = trim($name);
        $name = mb_strtoupper($name);
        $replacements = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u'
        ];
        return strtr($name,$replacements);
    }

    public function normalizeTeacherName($name) {
        $name = trim($name);
        $name = mb_strtoupper($name);
        $replacements = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u'
        ];
        return strtr($name,$replacements);
    }

    public function normalizeDay($day) {
        $day = strtolower(trim($day));
        $daysMap = [
            'lunes' => 'lunes',
            'martes' => 'martes',
            'miércoles' => 'miercoles',
            'miercoles' => 'miercoles',
            'jueves' => 'jueves',
            'viernes' => 'viernes',
        ];       
        return $daysMap[$day] ?? $day;
    }
}
