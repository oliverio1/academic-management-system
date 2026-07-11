<?php

namespace App\Http\Controllers;

use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private const ITEM_TYPES = [
        'material' => 'Material',
        'equipo' => 'Equipo',
        'reactivo' => 'Reactivo',
        'ludoteca' => 'Ludoteca',
        'otro' => 'Otro',
    ];

    private const AREA_TYPES = [
        'laboratorio' => 'Laboratorio',
        'ludoteca' => 'Ludoteca',
        'almacen' => 'Almacen',
        'aula' => 'Aula',
        'otro' => 'Otro',
    ];

    public function index(Request $request)
    {
        $this->ensureDefaultCategories();

        $items = InventoryItem::query()
            ->with(['category', 'location'])
            ->when($request->filled('type'), fn ($query) => $query->where('item_type', $request->type))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('quantity', '<=', 'minimum_quantity')->where('minimum_quantity', '>', 0))
            ->orderBy('name')
            ->get();

        $summary = [
            'items' => InventoryItem::count(),
            'low_stock' => InventoryItem::query()->whereColumn('quantity', '<=', 'minimum_quantity')->where('minimum_quantity', '>', 0)->count(),
            'reactives' => InventoryItem::query()->where('item_type', 'reactivo')->count(),
            'equipment' => InventoryItem::query()->where('item_type', 'equipo')->count(),
        ];

        return view('coordination.inventory.index', [
            'items' => $items,
            'summary' => $summary,
            'itemTypes' => self::ITEM_TYPES,
        ]);
    }

    public function create()
    {
        $this->ensureDefaultCategories();

        return view('coordination.inventory.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateItem($request);
        $initialQuantity = (float) ($data['quantity'] ?? 0);

        DB::transaction(function () use ($data, $initialQuantity) {
            $item = InventoryItem::create($data);

            if ($initialQuantity > 0) {
                $item->movements()->create([
                    'user_id' => auth()->id(),
                    'type' => 'entrada',
                    'quantity' => $initialQuantity,
                    'quantity_before' => 0,
                    'quantity_after' => $initialQuantity,
                    'reason' => 'Carga inicial',
                    'moved_at' => now(),
                ]);
            }
        });

        return redirect()->route('coordination.inventory.index')->with('success', 'Articulo de inventario creado.');
    }

    public function edit(InventoryItem $item)
    {
        return view('coordination.inventory.edit', $this->formData(['item' => $item]));
    }

    public function update(Request $request, InventoryItem $item)
    {
        $data = $this->validateItem($request, $item);
        unset($data['quantity']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $item->update($data);

        return redirect()->route('coordination.inventory.index')->with('success', 'Articulo de inventario actualizado.');
    }

    public function show(InventoryItem $item)
    {
        $item->load(['category', 'location', 'movements' => fn ($query) => $query->with('user')->latest('moved_at')->latest()]);

        return view('coordination.inventory.show', [
            'item' => $item,
        ]);
    }

    public function storeMovement(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['entrada', 'salida', 'ajuste'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->refresh();
            $before = (float) $item->quantity;
            $quantity = (float) $data['quantity'];
            $after = match ($data['type']) {
                'entrada' => $before + $quantity,
                'salida' => $before - $quantity,
                'ajuste' => $quantity,
            };

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La salida no puede dejar existencias negativas.',
                ]);
            }

            $item->update(['quantity' => $after]);
            $item->movements()->create([
                'user_id' => auth()->id(),
                'type' => $data['type'],
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'moved_at' => now(),
            ]);
        });

        return redirect()->route('coordination.inventory.show', $item)->with('success', 'Movimiento registrado.');
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'area_type' => ['required', Rule::in(array_keys(self::AREA_TYPES))],
            'responsible' => ['nullable', 'string', 'max:255'],
        ]);
        $data['campus_id'] = (int) session('active_campus_id', 0) ?: null;
        $data['is_active'] = true;

        InventoryLocation::create($data);

        return back()->with('success', 'Ubicacion creada.');
    }

    private function validateItem(Request $request, ?InventoryItem $item = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['required', Rule::in(array_keys(self::ITEM_TYPES))],
            'unit' => ['required', 'string', 'max:50'],
            'quantity' => [$item ? 'nullable' : 'required', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['required', Rule::in(['nuevo', 'bueno', 'regular', 'mantenimiento', 'danado'])],
            'status' => ['required', Rule::in(['disponible', 'prestado', 'mantenimiento', 'agotado', 'baja'])],
            'expiration_date' => ['nullable', 'date'],
            'storage_notes' => ['nullable', 'string'],
            'hazard_notes' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function formData(array $extra = []): array
    {
        return array_merge([
            'categories' => InventoryCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'itemTypes' => self::ITEM_TYPES,
            'areaTypes' => self::AREA_TYPES,
        ], $extra);
    }

    private function ensureDefaultCategories(): void
    {
        if (InventoryCategory::query()->exists()) {
            return;
        }

        foreach (self::ITEM_TYPES as $type => $label) {
            if ($type === 'otro') {
                continue;
            }

            InventoryCategory::create([
                'name' => $label,
                'type' => $type,
                'is_active' => true,
            ]);
        }
    }
}
