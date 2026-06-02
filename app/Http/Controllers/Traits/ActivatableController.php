<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

trait ActivatableController
{
    protected function getActiveColumn(): string
    {
        return $this->activeColumn ?? 'active';
    }

    protected function authorizeActivation($model, string $action): void
    {
    }

    public function activate(...$args)
    {
        $model = $this->resolveActivatableModel($args);
        $this->authorizeActivation($model, 'activate');
       
        try {
            $column = $this->getActiveColumn();
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
    public function deactivate(...$args)
    {
        $model = $this->resolveActivatableModel($args);
        $this->authorizeActivation($model, 'deactivate');
        try {
            $column = $this->getActiveColumn();
            if (!$model->{$column}) {
                return back()->with('info', class_basename($model) . ' ya está inactivo.');
            }
            $model->update([$column => false]);
            return back()->with('success', class_basename($model) . ' desactivado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al desactivar: ' . $e->getMessage());
        }
    }

    protected function activatableModelClass(): ?string
    {
        $controllerClass = class_basename(static::class);
        $modelName = preg_replace('/Controller$/', '', $controllerClass) ?: '';
        $modelClass = 'App\\Models\\' . $modelName;

        if ($modelName !== '' && class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
            return $modelClass;
        }

        return null;
    }

    protected function resolveActivatableModel(array $args): Model
    {
        foreach ($args as $arg) {
            if ($arg instanceof Model) {
                return $arg;
            }
        }

        $route = Request::route();
        $params = $route ? array_values($route->parameters()) : [];

        foreach ($params as $param) {
            if ($param instanceof Model) {
                return $param;
            }
        }

        $idCandidate = null;
        foreach (array_merge($args, $params) as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $idCandidate = $value;
                break;
            }
        }

        $modelClass = $this->activatableModelClass();
        if (! $modelClass || $idCandidate === null) {
            throw new \InvalidArgumentException('No se pudo resolver el modelo para activar/desactivar.');
        }

        return $modelClass::query()->findOrFail($idCandidate);
    }
}
