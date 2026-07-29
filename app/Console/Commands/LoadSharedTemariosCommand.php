<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Temario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadSharedTemariosCommand extends Command
{
    protected $signature = 'ams:load-shared-temarios {--apply : Guarda los temarios en la base de datos} {--force : Reemplaza el temario existente de la materia}';

    protected $description = 'Carga temarios estructurados desde PDFs compartidos para planeaciones.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        $rows = [];
        $saved = 0;

        foreach ($this->programs() as $program) {
            $subject = $this->findSubject($program);

            if (! $subject) {
                $rows[] = [$program['name'], $program['subject_key'] ?? '-', 'Materia no encontrada', '-', '-'];
                continue;
            }

            $existing = $subject->temarios()->first();
            if ($existing && ! $force) {
                $rows[] = [$subject->name, $program['subject_key'] ?? '-', 'Ya tiene temario', count($program['units']), 'Usa --force'];
                continue;
            }

            if ($apply) {
                $this->saveProgram($subject, $program, $existing);
                $saved++;
            }

            $rows[] = [
                $subject->name,
                $program['subject_key'] ?? '-',
                $apply ? 'Guardado' : 'DRY RUN',
                count($program['units']),
                collect($program['units'])->sum(fn ($unit) => count($unit['points'])) + count($program['units']),
            ];
        }

        $this->table(['Materia local', 'Clave', 'Estado', 'Unidades', 'Puntos'], $rows);
        $this->line($apply ? "Temarios guardados: {$saved}" : 'DRY RUN: usa --apply para guardar cambios.');

        return self::SUCCESS;
    }

    private function findSubject(array $program): ?Subject
    {
        if (! empty($program['subject_id'])) {
            return Subject::query()->find((int) $program['subject_id']);
        }

        if (! empty($program['subject_key'])) {
            $subject = Subject::query()->where('subject_key', (string) $program['subject_key'])->first();
            if ($subject) {
                return $subject;
            }
        }

        return Subject::query()->where('name', $program['local_name'])->first();
    }

    private function saveProgram(Subject $subject, array $program, ?Temario $existing): void
    {
        DB::transaction(function () use ($subject, $program, $existing): void {
            $subject->update([
                'subject_key' => $program['subject_key'] ?? $subject->subject_key,
                'hours_per_week' => $program['hours_per_week'],
                'weekly_theory_hours' => $program['weekly_theory_hours'],
                'weekly_practice_hours' => $program['weekly_practice_hours'],
                'annual_hours' => $program['annual_hours'],
                'annual_theory_hours' => $program['annual_theory_hours'],
                'annual_practice_hours' => $program['annual_practice_hours'],
                'type' => $program['type'],
                'subject_character' => $program['subject_character'],
                'is_active' => true,
            ]);

            $temario = $existing ?: new Temario();
            $temario->fill([
                'subject_id' => $subject->id,
                'title' => 'Temario ' . $program['name'],
                'description' => $program['general_objective'],
            ]);
            $temario->save();
            $temario->points()->delete();

            $position = 1;
            foreach ($program['units'] as $unit) {
                $temario->points()->create([
                    'position' => $position++,
                    'label' => (string) $unit['number'],
                    'level' => 1,
                    'type' => 'conceptual',
                    'content' => $unit['title'] . ' | Objetivo especifico: ' . $unit['objective'],
                ]);

                foreach ($unit['points'] as $point) {
                    $temario->points()->create([
                        'position' => $position++,
                        'label' => $point['label'],
                        'level' => $this->levelFromLabel($point['label']),
                        'type' => $point['type'],
                        'content' => $point['content'],
                    ]);
                }
            }
        });
    }

    private function levelFromLabel(string $label): int
    {
        return substr_count($label, '.') >= 2 ? 3 : 2;
    }

    private function point(string $label, string $content, string $type = 'conceptual'): array
    {
        return compact('label', 'content', 'type');
    }

    private function programs(): array
    {
        return [
            $this->inglesVi(),
            $this->esteticaIvPintura(),
            $this->esteticaVPintura(),
            $this->genero(),
            $this->tutoriasIv(),
        ];
    }

    private function inglesVi(): array
    {
        return [
            'local_name' => 'INGLÉS VI',
            'name' => 'Ingles VI',
            'subject_key' => '1603',
            'hours_per_week' => 3,
            'weekly_theory_hours' => 3,
            'weekly_practice_hours' => 0,
            'annual_hours' => 90,
            'annual_theory_hours' => 90,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Obligatoria de eleccion',
            'general_objective' => 'El alumno aplicara conocimientos linguisticos de la lengua meta en situaciones comunicativas que promuevan intercambio de informacion, expresion de opiniones, narracion y descripcion de eventos, asi como reflexion critica sobre la vida personal y el ambito global.',
            'units' => [
                [
                    'number' => 1,
                    'title' => 'Language description and simple facts',
                    'objective' => 'Aplicar elementos linguisticos para construir oraciones simples, describir hechos y socializar informacion de interes general.',
                    'points' => [
                        $this->point('1.1', 'Presente simple para expresar habitos, rutinas y hechos permanentes.'),
                        $this->point('1.2', 'Adverbios de frecuencia y expresiones de tiempo.'),
                        $this->point('1.3', 'Determinantes y cuantificadores para describir informacion general.'),
                        $this->point('1.4', 'Texto argumentativo o cientifico breve.'),
                        $this->point('1.5', 'Lectura detallada y reconocimiento de ideas principales y secundarias.', 'procedimental'),
                        $this->point('1.6', 'Disposicion para investigar y compartir informacion respetando opiniones.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Future events and planned activities',
                    'objective' => 'Usar estructuras de futuro para describir fenomenos naturales inevitables y actividades programadas.',
                    'points' => [
                        $this->point('2.1', 'Estructura del presente simple y presente continuo.'),
                        $this->point('2.2', 'Presente simple para expresar futuro con caracter permanente.'),
                        $this->point('2.3', 'Presente continuo para expresar futuro con caracter temporal.'),
                        $this->point('2.4', 'Textos orales y escritos de divulgacion.'),
                        $this->point('2.5', 'Lectura detallada de textos cientificos.', 'procedimental'),
                        $this->point('2.6', 'Apreciacion de actividades culturales relacionadas con fenomenos naturales.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Experience and change',
                    'objective' => 'Describir cambios, logros y resultados mediante presente perfecto simple y continuo.',
                    'points' => [
                        $this->point('3.1', 'Presente perfecto y adverbios already, yet y just.'),
                        $this->point('3.2', 'Presente perfecto continuo y adverbios since y for.'),
                        $this->point('3.3', 'Texto descriptivo cientifico.'),
                        $this->point('3.4', 'Identificacion de ideas principales y secundarias.', 'procedimental'),
                        $this->point('3.5', 'Reflexion sobre cambios e impactos de nuevas tecnologias.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 4,
                    'title' => 'Modals and responsible decisions',
                    'objective' => 'Expresar inferencias y grados de certeza para argumentar decisiones en contextos academicos y personales.',
                    'points' => [
                        $this->point('4.1', 'Inferencia certera con must.'),
                        $this->point('4.2', 'Inferencia probable con might, may y could.'),
                        $this->point('4.3', 'Textos narrativos y cuentos.'),
                        $this->point('4.4', 'Lectura detallada y formulacion de conclusiones.', 'procedimental'),
                        $this->point('4.5', 'Postura reflexiva ante las implicaciones de las acciones humanas.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 5,
                    'title' => 'Conditionals and ethical implications',
                    'objective' => 'Relacionar hechos y consecuencias mediante condicionales para reflexionar sobre cuestiones legales y morales.',
                    'points' => [
                        $this->point('5.1', 'Condicional cero para hechos permanentes.'),
                        $this->point('5.2', 'Primer condicional para consecuencias futuras.'),
                        $this->point('5.3', 'Segundo condicional para situaciones hipoteticas.'),
                        $this->point('5.4', 'Texto expositivo con tematica juridica.'),
                        $this->point('5.5', 'Conectores de causa y consecuencia.', 'procedimental'),
                        $this->point('5.6', 'Interes por evaluar derechos, obligaciones y decisiones responsables.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 6,
                    'title' => 'Passive voice and relevant events',
                    'objective' => 'Usar voz pasiva para describir eventos de relevancia periodistica de manera impersonal.',
                    'points' => [
                        $this->point('6.1', 'Voz pasiva en presente.'),
                        $this->point('6.2', 'Voz pasiva en pasado.'),
                        $this->point('6.3', 'Texto narrativo: noticia periodistica.'),
                        $this->point('6.4', 'Interaccion con textos informativos para seleccionar informacion relevante.', 'procedimental'),
                        $this->point('6.5', 'Escucha activa como base de la comunicacion respetuosa.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }

    private function esteticaIvPintura(): array
    {
        return [
            'local_name' => 'EDUCACIÓN ESTÉTICA Y ARTÍSTICA II',
            'name' => 'Educacion Estetica y Artistica IV: Pintura',
            'subject_key' => '1409',
            'hours_per_week' => 1,
            'weekly_theory_hours' => 1,
            'weekly_practice_hours' => 0,
            'annual_hours' => 30,
            'annual_theory_hours' => 30,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'subject_character' => 'Obligatoria de eleccion',
            'general_objective' => 'El alumno adquirira conocimientos y habilidades basicos para interpretar y analizar obras pictoricas, comprender la construccion del lenguaje visual, tomar conciencia sobre practicas responsables de la pintura con el medio ambiente y valorar el arte como experiencia transformadora.',
            'units' => [
                [
                    'number' => 1,
                    'title' => 'De las emociones al entorno',
                    'objective' => 'Apreciar la experiencia artistica ante un interes personal y reconocer elementos del lenguaje pictorico para interpretar el entorno.',
                    'points' => [
                        $this->point('1.1', 'Elementos del lenguaje pictorico y su interpretacion.'),
                        $this->point('1.2', 'Relaciones del lenguaje y su analisis en el espacio pictorico.'),
                        $this->point('1.3', 'Uso del espacio pictorico.', 'procedimental'),
                        $this->point('1.4', 'Uso de tecnicas y herramientas pictoricas tradicionales en la composicion bidimensional.', 'procedimental'),
                        $this->point('1.5', 'Manejo de equipo y materiales especificos de la disciplina.', 'procedimental'),
                        $this->point('1.6', 'Expresion creativa mediante materiales tradicionales, alternativos y herramientas digitales.', 'procedimental'),
                        $this->point('1.7', 'Apreciacion y valoracion de la experiencia artistica.', 'actitudinal'),
                        $this->point('1.8', 'Realizacion de practicas pictoricas responsables con procesos y materiales amigables con el medio ambiente.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'De la imaginacion a lo tangible',
                    'objective' => 'Categorizar representaciones visuales fisicas y virtuales, explorar recursos pictoricos y producir expresiones personales responsables.',
                    'points' => [
                        $this->point('2.1', 'Las representaciones pictoricas fisicas y virtuales.'),
                        $this->point('2.2', 'Ideacion, pensar y ver diferente el arte.'),
                        $this->point('2.3', 'Procesos y recursos pictoricos alternativos.', 'procedimental'),
                        $this->point('2.4', 'Exploracion de recursos pictoricos digitales.', 'procedimental'),
                        $this->point('2.5', 'Aplicacion de metodos en la creacion pictorica.', 'procedimental'),
                        $this->point('2.6', 'Valoracion de obras pictoricas fisicas y virtuales.', 'actitudinal'),
                        $this->point('2.7', 'Produccion de expresiones pictoricas personales y originales de forma responsable y respetuosa.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }

    private function esteticaVPintura(): array
    {
        return [
            'local_name' => 'EDUCACIÓN ESTÉTICA Y ARTÍSTICA IV',
            'name' => 'Educacion Estetica y Artistica V: Pintura',
            'subject_key' => '1509',
            'hours_per_week' => 1,
            'weekly_theory_hours' => 1,
            'weekly_practice_hours' => 0,
            'annual_hours' => 30,
            'annual_theory_hours' => 30,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL_PRACTICAL,
            'subject_character' => 'Obligatoria de eleccion',
            'general_objective' => 'El alumno desarrollara criterios de apreciacion, experimentacion e investigacion pictorica mediante el estudio de representaciones, formas, luz, color y procesos de sintesis visual.',
            'units' => [
                [
                    'number' => 1,
                    'title' => 'La luz, sombra y representacion en las formas',
                    'objective' => 'Reconocer la luz, el color y las cualidades opticas como elementos de construccion de formas y figuras.',
                    'points' => [
                        $this->point('1.1', 'Luz y sombra: sombra propia, claroscuro y valores tonales.'),
                        $this->point('1.2', 'Perspectiva lineal y perspectiva atmosferica.'),
                        $this->point('1.3', 'Color: mezclas pigmentarias y mezclas opticas.'),
                        $this->point('1.4', 'Fragmentacion de las formas y mezcla de materiales con collage y tecnicas mixtas.'),
                        $this->point('1.5', 'Aplicacion de luz, sombra y color en representaciones mimeticas.', 'procedimental'),
                        $this->point('1.6', 'Experimentacion optica de luz y color en formas y figuras.', 'procedimental'),
                        $this->point('1.7', 'Uso de collage y tecnicas mixtas para la expresion plastica.', 'procedimental'),
                        $this->point('1.8', 'Valoracion de convenciones de representacion surgidas del razonamiento artistico.', 'actitudinal'),
                        $this->point('1.9', 'Apreciacion de principios artisticos derivados de estudios opticos.', 'actitudinal'),
                        $this->point('1.10', 'Comprension de propuestas pictoricas que fragmentan y recomponen formas.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Transformacion de las representaciones',
                    'objective' => 'Identificar transformaciones en la logica de las representaciones figurativas y niveles de abstraccion mediante referencias visuales y documentales.',
                    'points' => [
                        $this->point('2.1', 'La transformacion y los procesos de sintesis de la forma.'),
                        $this->point('2.2', 'Los suenos, fantasias, geometrizacion y mancha como recursos de transformacion.'),
                        $this->point('2.3', 'Investigacion visual, documental y tecnica para la creacion pictorica.', 'procedimental'),
                        $this->point('2.4', 'Experimentacion figurativa y no figurativa en procesos pictoricos.', 'procedimental'),
                        $this->point('2.5', 'Apreciacion de manifestaciones pictoricas y niveles de abstraccion.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'El proyecto artistico',
                    'objective' => 'Reconocer el proceso de creacion artistica y desarrollar proyectos personales mediante tecnicas digitales, alternativas y materiales amigables con el medio ambiente.',
                    'points' => [
                        $this->point('3.1', 'La obra pictorica como discurso.'),
                        $this->point('3.2', 'La postura personal en la eleccion del entorno y sus argumentos en el proyecto pictorico.'),
                        $this->point('3.3', 'El boceto en soportes fisicos y digitales.'),
                        $this->point('3.4', 'El proyecto pictorico terminado.'),
                        $this->point('3.5', 'Creacion de proyectos pictoricos fisicos y virtuales.', 'procedimental'),
                        $this->point('3.6', 'Evaluacion del proceso creativo y presentacion de resultados.', 'procedimental'),
                        $this->point('3.7', 'Valoracion de la cultura visual y de la expresion artistica personal.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }

    private function genero(): array
    {
        return [
            'local_name' => 'GÉNERO Y PREVENCIÓN DE LAS VIOLENCIAS',
            'name' => 'Genero y prevencion de las violencias',
            'subject_key' => '8000',
            'hours_per_week' => 2,
            'weekly_theory_hours' => 2,
            'weekly_practice_hours' => 0,
            'annual_hours' => 60,
            'annual_theory_hours' => 60,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Obligatoria',
            'general_objective' => 'Las, les y los estudiantes construiran, desde la colectividad, estrategias propias que incidan en la transformacion de relaciones desiguales, de exclusion y de poder entre mujeres, hombres y personas sexo-diversas, para afrontar, prevenir y promover la erradicacion de las violencias de genero desde perspectivas de genero, juventudes, feminista, derechos humanos, comunidad, diversidad e interculturalidad con enfoque interseccional.',
            'units' => [
                [
                    'number' => 1,
                    'title' => 'Orden y mandatos de genero',
                    'objective' => 'Analizar mandatos de genero, estereotipos y prejuicios en expresiones culturales para cuestionarlos y prevenir su reproduccion escolar.',
                    'points' => [
                        $this->point('1.1', 'Categoria genero. El genero como construccion cultural de la diferencia sexual.'),
                        $this->point('1.2', 'Orden y mandatos de genero: desigualdad de genero e igualdad de genero.'),
                        $this->point('1.3', 'Prejuicios y estereotipos de genero en diversas expresiones culturales.'),
                        $this->point('1.4', 'Intersecciones socioculturales: racialidad, grupo etnico, clase, sexo, genero, edad e identidad sexual.'),
                        $this->point('1.5', 'Perspectiva de genero: caracteristicas e importancia en la prevencion de violencias.'),
                        $this->point('1.6', 'Analisis critico del genero como construccion cultural que reproduce desigualdades y discriminaciones.', 'procedimental'),
                        $this->point('1.7', 'Reflexion colectiva de estereotipos y prejuicios de genero en expresiones culturales.', 'procedimental'),
                        $this->point('1.8', 'Identificacion y deconstruccion de formas en que el orden y los mandatos de genero operan en la vida del alumnado.', 'procedimental'),
                        $this->point('1.9', 'Aplicacion del enfoque interseccional para comprender desigualdades y discriminaciones multiples.', 'procedimental'),
                        $this->point('1.10', 'Cuestionamiento del binarismo generico hegemonico y su naturalizacion.', 'procedimental'),
                        $this->point('1.11', 'Cuestionamiento de estereotipos y prejuicios impuestos por el orden y los mandatos de genero.', 'procedimental'),
                        $this->point('1.12', 'Disposicion para incorporar la perspectiva de genero en el desenvolvimiento personal.', 'actitudinal'),
                        $this->point('1.13', 'Valoracion de la perspectiva de genero como herramienta para mirar y cuestionar el mundo.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Relaciones afectivas y concepciones del cuerpo desde una perspectiva feminista y de genero',
                    'objective' => 'Analizar relaciones afectivas y formas de percibir el cuerpo para modificar visiones hegemonicas en la construccion de relaciones interpersonales.',
                    'points' => [
                        $this->point('2.1', 'Concepciones del cuerpo desde perspectivas feminista y de genero: diversidad corporal, cuerpo como territorio y consentimiento.'),
                        $this->point('2.2', 'Construccion del binarismo de genero: masculinidades y feminidades en el mundo actual.'),
                        $this->point('2.3', 'Relaciones afectivas: construccion y deconstruccion del amor romantico.'),
                        $this->point('2.4', 'Analisis critico de concepciones heteronormadas de cuerpos femeninos y masculinos.', 'procedimental'),
                        $this->point('2.5', 'Reflexion sobre la importancia de visibilizar la diversidad sexual y de genero.', 'procedimental'),
                        $this->point('2.6', 'Analisis de representaciones culturales del cuerpo, la sexualidad, el erotismo y la afectividad.', 'procedimental'),
                        $this->point('2.7', 'Analisis critico del amor romantico y su vinculacion con violencias en relaciones afectivas.', 'procedimental'),
                        $this->point('2.8', 'Desmitificacion del amor romantico para construir relaciones igualitarias en amor, sexo y cuerpo.', 'procedimental'),
                        $this->point('2.9', 'Apertura para asumir postura critica ante concepciones del cuerpo hegemonico.', 'actitudinal'),
                        $this->point('2.10', 'Respeto por la diversidad sexual y expresiones de genero en el ambito escolar.', 'actitudinal'),
                        $this->point('2.11', 'Disposicion para construir relaciones afectivas no violentas.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Prevencion, atencion y desactivacion de la violencia de genero en la ENP',
                    'objective' => 'Analizar tipos y modalidades de violencia de genero en situaciones escolares y comunitarias para visibilizarlas y proponer acciones de prevencion o atencion.',
                    'points' => [
                        $this->point('3.1', 'Violencia por razones de genero en marcos institucionales: tipos, modalidades y situacion actual en espacios escolares universitarios.'),
                        $this->point('3.2', 'Acciones de prevencion, manejo de conflictos y violencia de genero: respeto, reconocimiento, dialogo y escucha activa.'),
                        $this->point('3.3', 'Rutas e instancias vigentes para la atencion de violencias en la ENP.'),
                        $this->point('3.4', 'Identificacion de situaciones de violencia de genero y planteamiento de alternativas.', 'procedimental'),
                        $this->point('3.5', 'Ejercitacion hipotetica de estrategias restaurativas.', 'procedimental'),
                        $this->point('3.6', 'Analisis critico de textos sobre tipos y modalidades de violencia.', 'procedimental'),
                        $this->point('3.7', 'Conocimiento y manejo de rutas institucionales para atencion, prevencion, seguimiento y erradicacion de violencias.', 'procedimental'),
                        $this->point('3.8', 'Respeto y empatia con personas que han vivido violencias.', 'actitudinal'),
                        $this->point('3.9', 'Disposicion para asumir compromiso en el cuidado de si y de otras personas.', 'actitudinal'),
                        $this->point('3.10', 'Disposicion para promover rutas y acciones de prevencion, atencion y desactivacion de violencia de genero.', 'actitudinal'),
                        $this->point('3.11', 'Valoracion de los esfuerzos institucionales para prevenir, atender y erradicar la violencia de genero.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }

    private function tutoriasIv(): array
    {
        return [
            'local_name' => 'TUTORÍAS IV',
            'name' => 'Tutorias IV',
            'subject_key' => null,
            'hours_per_week' => 1,
            'weekly_theory_hours' => 1,
            'weekly_practice_hours' => 0,
            'annual_hours' => 34,
            'annual_theory_hours' => 34,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Formativa',
            'general_objective' => 'Favorecer la adaptacion, permanencia y desarrollo integral de los estudiantes durante su primer ano de bachillerato mediante autonomia academica, organizacion personal, habilidades socioemocionales, convivencia responsable, ciudadania digital, toma de decisiones y deteccion oportuna de dificultades.',
            'units' => [
                [
                    'number' => 1,
                    'title' => 'Integracion, diagnostico y adaptacion al bachillerato',
                    'objective' => 'Reconocer exigencias del bachillerato, favorecer la integracion del grupo e identificar necesidades academicas, personales y de convivencia.',
                    'points' => [
                        $this->point('1.1', 'La tutoria como espacio de acompanamiento.'),
                        $this->point('1.2', 'Transicion de la secundaria al bachillerato.'),
                        $this->point('1.3', 'Diagnostico inicial del estudiante.', 'procedimental'),
                        $this->point('1.4', 'Integracion y acuerdos del grupo.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Autonomia y gestion del aprendizaje',
                    'objective' => 'Desarrollar estrategias de organizacion, estudio y seguimiento academico para asumir responsabilidad sobre el aprendizaje.',
                    'points' => [
                        $this->point('2.1', 'Administracion del tiempo.', 'procedimental'),
                        $this->point('2.2', 'Planeacion de actividades academicas.', 'procedimental'),
                        $this->point('2.3', 'Procrastinacion: concepto, causas y estrategias para comenzar y mantener el trabajo.'),
                        $this->point('2.4', 'Distractores y condiciones de estudio.', 'procedimental'),
                        $this->point('2.5', 'Estrategias para aprender: lectura, apuntes, esquemas y recuperacion activa.', 'procedimental'),
                        $this->point('2.6', 'Preparacion de examenes.', 'procedimental'),
                        $this->point('2.7', 'Seguimiento del desempeno academico.', 'procedimental'),
                        $this->point('2.8', 'Busqueda de apoyo academico.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Recursos personales y bienestar escolar',
                    'objective' => 'Fortalecer recursos personales para afrontar exigencias escolares, reconocer emociones, manejar frustracion y solicitar apoyo.',
                    'points' => [
                        $this->point('3.1', 'Emociones y desempeno escolar.'),
                        $this->point('3.2', 'Estres academico y estrategias basicas de regulacion.', 'procedimental'),
                        $this->point('3.3', 'Tolerancia a la frustracion.', 'actitudinal'),
                        $this->point('3.4', 'Motivacion y responsabilidad.', 'actitudinal'),
                        $this->point('3.5', 'Autocuidado: sueno, alimentacion, descanso y uso saludable de dispositivos.', 'actitudinal'),
                        $this->point('3.6', 'Redes de apoyo y solicitud de ayuda.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 4,
                    'title' => 'Comunicacion, convivencia y participacion grupal',
                    'objective' => 'Desarrollar habilidades de comunicacion, colaboracion y resolucion de conflictos para una convivencia respetuosa e incluyente.',
                    'points' => [
                        $this->point('4.1', 'Comunicacion interpersonal.'),
                        $this->point('4.2', 'Escucha activa y empatia.', 'actitudinal'),
                        $this->point('4.3', 'Comunicacion asertiva.', 'procedimental'),
                        $this->point('4.4', 'Resolucion de conflictos.', 'procedimental'),
                        $this->point('4.5', 'Trabajo colaborativo.', 'procedimental'),
                        $this->point('4.6', 'Inclusion y cuidado del grupo.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => 5,
                    'title' => 'Ciudadania digital y prevencion de riesgos',
                    'objective' => 'Promover el uso responsable, seguro, critico y etico de tecnologias digitales y reconocer situaciones de riesgo.',
                    'points' => [
                        $this->point('5.1', 'Habitos digitales y atencion.', 'procedimental'),
                        $this->point('5.2', 'Privacidad y huella digital.'),
                        $this->point('5.3', 'Convivencia en entornos digitales.', 'actitudinal'),
                        $this->point('5.4', 'Pensamiento critico y uso etico de la informacion.', 'procedimental'),
                        $this->point('5.5', 'Prevencion y toma de decisiones ante riesgos.', 'procedimental'),
                    ],
                ],
                [
                    'number' => 6,
                    'title' => 'Toma de decisiones y plan personal de mejora',
                    'objective' => 'Aplicar un proceso reflexivo de toma de decisiones para evaluar la trayectoria escolar y elaborar un plan de mejora.',
                    'points' => [
                        $this->point('6.1', 'Toma responsable de decisiones.', 'procedimental'),
                        $this->point('6.2', 'Autoconocimiento aplicado al desempeno escolar.'),
                        $this->point('6.3', 'Evaluacion de la trayectoria academica.', 'procedimental'),
                        $this->point('6.4', 'Establecimiento de metas.', 'procedimental'),
                        $this->point('6.5', 'Plan personal de mejora.', 'procedimental'),
                        $this->point('6.6', 'Compromiso con la continuidad academica.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }
}
