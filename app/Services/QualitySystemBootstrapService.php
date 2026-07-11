<?php

namespace App\Services;

use App\Models\QualityDocument;
use App\Models\QualityDocumentEvent;
use App\Models\QualityDocumentVersion;
use App\Models\QualityProcess;
use Illuminate\Support\Facades\DB;

class QualitySystemBootstrapService
{
    public function bootstrap(?int $userId = null): array
    {
        return DB::transaction(function () use ($userId) {
            $createdProcesses = 0;
            $updatedProcesses = 0;
            $createdDocuments = 0;
            $updatedDocuments = 0;

            $processIds = [];

            foreach ($this->processBlueprint() as $processData) {
                $process = QualityProcess::query()->where('code', $processData['code'])->first();

                if ($process) {
                    $process->update($processData);
                    $updatedProcesses++;
                } else {
                    $process = QualityProcess::create($processData);
                    $createdProcesses++;
                }

                $processIds[$process->code] = $process->id;
            }

            foreach ($this->documentBlueprint() as $documentData) {
                $processCode = $documentData['process_code'];
                unset($documentData['process_code']);

                $documentData['quality_process_id'] = $processIds[$processCode] ?? null;

                if (! $documentData['quality_process_id']) {
                    continue;
                }

                $document = QualityDocument::query()->where('code', $documentData['code'])->first();

                if ($document) {
                    $document->update($documentData);
                    $updatedDocuments++;
                    continue;
                }

                $document = QualityDocument::create($documentData + [
                    'version' => '0.1',
                    'status' => 'draft',
                    'approval_status' => 'draft',
                    'is_active' => true,
                ]);

                $version = QualityDocumentVersion::create([
                    'quality_document_id' => $document->id,
                    'version' => $document->version ?: '0.1',
                    'content' => $document->content,
                    'change_summary' => 'Estructura base ISO 9001 / ISO 21001',
                    'approval_status' => 'draft',
                ]);

                $document->update(['current_version_id' => $version->id]);

                QualityDocumentEvent::create([
                    'quality_document_id' => $document->id,
                    'quality_document_version_id' => $version->id,
                    'user_id' => $userId,
                    'event_type' => 'iso_bootstrap',
                    'notes' => 'Documento creado desde la estructura base del SGC.',
                ]);

                $createdDocuments++;
            }

            return [
                'created_processes' => $createdProcesses,
                'updated_processes' => $updatedProcesses,
                'created_documents' => $createdDocuments,
                'updated_documents' => $updatedDocuments,
            ];
        });
    }

    private function processBlueprint(): array
    {
        return [
            [
                'code' => 'SGC-DIR',
                'name' => 'Dirección, liderazgo y planeación institucional',
                'description' => 'Define contexto, partes interesadas, política, objetivos, riesgos, responsabilidades y revisión por la dirección.',
                'process_type' => 'strategic',
                'iso_9001_clauses' => '4, 5, 6, 9.3',
                'iso_21001_clauses' => '4, 5, 6, 9.3',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-ACA',
                'name' => 'Gestión académica y prestación del servicio educativo',
                'description' => 'Controla planeación académica, operación del servicio educativo, evaluación del aprendizaje y seguimiento académico.',
                'process_type' => 'core',
                'iso_9001_clauses' => '8.1, 8.2, 8.5, 8.6, 8.7, 9.1',
                'iso_21001_clauses' => '8.1, 8.2, 8.3, 8.5, 8.6, 9.1',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-EST',
                'name' => 'Atención, comunicación y bienestar del estudiante',
                'description' => 'Gestiona comunicación, acompañamiento, satisfacción, quejas, inclusión y apoyo al estudiante.',
                'process_type' => 'core',
                'iso_9001_clauses' => '8.2, 9.1.2, 10.2',
                'iso_21001_clauses' => '7.3, 8.5, 8.6, 8.7, 9.1.2, 10.2',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-RH',
                'name' => 'Competencia y desarrollo del personal',
                'description' => 'Asegura perfiles, competencias, inducción, capacitación, evaluación y toma de conciencia del personal.',
                'process_type' => 'support',
                'iso_9001_clauses' => '7.1.2, 7.2, 7.3',
                'iso_21001_clauses' => '7.1.2, 7.2, 7.3',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-INF',
                'name' => 'Infraestructura, ambiente y recursos',
                'description' => 'Gestiona instalaciones, recursos tecnológicos, materiales, ambiente de aprendizaje y servicios de apoyo.',
                'process_type' => 'support',
                'iso_9001_clauses' => '7.1.3, 7.1.4, 7.1.5',
                'iso_21001_clauses' => '7.1.3, 7.1.4, 7.1.5, 8.5',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-DOC',
                'name' => 'Control de información documentada',
                'description' => 'Controla creación, actualización, aprobación, distribución, consulta, resguardo y obsolescencia documental.',
                'process_type' => 'support',
                'iso_9001_clauses' => '7.5',
                'iso_21001_clauses' => '7.5',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'code' => 'SGC-MEJ',
                'name' => 'Evaluación, auditoría y mejora',
                'description' => 'Gestiona indicadores, satisfacción, auditoría interna, no conformidades, acciones correctivas y mejora continua.',
                'process_type' => 'evaluation',
                'iso_9001_clauses' => '9.1, 9.2, 9.3, 10.2, 10.3',
                'iso_21001_clauses' => '9.1, 9.2, 9.3, 10.2, 10.3',
                'sort_order' => 70,
                'is_active' => true,
            ],
        ];
    }

