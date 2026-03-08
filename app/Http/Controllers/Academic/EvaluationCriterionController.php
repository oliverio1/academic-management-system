<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\EvaluationCriterion;
use Illuminate\Support\Facades\DB;

class EvaluationCriterionController extends Controller
{
    public function index(TeachingAssignment $assignment) {
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id,403);
        $criteria = $assignment->evaluationCriteria()->orderBy('id')->get();
        $total = $criteria->sum('percentage');
        return view('teacher.evaluation_criteria.index',compact('assignment', 'criteria', 'total'));
    }

    public function create(TeachingAssignment $teachingAssignment) {
        // 🔒 Seguridad: solo el profesor dueño
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        // 🔒 Regla: no permitir si ya hay calificaciones
        abort_if(
            $teachingAssignment->hasGrades(),
            403,
            'No puedes configurar la evaluación porque ya existen calificaciones.'
        );

        // 🔁 Si ya existen criterios, manda a editar
        if ($teachingAssignment->evaluationCriteria()->exists()) {
            return redirect()
                ->route('teacher.evaluation.edit', $teachingAssignment)
                ->with('info', 'La evaluación ya está configurada. Puedes editarla.');
        }

        // 📦 Cargar relaciones útiles (opcional pero recomendado)
        $teachingAssignment->load(['subject', 'group']);

        return view(
            'teacher.evaluation.create',
            compact('teachingAssignment')
        );
    }

    public function store(Request $request, TeachingAssignment $teachingAssignment) {

        // 🔒 Seguridad: solo el profesor dueño
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        // 🔒 Regla: no permitir si ya hay calificaciones
        abort_if(
            $teachingAssignment->hasGrades(),
            403,
            'No puedes configurar la evaluación porque ya existen calificaciones.'
        );
    
        // 🔒 Regla: no permitir duplicar criterios
        abort_if(
            $teachingAssignment->evaluationCriteria()->exists(),
            403,
            'Esta asignación ya tiene criterios definidos.'
        );
    
        // 📥 Validación base
        $data = $request->validate([
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.percentage' => 'required|numeric|min:0',
        ]);
    
        // 🧮 Validar suma = 100
        $total = collect($data['criteria'])->sum('percentage');
    
        if (round($total, 2) !== 100.00) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'La suma de los porcentajes debe ser exactamente 100%.'
                ]);
        }
    
        // 💾 Guardado atómico
        DB::transaction(function () use ($data, $teachingAssignment) {
            foreach ($data['criteria'] as $criterion) {
                if ($key === 'attendance') {
                    $teachingAssignment->evaluationCriteria()->updateOrCreate(
                        ['name' => 'Asistencia'],
                        ['percentage' => $criterion['percentage']]
                    );
                    continue;
                }
                $teachingAssignment->evaluationCriteria()->create([
                    'name' => $criterion['name'],
                    'percentage' => $criterion['percentage'],
                ]);
            }
        });
    
        return redirect()
            ->route('assignments.show', [
                $teachingAssignment,
                'tab' => 'evaluation',
            ])
            ->with('success', 'Evaluación configurada correctamente.');
    }

    public function edit(TeachingAssignment $teachingAssignment) {
        // 🔒 Seguridad
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );

        // 🔒 No tiene sentido editar si no hay criterios
        abort_if(
            !$teachingAssignment->evaluationCriteria()->exists(),
            404,
            'No hay criterios para editar.'
        );

        $teachingAssignment->load([
            'evaluationCriteria.activities'
        ]);

        return view(
            'teacher.evaluation.edit',
            compact('teachingAssignment')
        );
    }

    public function update(
        Request $request,
        TeachingAssignment $assignment
    ) {
        // 🔒 Seguridad: solo el profesor dueño
        abort_if(
            $assignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
    
        // 📥 Validación base
        $data = $request->validate([
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.percentage' => 'required|numeric|min:0',
        ]);
    
        /**
         * 🧠 Obtener criterio Asistencia REAL del assignment
         */
        $attendanceCriterion = $assignment
            ->evaluationCriteria()
            ->where('name', 'Asistencia')
            ->first();
    
        abort_if(
            !$attendanceCriterion,
            500,
            'No existe el criterio obligatorio Asistencia.'
        );
    
        /**
         * 🧮 Validar suma = 100
         */
        $total = collect($data['criteria'])->sum('percentage');
    
        if (round($total, 2) !== 100.00) {
            return back()
                ->withInput()
                ->withErrors([
                    'criteria' => 'La suma de los porcentajes debe ser exactamente 100%.'
                ]);
        }
    
        DB::transaction(function () use (
            $data,
            $assignment,
            $attendanceCriterion
        ) {
    
            /**
             * 🔁 Normalizar IDs recibidos
             * attendance → ID real
             */
            $incomingIds = collect($data['criteria'])
                ->keys()
                ->map(function ($key) use ($attendanceCriterion) {
                    return $key === 'attendance'
                        ? $attendanceCriterion->id
                        : (int) $key;
                });
    
            /**
             * 📦 Criterios actuales
             */
            $existing = $assignment->evaluationCriteria()->get();
    
            foreach ($existing as $criterion) {
    
                /**
                 * ❌ Eliminación
                 */
                if (!$incomingIds->contains($criterion->id)) {
    
                    if ($criterion->name === 'Asistencia') {
                        abort(403, 'El rubro Asistencia no puede eliminarse.');
                    }
    
                    if ($criterion->activities()->exists()) {
                        abort(403, 'No puedes eliminar un criterio con actividades asociadas.');
                    }
    
                    $criterion->delete();
                    continue;
                }
    
                /**
                 * ✏️ Actualización
                 */
                if ($criterion->name === 'Asistencia') {
    
                    $criterion->update([
                        'percentage' =>
                            $data['criteria']['attendance']['percentage'],
                    ]);
    
                    continue;
                }
    
                $criterion->update([
                    'name' =>
                        $data['criteria'][$criterion->id]['name'],
                    'percentage' =>
                        $data['criteria'][$criterion->id]['percentage'],
                ]);
            }
    
            /**
             * ➕ Nuevos criterios (claves negativas o nuevas)
             */
            foreach ($data['criteria'] as $key => $criterion) {
    
                if ($key === 'attendance') {
                    continue;
                }
    
                if (!is_numeric($key) || (int) $key <= 0) {
    
                    $assignment->evaluationCriteria()->create([
                        'name' => $criterion['name'],
                        'percentage' => $criterion['percentage'],
                    ]);
                }
            }
        });
    
        return redirect()
            ->route('assignments.show', [
                $assignment,
                'tab' => 'evaluation',
            ])
            ->with('success', 'Criterios de evaluación actualizados correctamente.');
    }

    public function destroy(EvaluationCriterion $criterion) {
        $assignment = $criterion->teachingAssignment;
        abort_if($assignment->teacher_id !== auth()->user()->teacher->id,403);
        $criterion->delete();
        return back()->with('success', 'Criterio eliminado');
    }
}
