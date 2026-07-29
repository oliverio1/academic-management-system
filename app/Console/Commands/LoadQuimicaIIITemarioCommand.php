<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Temario;
use App\Models\TemarioPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadQuimicaIIITemarioCommand extends Command
{
    protected $signature = 'ams:load-quimica-iii-temario {--force : Reemplaza el temario existente sin confirmar}';

    protected $description = 'Carga el temario DGIRE de Quimica III para planeaciones.';

    public function handle(): int
    {
        $subject = Subject::query()
            ->where('name', 'QUÍMICA III')
            ->orWhere('name', 'QUIMICA III')
            ->first();

        if (! $subject) {
            $this->error('No encontre la materia QUIMICA III. Importa primero el libro maestro o crea la materia.');
            return self::FAILURE;
        }

        $existingTemario = $subject->temarios()->where('title', 'Temario Quimica III')->first();

        if ($existingTemario && ! $this->option('force')) {
            if (! $this->confirm('Ya existe un temario de Quimica III. Quieres reemplazar sus puntos?')) {
                $this->warn('Cancelado.');
                return self::SUCCESS;
            }
        }

        DB::transaction(function () use ($subject, &$existingTemario) {
            $subject->update([
                'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
                'subject_character' => 'Obligatoria',
                'subject_key' => '1501',
                'hours_per_week' => 4,
                'weekly_theory_hours' => 3,
                'weekly_practice_hours' => 1,
                'annual_hours' => 120,
                'annual_theory_hours' => 90,
                'annual_practice_hours' => 30,
                'is_active' => true,
            ]);

            $temario = $existingTemario ?: Temario::create([
                'subject_id' => $subject->id,
                'title' => 'Temario Quimica III',
                'description' => $this->generalObjective(),
            ]);

            $temario->update([
                'subject_id' => $subject->id,
                'title' => 'Temario Quimica III',
                'description' => $this->generalObjective(),
            ]);

            $temario->points()->delete();

            $position = 1;
            foreach ($this->units() as $unit) {
                TemarioPoint::create([
                    'temario_id' => $temario->id,
                    'position' => $position++,
                    'label' => (string) $unit['number'],
                    'level' => 1,
                    'type' => 'conceptual',
                    'content' => $unit['title'] . ' | Objetivo especifico: ' . implode(' ', $unit['objectives']),
                ]);

                foreach ($unit['topics'] as $topic) {
                    TemarioPoint::create([
                        'temario_id' => $temario->id,
                        'position' => $position++,
                        'label' => $topic['label'],
                        'level' => 2,
                        'type' => 'conceptual',
                        'content' => $topic['content'],
                    ]);

                    foreach ($topic['subtopics'] as $subtopic) {
                        TemarioPoint::create([
                            'temario_id' => $temario->id,
                            'position' => $position++,
                            'label' => $subtopic['label'],
                            'level' => 3,
                            'type' => 'conceptual',
                            'content' => $subtopic['content'],
                        ]);
                    }
                }

                foreach ($unit['procedimental'] as $item) {
                    TemarioPoint::create([
                        'temario_id' => $temario->id,
                        'position' => $position++,
                        'label' => $item['label'],
                        'level' => 4,
                        'type' => 'procedimental',
                        'content' => $item['content'],
                    ]);
                }

                foreach ($unit['actitudinal'] as $item) {
                    TemarioPoint::create([
                        'temario_id' => $temario->id,
                        'position' => $position++,
                        'label' => $item['label'],
                        'level' => 4,
                        'type' => 'actitudinal',
                        'content' => $item['content'],
                    ]);
                }
            }

            $existingTemario = $temario;
        });

        $counts = $existingTemario->points()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $this->info('Temario de Quimica III cargado.');
        $this->line('Materia ID: ' . $subject->id);
        $this->line('Temario ID: ' . $existingTemario->id);
        $this->line('Conceptuales: ' . ($counts['conceptual'] ?? 0));
        $this->line('Procedimentales: ' . ($counts['procedimental'] ?? 0));
        $this->line('Actitudinales: ' . ($counts['actitudinal'] ?? 0));

        return self::SUCCESS;
    }

    private function generalObjective(): string
    {
        return 'El alumno aplicara conocimientos quimicos relacionados con las propiedades, las transformaciones y las aplicaciones de los materiales asi como el lenguaje quimico necesario para abordar problematicas actuales derivadas del uso de los dispositivos moviles, de la contaminacion del aire, y de la distribucion y utilizacion del agua, con sus respectivas consecuencias ambientales. Esto se lograra a traves de actividades colaborativas de investigacion documental, el analisis e interpretacion de textos de divulgacion cientifica y experimental, en espanol y en una segunda lengua, ademas del empleo de las Tecnologias de la Informacion y Comunicacion (TIC) para promover la formacion de un ciudadano consciente del cuidado de su entorno.';
    }

    private function units(): array
    {
        return [
            [
                'number' => 1,
                'title' => 'Elementos quimicos en los dispositivos moviles: una relacion innovadora',
                'objectives' => [
                    'Explicara las propiedades fisicas y quimicas de algunos elementos presentes en los dispositivos moviles, con base en el estudio de su estructura atomica, la informacion contenida en la tabla periodica y la modelizacion; para que reflexione sobre el impacto social y ambiental propiciado por la explotacion de los recursos naturales necesarios en su fabricacion.',
                    'Analizara el impacto ambiental y en la salud que tiene el consumo desmedido de los dispositivos moviles, por medio del analisis y la discusion de informacion, con el fin de que proponga acciones que favorezcan la reduccion, reutilizacion y reciclaje de los materiales que integran a este tipo de equipos y que promueva una cultura de consumidor responsable.',
                ],
                'topics' => [
                    $this->topic('1.1', 'Minerales y dispositivos moviles: impacto social y ambiental', [
                        ['1.1.1', 'Consumismo desmedido de dispositivos moviles y obsolescencia programada.'],
                        ['1.1.2', 'Sobreexplotacion de recursos naturales.'],
                        ['1.1.3', 'Principales minerales de algunos elementos presentes en equipos moviles: Si, C, Ag, Au, Cu, Sn, Ta, Ni y Ga.'],
                        ['1.1.4', 'Ubicacion geografica de yacimientos minerales.'],
                        ['1.1.5', 'Precio social de la extraccion de minerales como fuente primaria para obtener elementos quimicos; ejemplos: mineria en Mexico y coltan en la Republica Democratica del Congo.'],
                    ]),
                    $this->topic('1.2', 'Elementos quimicos en los dispositivos moviles', [
                        ['1.2.1', 'Quimica como ciencia: propositos y caracteristicas. Uso de modelos cientificos.'],
                        ['1.2.2', 'Composicion quimica de minerales de donde se extraen elementos usados en dispositivos moviles: mezcla, compuesto y elemento.'],
                        ['1.2.3', 'Nomenclatura de oxidos.'],
                        ['1.2.4', 'Atomo y particulas subatomicas.'],
                        ['1.2.5', 'Ubicacion de elementos en la tabla periodica: clasificacion, grupos, periodos, numero atomico y numero de masa.'],
                        ['1.2.6', 'Modelos atomicos: Bohr y modelo cuantico.'],
                        ['1.2.7', 'Niveles, subniveles, orbitales y configuraciones electronicas.'],
                        ['1.2.8', 'Propiedades fisicas y quimicas de elementos presentes en la tecnologia movil: conductividad electrica, temperatura de fusion y reactividad quimica.'],
                    ]),
                    $this->topic('1.3', 'Desecho de dispositivos moviles', [
                        ['1.3.1', 'Impacto ambiental del desecho de dispositivos moviles.'],
                        ['1.3.2', 'Reutilizacion, reduccion y reciclaje.'],
                    ]),
                ],
                'procedimental' => $this->items([
                    ['1.4', 'Busqueda, lectura y analisis de textos de divulgacion cientifica, en espanol y otra lengua, que aborden temas sobre la extraccion de los elementos, su aplicacion en los dispositivos moviles y su impacto en la sociedad y el ambiente.'],
                    ['1.5', 'Calculo del numero de particulas subatomicas de los elementos.'],
                    ['1.6', 'Representacion de la configuracion electronica de los elementos.'],
                    ['1.7', 'Realizacion de trabajos practicos relacionados con las propiedades fisicas y quimicas de los elementos, aplicando las normas de seguridad del laboratorio.'],
                    ['1.8', 'Redaccion de textos academicos relacionados con la importancia de los elementos presentes en los dispositivos moviles, y su impacto ambiental y social.'],
                ]),
                'actitudinal' => $this->items([
                    ['1.9', 'Valoracion del conocimiento quimico en el desarrollo cientifico-tecnologico y sus repercusiones en la sociedad.'],
                    ['1.10', 'Adopcion de una postura responsable sobre la reduccion del uso, reutilizacion y reciclado de los dispositivos moviles, para disminuir la explotacion y agotamiento de los recursos naturales.'],
                    ['1.11', 'Respeto a las ideas y aportaciones de sus companeros en la toma de decisiones sobre el uso de los dispositivos moviles.'],
                    ['1.12', 'Adopcion de una postura responsable y comprometida durante las actividades realizadas.'],
                ]),
            ],
            [
                'number' => 2,
                'title' => 'Control de las emisiones atmosfericas en las grandes urbes',
                'objectives' => [
                    'Aplicara los conocimientos quimicos relacionados con el uso de los combustibles fosiles, mediante el estudio de su reaccion de combustion, asi como la formacion de oxidos no metalicos, para explicar las causas y efectos del calentamiento global y la lluvia acida que impactan en el ambiente.',
                    'Valorara su responsabilidad en el cumplimiento de las medidas gubernamentales vigentes relacionadas con el control de la contaminacion del aire, mediante el analisis de su huella del carbono y de la informacion publicada sobre programas o acciones del gobierno local y nacional, para modificar su estilo de vida y participar en actividades que le permitan argumentar distintos puntos de vista sobre algunas acciones factibles que como ciudadanos, puedan contribuir al mejoramiento de la calidad del aire.',
                ],
                'topics' => [
                    $this->topic('2.1', 'Huella de carbono', [
                        ['2.1.1', 'Relacion entre produccion de CO2 y estilo de vida.'],
                        ['2.1.2', 'Reacciones de combustion completa e incompleta como proceso exotermico.'],
                        ['2.1.3', 'Hidrocarburos como fuente de energia: concepto de reaccion quimica, estructura y nomenclatura de los primeros diez alcanos.'],
                        ['2.1.4', 'Estequiometria en reacciones de combustion completa: concepto de mol y relaciones mol-mol, masa-mol y masa-masa.'],
                    ]),
                    $this->topic('2.2', 'Calidad del aire que respiramos', [
                        ['2.2.1', 'Fuentes de contaminacion naturales y antropogenicas.'],
                        ['2.2.2', 'Contaminantes primarios y secundarios: oxidos no metalicos, enlace covalente polar y no polar.'],
                        ['2.2.3', 'Difusion de contaminantes en el aire: propiedades del estado gaseoso y teoria cinetico-molecular.'],
                        ['2.2.4', 'Normatividad local y mundial: oxidos de nitrogeno, azufre y carbono, ozono troposferico, particulas suspendidas y concentracion en ppm.'],
                    ]),
                    $this->topic('2.3', 'Consecuencias de la contaminacion del aire', [
                        ['2.3.1', 'Implicaciones en la salud humana e indice para medir la calidad del aire.'],
                        ['2.3.2', 'Calentamiento global.'],
                        ['2.3.3', 'Lluvia acida: origen y efecto. Nomenclatura de oxiacidos, teoria acido-base de Arrhenius, escala de pH y reaccion de acidos con carbonatos.'],
                    ]),
                    $this->topic('2.4', 'Convertidores cataliticos metalicos en automotores', [
                        ['2.4.1', 'Reacciones de oxido-reduccion de oxidos de azufre, nitrogeno y carbono: numero de oxidacion, agente oxidante y agente reductor.'],
                        ['2.4.2', 'Medidas gubernamentales para el control de emisiones atmosfericas.'],
                    ]),
                ],
                'procedimental' => $this->items([
                    ['2.5', 'Elaboracion de tablas y graficos, analisis e interpretacion de resultados de la huella de carbono con apoyo de las TIC.'],
                    ['2.6', 'Comparacion y analisis de la normatividad nacional e internacional sobre la calidad del aire.'],
                    ['2.7', 'Representacion simbolica y nanoscopica de las principales sustancias contaminantes del aire empleando el modelo de particulas.'],
                    ['2.8', 'Realizacion de trabajos practicos relacionados con las propiedades de sustancias que lleven a la comprension del origen y efecto de los contaminantes.'],
                    ['2.9', 'Resolucion de problemas y casos sobre la contaminacion del aire.'],
                    ['2.10', 'Redaccion de textos academicos relacionados con la contaminacion del aire y posibles propuestas para reducirla.'],
                ]),
                'actitudinal' => $this->items([
                    ['2.11', 'Argumentacion sobre como el estilo de vida puede contribuir a mejorar la calidad del aire.'],
                    ['2.12', 'Tolerancia y compromiso en su participacion de manera colaborativa durante la realizacion de actividades experimentales y en el aula.'],
                    ['2.13', 'Valoracion de la cultura cientifica como herramienta para el analisis reflexivo de propuestas y opiniones relacionadas con la contaminacion del aire.'],
                    ['2.14', 'Adopcion de una postura honesta y responsable en el cumplimiento de las medidas gubernamentales para el control de emisiones vehiculares en las principales urbes.'],
                ]),
            ],
            [
                'number' => 3,
                'title' => 'Abastecimiento del agua potable: un desafio vital',
                'objectives' => [
                    'Analizara los aspectos quimicos y ambientales relacionados con el abastecimiento y uso del agua en la region en donde habita, por medio de la busqueda de informacion en fuentes impresas y digitales, para proponer acciones viables hacia una gestion sostenible del agua.',
                    'Explicara las propiedades fisicas y quimicas del agua a partir de la estructura tridimensional de la molecula, de tal forma que pueda comprender la importancia de este liquido como un recurso indispensable para la vida.',
                    'Aplicara la representacion simbolica de sustancias acidas, basicas y sales y su concentracion porcentual presente en productos de uso domestico para enriquecer su cultura cientifica y desarrollar una postura critica y responsable de su uso y eliminacion.',
                ],
                'topics' => [
                    $this->topic('3.1', 'Hacia la sostenibilidad del agua en el planeta', [
                        ['3.1.1', 'Distribucion mundial del agua.'],
                        ['3.1.2', 'Abastecimiento del agua potable: fuente y redes de distribucion en la region.'],
                        ['3.1.3', 'Demanda de agua potable, huella hidrica y uso en sociedad: pecuario, agricola, urbano, generacion de energia electrica e industrias.'],
                    ]),
                    $this->topic('3.2', 'Agua potable: un recurso vital', [
                        ['3.2.1', 'Caracteristicas fisicas, quimicas y microbiologicas del agua potable; normatividad mexicana.'],
                        ['3.2.2', 'Procesos fisicos y quimicos en la potabilizacion del agua: filtracion, floculacion, precipitacion, adsorcion con carbon activado, desinfeccion con cloro, ozono y radiacion UV.'],
                        ['3.2.3', 'Propiedades del agua: molecula polar, puente de hidrogeno, estado de agregacion, temperaturas de fusion y ebullicion, calor especifico, densidad, tension superficial y capilaridad.'],
                    ]),
                    $this->topic('3.3', 'El agua en nuestro entorno', [
                        ['3.3.1', 'Agua y poder disolvente: sustancias con enlace ionico y covalente polar.'],
                        ['3.3.2', 'Nomenclatura de hidruros, hidracidos, sales binarias y ternarias.'],
                        ['3.3.3', 'Disoluciones en el hogar: alimentos, medicamentos y productos de limpieza; concentraciones porcentuales.'],
                        ['3.3.4', 'Tratamiento de disoluciones acido-base: neutralizacion.'],
                        ['3.3.5', 'Medidas preventivas para el uso adecuado del agua.'],
                    ]),
                ],
                'procedimental' => $this->items([
                    ['3.4', 'Busqueda, lectura y analisis de textos de divulgacion cientifica, en espanol y otra lengua, sobre la problematica del agua y su gestion sostenible en los niveles local, nacional y mundial.'],
                    ['3.5', 'Analisis de la huella hidrica en el contexto cotidiano de los estudiantes.'],
                    ['3.6', 'Resolucion de casos sobre la problematica del agua para su uso sostenible.'],
                    ['3.7', 'Representacion de la molecula del agua por medio de modelos, para generar explicaciones acerca de sus propiedades y su relevancia en el entorno.'],
                    ['3.8', 'Realizacion de trabajos practicos relacionados con acidos, bases y sales, aplicando las normas de seguridad del laboratorio.'],
                    ['3.9', 'Comunicacion oral y escrita de los resultados de investigacion y/o trabajos practicos que incluyan tablas, graficos, modelos, simulaciones, entre otros, haciendo uso de las TIC.'],
                    ['3.10', 'Resolucion de ejercicios sobre nomenclatura y concentracion porcentual.'],
                ]),
                'actitudinal' => $this->items([
                    ['3.11', 'Valoracion de la importancia del agua para la vida, y su distribucion en el planeta.'],
                    ['3.12', 'Respeto a las ideas y aportaciones de sus companeros.'],
                    ['3.13', 'Argumentacion de una postura responsable en el cumplimiento de las medidas encaminadas al uso sostenible del agua.'],
                    ['3.14', 'Adopcion de una actitud comprometida para disminuir la contaminacion del agua ocasionada por el desecho de productos de uso cotidiano.'],
                ]),
            ],
        ];
    }

    private function topic(string $label, string $content, array $subtopics): array
    {
        return [
            'label' => $label,
            'content' => $content,
            'subtopics' => $this->items($subtopics),
        ];
    }

    private function items(array $items): array
    {
        return array_map(fn (array $item) => [
            'label' => $item[0],
            'content' => $item[1],
        ], $items);
    }
}