    private function documentBlueprint(): array
    {
        return [
            $this->document('SGC-MAN-01', 'Manual del Sistema de Gestión de Calidad Educativa', 'manual', 'SGC-DIR', '4, 5, 6, 7.5', '4, 5, 6, 7.5', 'Documento rector que describe alcance, contexto, mapa de procesos, responsabilidades, interacción entre procesos y criterios generales del SGC.'),
            $this->document('SGC-POL-01', 'Política de calidad educativa', 'policy', 'SGC-DIR', '5.2', '5.2', 'Declaración institucional de compromiso con la satisfacción de estudiantes y partes interesadas, cumplimiento aplicable y mejora continua.'),
            $this->document('SGC-MAT-01', 'Matriz de contexto, partes interesadas, riesgos y oportunidades', 'matrix', 'SGC-DIR', '4.1, 4.2, 6.1', '4.1, 4.2, 6.1', 'Matriz para identificar necesidades, expectativas, riesgos, oportunidades y acciones de atención por proceso.'),
            $this->document('SGC-OBJ-01', 'Objetivos e indicadores del SGC', 'plan', 'SGC-DIR', '6.2, 9.1', '6.2, 9.1', 'Plan de objetivos medibles, indicadores, metas, responsables, frecuencia de medición y evidencia de seguimiento.'),
            $this->document('SGC-PRO-01', 'Procedimiento de gestión académica y prestación del servicio educativo', 'procedure', 'SGC-ACA', '8.1, 8.2, 8.5, 8.6, 8.7', '8.1, 8.2, 8.3, 8.5, 8.6', 'Define planeación académica, impartición, seguimiento de asistencia, evaluación, retroalimentación y control de cambios académicos.'),
            $this->document('SGC-PRO-02', 'Procedimiento de atención y comunicación con estudiantes y familias', 'procedure', 'SGC-EST', '8.2, 9.1.2, 10.2', '7.3, 8.5, 8.6, 9.1.2, 10.2', 'Define canales de comunicación, atención de solicitudes, quejas, seguimiento y medición de satisfacción.'),
            $this->document('SGC-PRO-03', 'Procedimiento de competencia, capacitación y evaluación del personal', 'procedure', 'SGC-RH', '7.2, 7.3', '7.2, 7.3', 'Define perfiles, inducción, detección de necesidades, capacitación, evaluación de competencia y evidencia documental.'),
            $this->document('SGC-PRO-04', 'Procedimiento de control de información documentada', 'procedure', 'SGC-DOC', '7.5', '7.5', 'Define elaboración, revisión, aprobación, publicación, consulta, cambios, versiones, resguardo y retiro de documentos obsoletos.'),
            $this->document('SGC-PRO-05', 'Procedimiento de auditoría interna y mejora continua', 'procedure', 'SGC-MEJ', '9.2, 10.2, 10.3', '9.2, 10.2, 10.3', 'Define programa de auditoría, hallazgos, no conformidades, acciones correctivas, seguimiento y cierre.'),
            $this->document('SGC-FOR-01', 'Formato de no conformidad y acción correctiva', 'format', 'SGC-MEJ', '10.2', '10.2', 'Formato para registrar origen, descripción, análisis de causa, acción correctiva, responsable, fecha compromiso y verificación de eficacia.'),
            $this->document('SGC-FOR-02', 'Formato de encuesta de satisfacción de estudiantes y familias', 'format', 'SGC-EST', '9.1.2', '9.1.2', 'Formato base para medir percepción del servicio educativo, comunicación, acompañamiento, instalaciones y mejora esperada.'),
            $this->document('SGC-REG-01', 'Registro de revisión por la dirección', 'record', 'SGC-DIR', '9.3', '9.3', 'Registro de entradas, acuerdos, decisiones, necesidades de cambio, recursos y acciones derivadas de la revisión por dirección.'),
        ];
    }

    private function document(string $code, string $title, string $type, string $processCode, string $iso9001, string $iso21001, string $purpose): array
    {
        return [
            'process_code' => $processCode,
            'code' => $code,
            'title' => $title,
            'document_type' => $type,
            'iso_9001_clauses' => $iso9001,
            'iso_21001_clauses' => $iso21001,
            'owner' => 'Coordinación / Dirección',
            'content' => $this->baseContent($purpose),
        ];
    }

    private function baseContent(string $purpose): string
    {
        return implode("\n\n", [
            '1. Propósito',
            $purpose,
            '2. Alcance',
            'Aplica al proceso definido y a las áreas involucradas en el Sistema de Gestión de Calidad Educativa.',
            '3. Responsables',
            'Definir responsable del proceso, participantes, autoridad de aprobación y responsables de conservar evidencias.',
            '4. Desarrollo',
            'Describir actividades, entradas, salidas, criterios de aceptación, controles, registros y frecuencia de seguimiento.',
            '5. Evidencias y registros',
            'Listar formatos, reportes, bitácoras, indicadores o documentos que demuestran la ejecución del proceso.',
            '6. Indicadores sugeridos',
            'Definir indicador, fórmula, meta, frecuencia, responsable y fuente de información.',
            '7. Riesgos y oportunidades',
            'Identificar riesgos relevantes, controles preventivos, oportunidades de mejora y seguimiento.',
        ]);
    }
}
