<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class OnboardSchoolTenant extends Command
{
    protected $signature = 'school:onboard
        {tenant : ID del tenant (escuela), ej: colegio-del-valle}
        {--domain=* : Uno o varios dominios para la escuela}
        {--dry-run : Muestra cambios sin persistir}
        {--force : Reasigna dominios ya ocupados por otro tenant}';

    protected $description = 'Da de alta una escuela (tenant) y sus dominios.';

    public function handle(): int
    {
        $tenantId = $this->normalizeTenantId((string) $this->argument('tenant'));
        $domains = $this->normalizeDomains((array) $this->option('domain'), $tenantId);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($tenantId === '') {
            $this->error('El tenant es obligatorio y no puede quedar vacio.');
            return self::FAILURE;
        }

        if (empty($domains)) {
            $this->error('Debes indicar al menos un dominio (o se usara el tenant como dominio).');
            return self::FAILURE;
        }

        if (! $this->validateTenantId($tenantId)) {
            $this->error('Tenant invalido. Usa solo letras, numeros y guion medio.');
            return self::FAILURE;
        }

        foreach ($domains as $domain) {
            if (! $this->validateDomain($domain)) {
                $this->error("Dominio invalido: {$domain}");
                return self::FAILURE;
            }
        }

        try {
            DB::beginTransaction();

            $result = (function () use ($tenantId, $domains, $force) {
                $tenant = Tenant::query()->firstOrCreate(['id' => $tenantId]);
                $tenantWasCreated = $tenant->wasRecentlyCreated;

                $createdDomains = [];
                $updatedDomains = [];

                foreach ($domains as $domainName) {
                    $domain = Domain::query()->where('domain', $domainName)->first();

                    if (! $domain) {
                        Domain::query()->create([
                            'domain' => $domainName,
                            'tenant_id' => $tenant->id,
                        ]);
                        $createdDomains[] = $domainName;
                        continue;
                    }

                    if ((string) $domain->tenant_id === (string) $tenant->id) {
                        continue;
                    }

                    if (! $force) {
                        throw new \RuntimeException(
                            "El dominio {$domainName} ya pertenece a otro tenant ({$domain->tenant_id}). Usa --force para reasignarlo."
                        );
                    }

                    $domain->tenant_id = $tenant->id;
                    $domain->save();
                    $updatedDomains[] = $domainName;
                }

                return [
                    'tenant' => $tenantId,
                    'tenant_created' => $tenantWasCreated,
                    'created_domains' => $createdDomains,
                    'updated_domains' => $updatedDomains,
                ];
            })();

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info($dryRun ? 'Simulacion completada (dry-run).' : 'Escuela registrada correctamente.');
        $this->line('Tenant: ' . $result['tenant']);
        $this->line('Tenant creado: ' . ($result['tenant_created'] ? 'si' : 'no'));
        $this->line('Dominios creados: ' . (empty($result['created_domains']) ? 'ninguno' : implode(', ', $result['created_domains'])));
        $this->line('Dominios reasignados: ' . (empty($result['updated_domains']) ? 'ninguno' : implode(', ', $result['updated_domains'])));

        return self::SUCCESS;
    }

    private function normalizeTenantId(string $tenantId): string
    {
        $tenantId = mb_strtolower(trim($tenantId));
        return preg_replace('/\s+/', '-', $tenantId) ?? '';
    }

    private function normalizeDomains(array $domains, string $fallbackTenantId): array
    {
        $normalized = collect($domains)
            ->map(fn ($domain) => mb_strtolower(trim((string) $domain)))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return [$fallbackTenantId];
        }

        return $normalized->unique()->values()->all();
    }

    private function validateTenantId(string $tenantId): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tenantId);
    }

    private function validateDomain(string $domain): bool
    {
        return (bool) preg_match('/^[a-z0-9.-]+$/', $domain);
    }
}
