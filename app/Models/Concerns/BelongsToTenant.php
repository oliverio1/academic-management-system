<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::resolveTenantId();

            if ($tenantId !== null) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return;
            }

            $tenantId = static::resolveTenantId();
            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    protected static function resolveTenantId(): ?string
    {
        $tenantId = (string) tenant('id');

        return $tenantId !== '' ? $tenantId : null;
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where($query->qualifyColumn('tenant_id'), $tenantId);
    }
}

