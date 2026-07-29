<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Temario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadOperationalDocxTemariosCommand extends Command
{
    protected $signature = 'ams:load-operational-temarios {--apply : Guarda los temarios en la base de datos} {--force : Reemplaza el temario existente de la materia}';

    protected $description = 'Carga temarios extraidos de planeaciones operativas compartidas.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $rows = [];
        $saved = 0;

        foreach ($this->programs() as $program) {
            $subject = Subject::query()->where('name', $program['local_name'])->first();

            if (! $subject) {
                $rows[] = [$program['local_name'], '-', 'Materia no encontrada', '-', '-'];
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

    private function saveProgram(Subject $subject, array $program, ?Temario $existing): void
    {
        DB::transaction(function () use ($subject, $program, $existing): void {
            $subject->update([
                'subject_key' => $program['subject_key'] ?? $subject->subject_key,
                'hours_per_week' => $program['hours_per_week'] ?? $subject->hours_per_week,
                'weekly_theory_hours' => $program['weekly_theory_hours'] ?? $subject->weekly_theory_hours,
                'weekly_practice_hours' => $program['weekly_practice_hours'] ?? $subject->weekly_practice_hours,
                'annual_hours' => $program['annual_hours'] ?? $subject->annual_hours,
                'annual_theory_hours' => $program['annual_theory_hours'] ?? $subject->annual_theory_hours,
                'annual_practice_hours' => $program['annual_practice_hours'] ?? $subject->annual_practice_hours,
                'type' => $program['type'] ?? $subject->type,
                'subject_character' => $program['subject_character'] ?? $subject->subject_character,
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
            $this->potencia('POTENCIA V', 'Potencia V'),
            $this->potencia('POTENCIA VI', 'Potencia VI'),
            $this->innovacionYEmprender(),
            $this->inteligenciaEmocionalBachillerato(),
        ];
    }

    private function potencia(string $localName, string $name): array
    {
        return [
            'local_name' => $localName,
            'name' => $name,
            'subject_key' => '1600',
            'hours_per_week' => 1,
            'weekly_theory_hours' => 1,
            'weekly_practice_hours' => 0,
            'annual_hours' => 30,
            'annual_theory_hours' => 30,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Obligatoria',
            'general_objective' => 'El alumno dominara el uso de herramientas de Inteligencia Artificial Generativa como asistentes para la resolucion de problemas, la investigacion academica y la creacion de contenido multimedia. Desarrollara pensamiento critico para evaluar la veracidad de la informacion, comprender dilemas eticos de la tecnologia y aplicar estas herramientas para potenciar su productividad y creatividad en entornos academicos y profesionales.',
            'units' => [
                [
                    'number' => '1',
                    'title' => 'Metodologias de resolucion de problemas con tecnologia e IA',
                    'objective' => 'Comprender los fundamentos de los Modelos de Lenguaje y diferenciar entre ChatGPT, Gemini y Copilot. Dominar la estructura de un prompt eficaz para obtener respuestas precisas y resolver problemas logicos basicos.',
                    'points' => [
                        $this->point('1.1', 'Inteligencia Artificial Generativa vs. buscadores tradicionales.', 'conceptual'),
                        $this->point('1.2', 'Alucinaciones y sesgos.', 'conceptual'),
                        $this->point('1.3', 'Estructura del prompt.', 'conceptual'),
                        $this->point('1.4', 'Creacion de cuentas y configuracion de entorno seguro.', 'procedimental'),
                        $this->point('1.5', 'Diseno y refinamiento de prompts mediante iteracion.', 'procedimental'),
                        $this->point('1.6', 'Uso de IA para lluvia de ideas y organizacion.', 'procedimental'),
                        $this->point('1.7', 'Valoracion de la IA como copiloto, no como sustituto.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '2',
                    'title' => 'Ciudadania digital y compromiso con el entorno',
                    'objective' => 'Desarrollar habilidades de investigacion critica para verificar fuentes y citar informacion generada por IA. Aplicar tecnicas de reescritura y adaptacion de estilo para comunicar mensajes a diferentes audiencias desde una ciudadania digital etica.',
                    'points' => [
                        $this->point('2.1', 'Veracidad y fact-checking.', 'conceptual'),
                        $this->point('2.2', 'Plagio academico vs. uso etico.', 'conceptual'),
                        $this->point('2.3', 'Estilos de redaccion y tono.', 'conceptual'),
                        $this->point('2.4', 'Uso de Copilot o Gemini con acceso a internet para validar datos.', 'procedimental'),
                        $this->point('2.5', 'Adaptacion de textos en tono formal e informal.', 'procedimental'),
                        $this->point('2.6', 'Storytelling asistido.', 'procedimental'),
                        $this->point('2.7', 'Responsabilidad sobre la informacion compartida y generada.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '3',
                    'title' => 'Pensamiento critico y adaptabilidad en entornos tecnologicos',
                    'objective' => 'Dominar la generacion de contenido visual mediante prompts descriptivos y tecnicos. Desarrollar habilidades de preproduccion multimedia, guiones y storyboards adaptados a nuevos formatos digitales.',
                    'points' => [
                        $this->point('3.1', 'Generacion de imagen por difusion.', 'conceptual'),
                        $this->point('3.2', 'Estilos artisticos y tecnicos en prompts.', 'conceptual'),
                        $this->point('3.3', 'Vision artificial: imagen a texto.', 'conceptual'),
                        $this->point('3.4', 'Creacion de imagenes con DALL-E 3 o Copilot.', 'procedimental'),
                        $this->point('3.5', 'Creacion de logos e identidad visual.', 'procedimental'),
                        $this->point('3.6', 'Guionismo tecnico para video.', 'procedimental'),
                        $this->point('3.7', 'Respeto a la propiedad intelectual y derechos de autor en arte digital.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '4',
                    'title' => 'Etica en el uso y desarrollo de tecnologias emergentes',
                    'objective' => 'Integrar conocimientos de logica y productividad para generar codigo basico y automatizar tareas. Desarrollar un proyecto final de emprendimiento digital que integre etica, texto, imagen y estrategia.',
                    'points' => [
                        $this->point('4.1', 'Logica de programacion no-code.', 'conceptual'),
                        $this->point('4.2', 'Automatizacion de datos.', 'conceptual'),
                        $this->point('4.3', 'Futuro del trabajo y empleabilidad.', 'conceptual'),
                        $this->point('4.4', 'Generacion de codigo HTML/CSS con IA.', 'procedimental'),
                        $this->point('4.5', 'Creacion de tablas y formulas en Excel con IA.', 'procedimental'),
                        $this->point('4.6', 'Simulaciones de rol y entrevistas.', 'procedimental'),
                        $this->point('4.7', 'Adaptabilidad y aprendizaje continuo.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }

    private function innovacionYEmprender(): array
    {
        return [
            'local_name' => 'INNOVACIÓN Y EMPRENDER',
            'name' => 'Innovacion y emprender',
            'hours_per_week' => 2,
            'weekly_theory_hours' => 2,
            'weekly_practice_hours' => 0,
            'annual_hours' => 61,
            'annual_theory_hours' => 61,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Cultural',
            'general_objective' => 'Preparar a los alumnos fortaleciendo su formacion integral mediante herramientas para abordar conocimientos vinculados con emprender o manejar un negocio, promoviendo una cultura colaborativa, creativa e interdisciplinaria aplicable a la vida personal y social.',
            'units' => [
                [
                    'number' => '1',
                    'title' => 'Identificacion del proyecto de emprendimiento',
                    'objective' => 'Adquirir conocimientos basicos para crear un proyecto de emprendimiento e identificar su potencial mediante el sistema FODA.',
                    'points' => [
                        $this->point('1.1', 'Sistema FODA, conceptos usuales de contabilidad y contexto inicial para comprender el emprendimiento.', 'conceptual'),
                        $this->point('1.2', 'Aplicacion de una metodologia en el emprendimiento.', 'conceptual'),
                        $this->point('1.3', 'Importancia de la mision y vision empresarial.', 'conceptual'),
                        $this->point('1.4', 'Identificacion del proyecto y determinacion del momento adecuado del emprendimiento.', 'procedimental'),
                        $this->point('1.5', 'Inteligencias multiples y personalidad emprendedora.', 'procedimental'),
                        $this->point('1.6', 'Etapas del emprendimiento e identificacion de necesidades del cliente.', 'procedimental'),
                        $this->point('1.7', 'Identificacion y validacion de hipotesis.', 'procedimental'),
                        $this->point('1.8', 'Metodo SCAMPER y design thinking.', 'procedimental'),
                        $this->point('1.9', 'Mision, vision, valores y objetivos de los emprendedores.', 'conceptual'),
                        $this->point('1.10', 'Trabajo colaborativo en equipo.', 'actitudinal'),
                        $this->point('1.11', 'Creatividad e innovacion en el desarrollo de un negocio.', 'actitudinal'),
                        $this->point('1.12', 'Aplicacion responsable de tecnicas metodologicas en el marco comercial.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '2',
                    'title' => 'Plan de marketing',
                    'objective' => 'Conocer y practicar la distincion de modelos de negocio, nombres de empresa, elementos de marca y presentacion de productos.',
                    'points' => [
                        $this->point('2.1', 'Modelos de negocio, nombre de empresa, elementos de marca y presentacion de productos.', 'conceptual'),
                        $this->point('2.2', 'Tecnicas de marketing como herramientas para promover un producto.', 'conceptual'),
                        $this->point('2.3', 'Marketing mezcla, digital y neuronal.', 'conceptual'),
                        $this->point('2.4', 'Investigacion de mercado fisico y virtual.', 'procedimental'),
                        $this->point('2.5', 'Economia y normatividad del producto o servicio; frecuencia y uso.', 'conceptual'),
                        $this->point('2.6', 'Empaque, embalaje y tematica del producto.', 'procedimental'),
                        $this->point('2.7', 'Definicion de target fisico o virtual.', 'procedimental'),
                        $this->point('2.8', 'Tipos de oferta y demanda.', 'conceptual'),
                        $this->point('2.9', 'Proyecciones de demanda enfocadas en temporadas o sentimientos del mercado.', 'procedimental'),
                        $this->point('2.10', 'Analisis de precios e insights del proyecto.', 'procedimental'),
                        $this->point('2.11', 'Codigo cultural, codigo biologico y los tres cerebros: cortex, limbico y reptil.', 'conceptual'),
                        $this->point('2.12', 'Tecnicas de persuasion empresarial, copywriting y storytelling.', 'procedimental'),
                        $this->point('2.13', 'Diseno practico del prototipo del producto del proyecto.', 'procedimental'),
                        $this->point('2.14', 'Campana publicitaria: elaboracion de comercial y presentacion del producto terminado.', 'procedimental'),
                    ],
                ],
                [
                    'number' => '3',
                    'title' => 'Estructura de la organizacion',
                    'objective' => 'Conocer el marco teorico de la estructura organizacional de un negocio para asignar funciones por areas y aplicar tecnicas de desarrollo operativo en una organizacion.',
                    'points' => [
                        $this->point('3.1', 'Herramientas para establecer una estructura organizacional.', 'conceptual'),
                        $this->point('3.2', 'Organigramas y principales tipos.', 'conceptual'),
                        $this->point('3.3', 'Importancia de la cultura organizacional.', 'conceptual'),
                        $this->point('3.4', 'Responsabilidad social en las empresas.', 'actitudinal'),
                        $this->point('3.5', 'Marco juridico para la integracion de una empresa.', 'conceptual'),
                        $this->point('3.6', 'Estructura organizacional y cadena de mando.', 'conceptual'),
                        $this->point('3.7', 'Funciones y objetivos internos y externos de las organizaciones.', 'conceptual'),
                        $this->point('3.8', 'Diseno de organigrama.', 'procedimental'),
                        $this->point('3.9', 'Cultura organizacional.', 'actitudinal'),
                        $this->point('3.10', 'Constitucion legal para la creacion de una sociedad.', 'conceptual'),
                        $this->point('3.10.1', 'Propiedad intelectual: derechos de autor, marcas, patentes, licencias y franquicias.', 'conceptual'),
                        $this->point('3.10.2', 'Controles de calidad de las empresas.', 'procedimental'),
                        $this->point('3.11', 'Empresas responsables: sustentabilidad y participacion ciudadana.', 'actitudinal'),
                        $this->point('3.12', 'Diseno practico de un organigrama y funciones aplicado al proyecto.', 'procedimental'),
                        $this->point('3.13', 'Funcion social del emprendimiento como parte de la responsabilidad social del negocio.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '4',
                    'title' => 'Rendimiento y retorno de inversion a corto y largo plazo',
                    'objective' => 'Conocer la rentabilidad del proyecto de inversion y los riesgos a corto y largo plazo para determinar el uso y aplicacion de recursos.',
                    'points' => [
                        $this->point('4.1', 'Conceptos financieros para evaluar el riesgo de la inversion en un emprendimiento.', 'conceptual'),
                        $this->point('4.2', 'Obligaciones y sanciones fiscales de las empresas.', 'conceptual'),
                        $this->point('4.3', 'Fuentes de financiamiento.', 'conceptual'),
                        $this->point('4.4', 'Importancia del comercio internacional.', 'conceptual'),
                        $this->point('4.5', 'Resultados de los proyectos: ingresos, ventas, costos y gastos.', 'procedimental'),
                        $this->point('4.6', 'Resultados de los proyectos: impuestos y utilidades.', 'procedimental'),
                        $this->point('4.7', 'Factores de riesgo para emprendedores.', 'conceptual'),
                        $this->point('4.8', 'Fuentes de financiamiento: crowdfunding y crowdlending.', 'conceptual'),
                        $this->point('4.9', 'Crowdfunding como alternativa de financiamiento.', 'conceptual'),
                        $this->point('4.10', 'Impacto de indicadores financieros en un negocio.', 'procedimental'),
                        $this->point('4.11', 'Investigacion de alternativas del sector financiero para financiar negocios.', 'procedimental'),
                    ],
                ],
            ],
        ];
    }

    private function inteligenciaEmocionalBachillerato(): array
    {
        return [
            'local_name' => 'INTELIGENCIA EMOCIONAL',
            'name' => 'Inteligencia emocional',
            'hours_per_week' => 1,
            'weekly_theory_hours' => 1,
            'weekly_practice_hours' => 0,
            'annual_hours' => 30,
            'annual_theory_hours' => 30,
            'annual_practice_hours' => 0,
            'type' => Subject::TYPE_THEORETICAL,
            'subject_character' => 'Optativa',
            'general_objective' => 'El alumno desarrollara habilidades de inteligencia emocional para reconocer, comprender y regular sus emociones, fortalecer su autoestima, comunicarse de manera asertiva, convivir responsablemente y tomar decisiones sanas en su vida escolar, familiar y social.',
            'units' => [
                [
                    'number' => '1',
                    'title' => 'Emociones, autoconocimiento e identidad adolescente',
                    'objective' => 'Reconocer las emociones basicas, su funcion en la vida cotidiana y su relacion con la identidad, la autoestima y el desarrollo personal durante la adolescencia.',
                    'points' => [
                        $this->point('1.1', 'Concepto de emocion y diferencia entre emocion, sentimiento y estado de animo.', 'conceptual'),
                        $this->point('1.2', 'Emociones basicas: alegria, tristeza, enojo, miedo, sorpresa y asco.', 'conceptual'),
                        $this->point('1.3', 'Comunicacion de la emocion: lenguaje verbal, corporal y expresion facial.', 'conceptual'),
                        $this->point('1.4', 'Adolescencia, identidad personal y cambios fisicos, cognitivos y psicosociales.', 'conceptual'),
                        $this->point('1.5', 'Registro personal de emociones, situaciones detonantes y respuestas habituales.', 'procedimental'),
                        $this->point('1.6', 'Autoevaluacion de fortalezas, areas de oportunidad y necesidades personales.', 'procedimental'),
                        $this->point('1.7', 'Respeto a la diversidad de formas de sentir y expresar emociones.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '2',
                    'title' => 'Autorregulacion, autonomia y manejo del estres',
                    'objective' => 'Aplicar estrategias de autorregulacion emocional para manejar impulsos, frustracion, presion academica y situaciones de estres propias de la vida escolar.',
                    'points' => [
                        $this->point('2.1', 'Inteligencia emocional y competencia emocional.', 'conceptual'),
                        $this->point('2.2', 'Autoconciencia emocional y reconocimiento de emociones y sus efectos.', 'conceptual'),
                        $this->point('2.3', 'Autorregulacion: control de impulsos, tolerancia a la frustracion y manejo del enojo.', 'conceptual'),
                        $this->point('2.4', 'Estres escolar: causas, sintomas y consecuencias en el rendimiento academico.', 'conceptual'),
                        $this->point('2.5', 'Tecnicas de pausa, respiracion, reorganizacion de pensamiento y solucion de problemas.', 'procedimental'),
                        $this->point('2.6', 'Plan personal para organizar tiempo, tareas, descanso y uso responsable de tecnologia.', 'procedimental'),
                        $this->point('2.7', 'Responsabilidad personal ante decisiones, emociones y consecuencias.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '3',
                    'title' => 'Empatia, comunicacion y convivencia escolar',
                    'objective' => 'Fortalecer habilidades interpersonales para comunicarse con asertividad, escuchar activamente, resolver conflictos y construir relaciones respetuosas en el entorno escolar.',
                    'points' => [
                        $this->point('3.1', 'Inteligencia intrapersonal e interpersonal.', 'conceptual'),
                        $this->point('3.2', 'Empatia y competencia social como elementos de la inteligencia emocional.', 'conceptual'),
                        $this->point('3.3', 'Habilidades sociales: escucha activa, respeto, colaboracion y comunicacion asertiva.', 'conceptual'),
                        $this->point('3.4', 'Conflicto, presion de grupo, limites personales y toma de perspectiva.', 'conceptual'),
                        $this->point('3.5', 'Practica de mensajes asertivos: expresar necesidades, desacuerdos y limites.', 'procedimental'),
                        $this->point('3.6', 'Analisis de casos de convivencia escolar, redes sociales y relaciones entre pares.', 'procedimental'),
                        $this->point('3.7', 'Apertura al dialogo, respeto a la dignidad de los demas y rechazo a la violencia.', 'actitudinal'),
                    ],
                ],
                [
                    'number' => '4',
                    'title' => 'Motivacion, toma de decisiones y proyecto personal',
                    'objective' => 'Integrar herramientas de inteligencia emocional para fortalecer la motivacion, la toma de decisiones, el sentido de logro y la construccion de un proyecto personal saludable.',
                    'points' => [
                        $this->point('4.1', 'Motivacion intrinseca y extrinseca en la vida academica.', 'conceptual'),
                        $this->point('4.2', 'Metas personales, habitos y rendimiento escolar.', 'conceptual'),
                        $this->point('4.3', 'Toma de decisiones: alternativas, consecuencias, valores y autocuidado.', 'conceptual'),
                        $this->point('4.4', 'Resiliencia, aprendizaje ante el error y adaptacion al cambio.', 'conceptual'),
                        $this->point('4.5', 'Diseno de metas SMART para mejorar bienestar, convivencia y desempeno academico.', 'procedimental'),
                        $this->point('4.6', 'Elaboracion de un plan personal de inteligencia emocional para el cierre del curso.', 'procedimental'),
                        $this->point('4.7', 'Compromiso con el desarrollo personal, la mejora continua y el bienestar colectivo.', 'actitudinal'),
                    ],
                ],
            ],
        ];
    }
}
