<?php

namespace App\Console\Commands;

use App\Models\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SyncDgireEnpProgramsCommand extends Command
{
    protected $signature = 'ams:sync-dgire-enp-programs {--apply : Actualiza materias y descarga PDFs}';

    protected $description = 'Cruza materias locales con programas ENP DGIRE y sincroniza metadatos oficiales.';

    private const BASE_URL = 'https://www.dgire.unam.mx';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $programs = collect($this->programs())->keyBy('local');

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        $matched = 0;
        $updated = 0;
        $downloaded = 0;

        foreach ($subjects as $subject) {
            $localKey = $this->normalize($subject->name);
            $program = $programs->get($localKey);

            if (! $program) {
                $rows[] = [$subject->id, $subject->name, '-', '-', '-', 'Sin match DGIRE ENP'];
                continue;
            }

            $matched++;
            $updates = $this->subjectUpdates($program);
            $changes = collect($updates)
                ->filter(fn ($value, $field) => (string) ($subject->{$field} ?? '') !== (string) $value)
                ->keys()
                ->implode(', ');

            if ($apply && $changes !== '') {
                $subject->update($updates);
                $updated++;
            }

            $pdfStatus = 'Pendiente';
            if ($apply) {
                $pdfStatus = $this->downloadPdf($program) ? 'Descargado' : 'Error PDF';
                if ($pdfStatus === 'Descargado') {
                    $downloaded++;
                }
            }

            $rows[] = [
                $subject->id,
                $subject->name,
                $program['key'],
                $program['name'],
                $program['hours'],
                $changes !== '' ? ($apply ? 'Actualizado: ' : 'Cambios: ') . $changes : 'OK',
                $pdfStatus,
            ];
        }

        $this->table(
            ['ID', 'Materia local', 'Clave', 'Materia DGIRE', 'Hrs', 'Estado', 'PDF'],
            $rows
        );

        $this->line(($apply ? 'Sincronizacion aplicada.' : 'DRY RUN: usa --apply para guardar cambios.'));
        $this->line("Materias con match: {$matched}");
        $this->line("Materias actualizadas: {$updated}");
        $this->line("PDFs descargados: {$downloaded}");
        $this->warn('Nota: este comando no estructura contenidos de temario cuando el PDF no puede extraerse de forma confiable.');

        return self::SUCCESS;
    }

    private function subjectUpdates(array $program): array
    {
        $hours = (int) $program['hours'];
        $isTheoreticalPractical = $hours === 4;

        return [
            'subject_key' => (string) $program['key'],
            'hours_per_week' => $hours,
            'type' => $isTheoreticalPractical ? Subject::TYPE_THEORETICAL_PRACTICAL : Subject::TYPE_THEORETICAL,
            'subject_character' => $program['character'],
            'annual_hours' => $hours * 30,
            'weekly_theory_hours' => $isTheoreticalPractical ? 3 : $hours,
            'weekly_practice_hours' => $isTheoreticalPractical ? 1 : 0,
            'annual_theory_hours' => $isTheoreticalPractical ? 90 : $hours * 30,
            'annual_practice_hours' => $isTheoreticalPractical ? 30 : 0,
        ];
    }

    private function downloadPdf(array $program): bool
    {
        $key = (string) $program['key'];
        $path = "dgire/enp/{$key}.pdf";
        if (Storage::disk('local')->exists($path)) {
            return true;
        }

        $url = $program['pdf'] ?? "/images/planes/enp/{$key}.pdf";
        $response = Http::timeout(30)->get(str_starts_with($url, 'http') ? $url : self::BASE_URL . $url);
        if (! $response->successful() || ! str_contains($response->header('content-type', ''), 'pdf')) {
            return false;
        }

        Storage::disk('local')->put($path, $response->body());

        return true;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?: $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
    }

    /**
     * @return array<int, array{local:string,key:string,name:string,hours:int,character:string}>
     */
    private function programs(): array
    {
        $rows = [
            ['MATEMATICAS IV', '1400', 'Matematicas IV', 5, 'Obligatoria'],
            ['FISICA III', '1401', 'Fisica III', 4, 'Obligatoria'],
            ['LENGUA ESPANOLA', '1402', 'Lengua Espanola', 5, 'Obligatoria'],
            ['HISTORIA UNIVERSAL III', '1403', 'Historia Universal III', 3, 'Obligatoria'],
            ['LOGICA', '1404', 'Logica', 3, 'Obligatoria'],
            ['GEOGRAFIA', '1405', 'Geografia', 3, 'Obligatoria'],
            ['DIBUJO', '1406', 'Dibujo II', 2, 'Obligatoria'],
            ['INFORMATICA', '1412', 'Informatica', 2, 'Obligatoria'],
            ['EDUCACION FISICA IV', '1410', 'Educacion Fisica IV', 1, 'Obligatoria'],
            ['ORIENTACION EDUCATIVA', '1411', 'Orientacion Educativa IV', 1, 'Obligatoria'],
            ['INGLES IV', '1407', 'Ingles IV', 3, 'Obligatoria Eleccion'],
            ['GENERO Y PREVENCION DE LAS VIOLENCIAS', '8000', 'Genero y prevencion de las violencias', 0, 'Obligatoria', '/media/attachments/2025/03/21/enp-asignatura-genero.pdf'],
            ['EDUCACION ESTETICA Y ARTISTICA II', '1409', 'Educacion Estetica y Artistica', 1, 'Obligatoria Eleccion'],
            ['MATEMATICAS V', '1500', 'Matematicas V', 5, 'Obligatoria'],
            ['QUIMICA III', '1501', 'Quimica III', 4, 'Obligatoria'],
            ['BIOLOGIA IV', '1502', 'Biologia IV', 4, 'Obligatoria'],
            ['EDUCACION PARA LA SALUD', '1503', 'Educacion para la Salud', 4, 'Obligatoria'],
            ['HISTORIA DE MEXICO II', '1504', 'Historia de Mexico II', 3, 'Obligatoria'],
            ['ETIMOLOGIAS GRECOLATINAS', '1505', 'Etimologias Grecolatinas', 2, 'Obligatoria'],
            ['ETICA', '1512', 'Etica', 2, 'Obligatoria'],
            ['EDUCACION FISICA V', '1513', 'Educacion Fisica V', 1, 'Obligatoria'],
            ['ORIENTACION EDUCATIVA V', '1515', 'Orientacion Educativa V', 1, 'Obligatoria'],
            ['LITERATURA UNIVERSAL', '1516', 'Literatura Universal', 3, 'Obligatoria'],
            ['INGLES V', '1506', 'Ingles V', 3, 'Obligatoria Eleccion'],
            ['EDUCACION ESTETICA Y ARTISTICA IV', '1514', 'Educacion Estetica y Artistica', 1, 'Obligatoria Eleccion'],
            ['DERECHO', '1601', 'Derecho', 2, 'Obligatoria'],
            ['LITERATURA MEXICANA E IBEROAMERICANA', '1602', 'Literatura Mexicana e Iberoamericana', 3, 'Obligatoria'],
            ['PSICOLOGIA', '1609', 'Psicologia', 4, 'Obligatoria'],
            ['MATEMATICAS VI', '1600', 'Matematicas VI', 5, 'Obligatoria'],
            ['DIBUJO CONSTRUCTIVO II', '1610', 'Dibujo Constructivo II', 3, 'Obligatoria'],
            ['FISICA IV', '1611', 'Fisica IV', 4, 'Obligatoria'],
            ['QUIMICA IV', '1612', 'Quimica IV', 4, 'Obligatoria'],
            ['BIOLOGIA V', '1613', 'Biologia V', 4, 'Obligatoria'],
            ['INTRODUCCION A LAS CIENCIAS SOCIALES Y ECONOMICAS', '1615', 'Introduccion a las Ciencias Sociales y Economicas', 3, 'Obligatoria'],
            ['PROBLEMAS SOCIALES POLITICOS Y ECONOMICOS DE MEXICO', '1616', 'Problemas Sociales, Politicos y Economicos de Mexico', 3, 'Obligatoria'],
            ['GEOGRAFIA ECONOMICA', '1614', 'Geografia Economica', 3, 'Obligatoria'],
            ['HISTORIA DE LA CULTURA', '1617', 'Historia de la Cultura', 3, 'Obligatoria'],
            ['HISTORIA DE LAS DOCTRINAS FILOSOFICAS', '1618', 'Historia de las Doctrinas Filosoficas', 3, 'Obligatoria'],
            ['GEOGRAFIA POLITICA', '1707', 'Geografia Politica', 3, 'Optativa General'],
            ['ESTADISTICA Y PROBABILIDAD', '1712', 'Estadistica y Probabilidad', 3, 'Optativa General'],
            ['SOCIOLOGIA', '1720', 'Sociologia', 3, 'Optativa General'],
            ['TEMAS SELECTOS DE BIOLOGIA', '1711', 'Temas Selectos de Biologia', 3, 'Optativa General'],
            ['TEMAS SELECTOS DE MATEMATICAS', '1710', 'Temas Selectos de Matematicas', 3, 'Optativa General'],
            ['COMUNICACION VISUAL', '1715', 'Comunicacion Visual', 3, 'Optativa General'],
            ['HISTORIA DEL ARTE', '1718', 'Historia del Arte', 3, 'Optativa General'],
            ['INGLES VI', '1603', 'Ingles VI', 3, 'Obligatoria Eleccion'],
        ];

        return array_map(fn ($row) => [
            'local' => $this->normalize($row[0]),
            'key' => $row[1],
            'name' => $row[2],
            'hours' => $row[3],
            'character' => $row[4],
            'pdf' => $row[5] ?? null,
        ], $rows);
    }
}
