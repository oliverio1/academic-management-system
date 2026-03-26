<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait ActivatableController
 * 
 * Provides reusable activate/deactivate methods for controllers
 * managing Eloquent models with status columns.
 * 
 * Usage in controller:
 *     use ActivatableController;
 *     protected $activeColumn = 'is_active'; // or 'active'
 * 
 * Optional: Override authorizeActivation($model, $action) for custom authorization
 */
trait ActivatableController
{
    /**
     * Get the column name for active status.
     * Override in controller if different from 'active'
     */
    protected function getActiveColumn(): string
    {
        return $this->activeColumn ?? 'active';
    }

    /**
     * Check authorization before activation/deactivation
     * Override in controller to add custom authorization logic
     */
    protected function authorizeActivation(Model $model, string $action): void
    {
        // Override this method in controller if using policies
        // Example:
        //   $this->authorize($action, $model);
    }

    /**
     * Activate a model
     * 
     * @param Model $model The model instance to activate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate(Model $model)
    {
        $this->authorizeActivation($model, 'activate');
        
        try {
            $column = $this->getActiveColumn();
            
            // Check if already active
            if ($model->{$column}) {
                return back()->with('info', class_basename($model) . ' ya está activo.');
            }
            
            $model->update([$column => true]);
            return back()->with('success', class_basename($model) . ' activado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al activar: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate a model
     * 
     * @param Model $model The model instance to deactivate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivate(Model $model)
    {
        $this->authorizeActivation($model, 'deactivate');
        
        try {
            $column = $this->getActiveColumn();
            
            // Check if already inactive
            if (!$model->{$column}) {
                return back()->with('info', class_basename($model) . ' ya está inactivo.');
            }
            
            $model->update([$column => false]);
            return back()->with('success', class_basename($model) . ' desactivado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al desactivar: ' . $e->getMessage());
        }
    }
}
