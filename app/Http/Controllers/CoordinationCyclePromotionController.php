<?php

namespace App\Http\Controllers;

use App\Models\SchoolCycle;
use App\Services\CyclePromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordinationCyclePromotionController extends Controller
{
    public function index(): View
    {
        return view('coordination.cycle_promotions.index', [
            'cycles' => $this->cycles(),
            'sourceCycleId' => null,
            'targetCycleId' => null,
            'selectedModalityId' => null,
            'previewRows' => collect(),
            'summary' => null,
        ]);
    }

    public function preview(Request $request, CyclePromotionService $service): View
    {
        $data = $request->validate([
            'source_cycle_id' => 'required|exists:school_cycles,id',
            'target_cycle_id' => 'required|exists:school_cycles,id',
            'modality_id' => 'required|exists:modalities,id',
        ]);

        $sourceCycle = SchoolCycle::with('modalities')->findOrFail($data['source_cycle_id']);
        $targetCycle = SchoolCycle::with('modalities')->findOrFail($data['target_cycle_id']);

        try {
            $preview = $service->preview($sourceCycle, $targetCycle, (int) $data['modality_id']);
        } catch (\InvalidArgumentException $e) {
            return view('coordination.cycle_promotions.index', [
                'cycles' => $this->cycles(),
                'sourceCycleId' => (int) $data['source_cycle_id'],
                'targetCycleId' => (int) $data['target_cycle_id'],
                'selectedModalityId' => (int) $data['modality_id'],
                'previewRows' => collect(),
                'summary' => null,
                'errorMessage' => $e->getMessage(),
            ]);
        }

        return view('coordination.cycle_promotions.index', [
            'cycles' => $this->cycles(),
            'sourceCycleId' => (int) $data['source_cycle_id'],
            'targetCycleId' => (int) $data['target_cycle_id'],
            'selectedModalityId' => (int) $data['modality_id'],
            'previewRows' => $preview['rows'],
            'summary' => $preview['summary'],
        ]);
    }

    public function execute(Request $request, CyclePromotionService $service): RedirectResponse
    {
        $data = $request->validate([
            'source_cycle_id' => 'required|exists:school_cycles,id',
            'target_cycle_id' => 'required|exists:school_cycles,id',
            'modality_id' => 'required|exists:modalities,id',
            'actions' => 'nullable|array',
            'actions.*' => 'nullable|in:promote,repeat,graduate,skip',
            'promote_groups' => 'nullable|array',
            'promote_groups.*' => 'nullable|integer|exists:groups,id',
            'repeat_groups' => 'nullable|array',
            'repeat_groups.*' => 'nullable|integer|exists:groups,id',
        ]);

        $sourceCycle = SchoolCycle::with('modalities')->findOrFail($data['source_cycle_id']);
        $targetCycle = SchoolCycle::with('modalities')->findOrFail($data['target_cycle_id']);

        try {
            $result = $service->execute(
                $sourceCycle,
                $targetCycle,
                (int) $data['modality_id'],
                $data['actions'] ?? [],
                $data['promote_groups'] ?? [],
                $data['repeat_groups'] ?? []
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('coordination.cycle-promotions.index')
                ->with('error', $e->getMessage());
        }

        $message = 'Promocion completada. Promovidos: ' . $result['promoted']
            . ', repetidores: ' . ($result['repeated'] ?? 0)
            . ', egresados: ' . ($result['graduated'] ?? 0)
            . ', omitidos: ' . $result['skipped']
            . ', listos en vista previa: ' . ($result['summary']['ready'] ?? 0) . '.';

        if (! empty($result['errors'])) {
            $message .= ' Errores: ' . implode(' | ', $result['errors']);
        }

        return redirect()
            ->route('coordination.cycle-promotions.index')
            ->with('info', $message);
    }

    protected function cycles()
    {
        return SchoolCycle::query()
            ->with(['modality', 'modalities'])
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $this->tenantId()))
            ->orderByDesc('start_date')
            ->get();
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado.');

        return $tenantId;
    }
}
