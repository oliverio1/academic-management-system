<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ListSchoolTenants extends Command
{
    protected $signature = 'school:list';

    protected $description = 'Lista escuelas (tenants) y sus dominios.';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->with('domains')
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay escuelas registradas.');
            return self::SUCCESS;
        }

        $rows = $tenants->map(function (Tenant $tenant) {
            return [
                'tenant' => (string) $tenant->id,
                'domains' => $tenant->domains->pluck('domain')->sort()->implode(', '),
                'created_at' => optional($tenant->created_at)->toDateTimeString(),
            ];
        })->all();

        $this->table(['Tenant', 'Domains', 'Created At'], $rows);

        return self::SUCCESS;
    }
}

