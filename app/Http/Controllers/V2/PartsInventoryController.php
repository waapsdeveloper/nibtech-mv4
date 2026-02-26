<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Admin_model;
use App\Models\Customer_model;
use App\Models\PartBatch;
use App\Models\PartBrokenRecord;
use App\Models\PartsPurchase;
use App\Models\PartsRepairAssignment;
use App\Models\Process_model;
use App\Models\Products_model;
use App\Models\RepairPart;
use App\Models\RepairPartUsage;
use App\Models\Stock_model;
use App\Models\Variation_model;
use App\Services\Repair\RepairPartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartsInventoryController extends Controller
{
    public function dashboard()
    {
        $data['title_page'] = 'Parts Inventory – Dashboard';
        session()->put('page_title', $data['title_page']);

        $partsCount = RepairPart::active()->count();
        $batchesCount = PartBatch::inStock()->count();
        $totalOnHand = RepairPart::active()->sum('on_hand');
        $lowStockCount = RepairPart::active()->whereColumn('on_hand', '<=', 'reorder_level')->count();
        $recentUsages = RepairPartUsage::with(['part', 'batch', 'stock'])->latest()->take(5)->get();

        return view('v2.parts-inventory.dashboard', compact('partsCount', 'batchesCount', 'totalOnHand', 'lowStockCount', 'recentUsages'))->with($data);
    }

    public function catalogIndex(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Part Catalog';
        session()->put('page_title', $data['title_page']);

        $query = RepairPart::withCount('batches');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        // Order by latest batch received first (part with most recent batch on top); parts with no batches last
        $query->orderByRaw('(SELECT MAX(COALESCE(received_at, created_at)) FROM part_batches WHERE part_batches.repair_part_id = repair_parts.id AND part_batches.deleted_at IS NULL) IS NULL ASC')
            ->orderByRaw('(SELECT MAX(COALESCE(received_at, created_at)) FROM part_batches WHERE part_batches.repair_part_id = repair_parts.id AND part_batches.deleted_at IS NULL) DESC')
            ->orderBy('name');

        $parts = $query->paginate(20)->withQueryString();

        return view('v2.parts-inventory.catalog.index', compact('parts'))->with($data);
    }

    /**
     * Delete a part (catalog line item) and all its parts-inventory related records:
     * repair part usages, broken records, batches, parts purchases, parts repair assignments, then the part.
     */
    public function catalogDestroy($id)
    {
        $part = RepairPart::findOrFail($id);

        DB::transaction(function () use ($part) {
            // Usages (reference repair_part_id and batch_id)
            RepairPartUsage::where('repair_part_id', $part->id)->forceDelete();
            // Broken records (reference repair_part_id and part_batch_id)
            PartBrokenRecord::where('repair_part_id', $part->id)->delete();
            // Batches (reference repair_part_id) – force delete to remove from DB
            PartBatch::where('repair_part_id', $part->id)->forceDelete();
            // Parts purchases (reference repair_part_id)
            PartsPurchase::where('repair_part_id', $part->id)->delete();
            // Parts repair assignments (reference repair_part_id)
            PartsRepairAssignment::where('repair_part_id', $part->id)->delete();
            // Part itself (soft-delete model – force delete)
            $part->forceDelete();
        });

        return redirect()->route('v2.parts-inventory.catalog')->with('success', 'Part and all associated records deleted.');
    }

    /**
     * Repair page (parts-inventory): show line item for stock identified by ?imei= (imei+serial concatenated).
     * Linked from internal repair actions as "Repair".
     */
    public function repair(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Repair';
        session()->put('page_title', $data['title_page']);

        $imeiParam = $request->query('imei', '');
        $stock = null;

        if ($imeiParam !== '') {
            $stock = Stock_model::with([
                'variation.product',
                'variation.storage_id',
                'variation.color_id',
                'variation.grade_id',
                'order.customer',
                'latest_operation',
            ])->whereRaw('CONCAT(COALESCE(imei,""), COALESCE(serial_number,"")) = ?', [$imeiParam])->first();
        }

        $data['stock'] = $stock;
        $data['imei_param'] = $imeiParam;
        $data['parts'] = RepairPart::orderBy('name')->get(['id', 'name', 'sku']);

        // Same as main Repair "Add Repair": next reference ID and repairers list (full list, no admin filter)
        $data['latest_reference'] = Process_model::where('process_type_id', 9)->orderBy('reference_id', 'DESC')->first()->reference_id ?? 5998;
        $data['next_reference'] = $data['latest_reference'] + 1;
        $data['repairers'] = Customer_model::whereNotNull('is_vendor')->orderBy('company')->pluck('company', 'id');

        return view('v2.parts-inventory.repair', $data);
    }

    /**
     * Submit repair from v2/parts-inventory/repair form: creates/updates PartsRepairAssignment
     * and redirects to items-to-repair.
     */
    public function repairSubmit(Request $request)
    {
        $request->validate([
            'imei' => 'required|string|max:255',
            'reference_id' => 'required|string|max:64',
            'repairer_id' => 'required|exists:customer,id',
            'repair_part_id' => 'required|exists:repair_parts,id',
            'batch_id' => 'nullable|exists:part_batches,id',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $imeiParam = trim($request->imei);
        $stock = Stock_model::whereRaw('CONCAT(COALESCE(imei,""), COALESCE(serial_number,"")) = ?', [$imeiParam])->first();

        if (! $stock) {
            return redirect()->route('v2.parts-inventory.repair', ['imei' => $imeiParam])
                ->withInput()
                ->withErrors(['imei' => 'Stock not found for this IMEI.']);
        }

        $assignment = PartsRepairAssignment::where('stock_id', $stock->id)->whereNull('repaired_at')->first();

        $data = [
            'stock_id' => $stock->id,
            'repair_part_id' => $request->repair_part_id,
            'part_batch_id' => $request->filled('batch_id') ? $request->batch_id : null,
            'unit_cost' => $request->filled('unit_cost') ? $request->unit_cost : null,
            'reference_id' => $request->reference_id,
            'customer_id' => $request->repairer_id,
            'admin_id' => session('user_id'),
        ];

        if ($assignment) {
            $assignment->update($data);
        } else {
            $data['assigned_at'] = now();
            PartsRepairAssignment::create($data);
        }

        return redirect()->route('v2.parts-inventory.items-to-repair')
            ->with('success', 'Repair submitted. Item recorded in Items to Repair.');
    }

    /**
     * Show repair status for one stock (linked from Internal Repair line item action "Repair status").
     */
    public function repairStatus($id)
    {
        $stock = Stock_model::with(['variation.product', 'variation.grade_id', 'sale_order'])->findOrFail($id);
        $assignments = PartsRepairAssignment::where('stock_id', $stock->id)
            ->with(['repairPart', 'partBatch', 'customer'])
            ->orderByDesc('assigned_at')
            ->get();
        $imei = ($stock->imei ?? '') . ($stock->serial_number ?? '');
        $data['title_page'] = 'Repair status – ' . ($imei ?: 'Stock #' . $stock->id);
        session()->put('page_title', $data['title_page']);
        return view('v2.parts-inventory.repair-status', compact('stock', 'assignments', 'imei'))->with($data);
    }

    /**
     * Batches for a part as JSON (for repair page batch dropdown).
     */
    public function partBatchesJson($id)
    {
        $part = RepairPart::findOrFail($id);
        $batches = PartBatch::where('repair_part_id', $part->id)
            ->orderBy('received_at', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'batch_number', 'unit_cost', 'quantity_remaining', 'received_at']);

        return response()->json($batches);
    }

    /**
     * Resolve product_id from an IMEI (stock is tracked by IMEI; stock -> variation -> product).
     */
    protected function productIdFromImei(string $imei): ?int
    {
        $imei = trim($imei);
        if ($imei === '') {
            return null;
        }
        $stock = Stock_model::with('variation')->where('imei', $imei)->first();

        return $stock && $stock->variation ? (int) $stock->variation->product_id : null;
    }

    public function catalogEdit($id)
    {
        $part = RepairPart::findOrFail($id);
        $data['title_page'] = 'Parts Inventory – Edit Part';
        session()->put('page_title', $data['title_page']);

        return view('v2.parts-inventory.catalog.form', compact('part'))->with($data);
    }

    public function catalogUpdate(Request $request, $id)
    {
        $part = RepairPart::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'compatible_device' => 'nullable|string|max:255',
            'on_hand' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $part->update([
            'name' => $request->name,
            'sku' => $request->sku,
            'compatible_device' => $request->compatible_device,
            'on_hand' => (int) ($request->on_hand ?? 0),
            'reorder_level' => (int) ($request->reorder_level ?? 0),
            'unit_cost' => (float) ($request->unit_cost ?? 0),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('v2.parts-inventory.catalog')->with('success', 'Part updated successfully.');
    }

    /**
     * Show form to attach IMEI to a part (set product from inventory).
     */
    public function attachImei($id)
    {
        $part = RepairPart::findOrFail($id);
        $data['title_page'] = 'Attach IMEI – ' . $part->name;
        session()->put('page_title', $data['title_page']);

        return view('v2.parts-inventory.catalog.attach-imei', compact('part'))->with($data);
    }

    /**
     * Update part's product_id from IMEI (stock from inventory).
     */
    public function attachImeiStore(Request $request, $id)
    {
        $part = RepairPart::findOrFail($id);
        $request->validate([
            'imei' => 'required|string|max:255',
        ]);

        $productId = $this->productIdFromImei($request->imei);
        if (! $productId) {
            return redirect()->back()->withInput()->withErrors(['imei' => 'IMEI not found in inventory. Use an IMEI from your Inventory.']);
        }

        $part->update(['product_id' => $productId]);

        return redirect()->route('v2.parts-inventory.catalog')->with('success', 'Product linked from IMEI successfully.');
    }

    public function batchReceive(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Batch Receive';
        session()->put('page_title', $data['title_page']);
        $suggestedSku = RepairPart::generateSuggestedSku();

        return view('v2.parts-inventory.batch-receive', compact('suggestedSku'))->with($data);
    }

    public function batchReceiveStore(Request $request)
    {
        $request->validate([
            'sku' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'quantity_received' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'received_at' => 'nullable|date',
            'purchase_date' => 'nullable|date',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $sku = trim($request->sku);
        $name = trim($request->name ?? '');
        $part = RepairPart::where('sku', $sku)->first();

        if (! $part) {
            if ($name === '') {
                return redirect()->back()->withInput()->withErrors(['name' => 'Part with this SKU not found. For new parts, name is required.']);
            }
            $part = RepairPart::create([
                'sku' => $sku,
                'name' => $name,
                'product_id' => null,
                'on_hand' => 0,
                'reorder_level' => 0,
                'unit_cost' => (float) $request->unit_cost,
                'active' => true,
            ]);
        }

        $batchNumber = PartBatch::generateBatchNumber();
        $receivedAt = $request->received_at ?: now()->format('Y-m-d');
        $purchaseDate = $request->purchase_date ?: $receivedAt;

        $service = app(RepairPartService::class);
        $service->receiveBatch(
            (int) $part->id,
            $batchNumber,
            (int) $request->quantity_received,
            (float) $request->unit_cost,
            [
                'name_label' => $name !== '' ? $name : null,
                'received_at' => $receivedAt,
                'purchase_date' => $purchaseDate,
                'supplier' => $request->filled('supplier') ? $request->supplier : null,
                'notes' => $request->filled('notes') ? $request->notes : null,
            ]
        );

        return redirect()->route('v2.parts-inventory.catalog')->with('success', 'Batch ' . $batchNumber . ' received successfully.');
    }

    public function inventory(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Batches';
        session()->put('page_title', $data['title_page']);

        $query = PartBatch::with(['repairPart.product'])
            ->inStock();

        if ($request->filled('search')) {
            $q = trim($request->search);
            $query->where(function ($qry) use ($q) {
                $qry->where('batch_number', 'like', "%{$q}%")
                    ->orWhereHas('repairPart', function ($p) use ($q) {
                        $p->where('name', 'like', "%{$q}%")
                            ->orWhere('sku', 'like', "%{$q}%")
                            ->orWhereHas('product', fn ($pr) => $pr->where('model', 'like', "%{$q}%"));
                    });
            });
        }
        if ($request->filled('low_stock') && $request->low_stock === '1') {
            $query->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('repair_parts')
                    ->whereColumn('repair_parts.id', 'part_batches.repair_part_id')
                    ->whereColumn('part_batches.quantity_remaining', '<=', 'repair_parts.reorder_level');
            });
        }

        $batches = $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('v2.parts-inventory.inventory', compact('batches'))->with($data);
    }

    /**
     * Edit a batch (batch number, quantity remaining, unit cost, received at, notes).
     */
    public function batchEdit($id)
    {
        $batch = PartBatch::with('repairPart.product')->findOrFail($id);
        $data['title_page'] = 'Edit batch – ' . ($batch->batch_number ?? '#' . $batch->id);
        session()->put('page_title', $data['title_page']);
        return view('v2.parts-inventory.batch-edit', compact('batch'))->with($data);
    }

    /**
     * Update batch record.
     */
    public function batchUpdate(Request $request, $id)
    {
        $batch = PartBatch::findOrFail($id);
        $request->validate([
            'batch_number' => 'required|string|max:64',
            'quantity_remaining' => 'required|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'received_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);
        $batch->batch_number = $request->batch_number;
        $batch->quantity_remaining = (int) $request->quantity_remaining;
        $batch->unit_cost = $request->filled('unit_cost') ? (float) $request->unit_cost : null;
        $batch->received_at = $request->filled('received_at') ? $request->received_at : $batch->received_at;
        $batch->notes = $request->filled('notes') ? $request->notes : null;
        $batch->save();
        return redirect()->route('v2.parts-inventory.inventory')->with('success', 'Batch updated.');
    }

    /**
     * Paginated in-stock batches for a part (for modal on inventory page).
     */
    public function partBatches(Request $request, $id)
    {
        $part = RepairPart::findOrFail($id);
        $batches = PartBatch::where('repair_part_id', $part->id)
            ->inStock()
            ->orderBy('received_at')
            ->orderBy('id')
            ->paginate(10);

        $batchList = collect($batches->items())->map(function ($b) {
            return [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'quantity_remaining' => $b->quantity_remaining,
                'received_at' => $b->received_at ? $b->received_at->format('Y-m-d') : null,
            ];
        })->all();

        return response()->json([
            'part' => [
                'id' => $part->id,
                'name' => $part->name,
                'sku' => $part->sku,
            ],
            'batches' => $batchList,
            'pagination' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    /**
     * Broken parts history for a part.
     */
    public function brokenHistory(Request $request, $id)
    {
        $part = RepairPart::with('product')->findOrFail($id);
        $data['title_page'] = 'Broken parts history – ' . $part->name;
        session()->put('page_title', $data['title_page']);

        $query = PartBrokenRecord::where('repair_part_id', $part->id)
            ->with(['partBatch', 'admin']);

        if ($request->filled('batch_id')) {
            $query->where('part_batch_id', $request->batch_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $records = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        return view('v2.parts-inventory.part-broken-history', compact('part', 'records'))->with($data);
    }

    /**
     * List only repair records submitted via v2/parts-inventory/repair (one row per parts_repair_assignment).
     * Relation: each record links to stock (the internal repair line item). Not a duplicate of internal repair stock list.
     */
    public function itemsToRepair(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Items to Repair';
        session()->put('page_title', $data['title_page']);

        $query = PartsRepairAssignment::with([
            'stock.variation.product',
            'stock.sale_order',
            'repairPart',
            'partBatch',
            'customer',
        ])->orderByDesc('assigned_at');

        if ($request->filled('status')) {
            if ($request->status === 'assigned') {
                $query->whereNull('repaired_at');
            } elseif ($request->status === 'repaired') {
                $query->whereNotNull('repaired_at');
            }
        }
        if ($request->filled('imei')) {
            $imei = trim($request->imei);
            $query->whereHas('stock', function ($q) use ($imei) {
                $q->where('imei', 'like', '%' . $imei . '%')
                    ->orWhere('serial_number', 'like', '%' . $imei . '%');
            });
        }
        if ($request->filled('reference_id')) {
            $query->where('reference_id', 'like', '%' . trim($request->reference_id) . '%');
        }

        $assignments = $query->paginate(25)->withQueryString();

        $gradeNames = \Illuminate\Support\Facades\DB::table('grade')->whereIn('id', [8, 12, 17])->pluck('name', 'id')->toArray();
        if (empty($gradeNames)) {
            $gradeNames = [8 => 'Repair', 12 => 'Hold', 17 => 'Other'];
        }

        return view('v2.parts-inventory.items-to-repair', compact('assignments', 'gradeNames'))->with($data);
    }

    /**
     * Show the assign-to-repair page: attach a part from parts inventory and mark as assigned to repair.
     */
    public function itemAssignRepair($id)
    {
        $data['title_page'] = 'Parts Inventory – Assign to Repair';
        session()->put('page_title', $data['title_page']);

        $stock = Stock_model::with(['variation.product', 'sale_order'])->findOrFail($id);
        $assignment = PartsRepairAssignment::where('stock_id', $stock->id)->whereNull('repaired_at')->with('repairPart')->first();
        $parts = RepairPart::active()->orderBy('name')->get(['id', 'name', 'sku']);

        return view('v2.parts-inventory.assign-repair', compact('stock', 'assignment', 'parts'))->with($data);
    }

    /**
     * Store assignment: link stock to a part and mark as assigned to repair.
     */
    public function itemAssignRepairStore(Request $request, $id)
    {
        $stock = Stock_model::findOrFail($id);
        $request->validate([
            'repair_part_id' => 'required|exists:repair_parts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $assignment = PartsRepairAssignment::where('stock_id', $stock->id)->whereNull('repaired_at')->first();
        if ($assignment) {
            $assignment->repair_part_id = $request->repair_part_id;
            $assignment->notes = $request->notes;
            $assignment->admin_id = session('user_id');
            $assignment->save();
        } else {
            PartsRepairAssignment::create([
                'stock_id' => $stock->id,
                'repair_part_id' => $request->repair_part_id,
                'notes' => $request->notes,
                'admin_id' => session('user_id'),
            ]);
        }

        return redirect()->route('v2.parts-inventory.items-to-repair.assign', $stock->id)->with('success', 'Item assigned to repair with selected part.');
    }

    /**
     * Mark a stock item as repaired: set repaired_at on assignment (if any) and stock status to 1.
     */
    public function itemMarkRepaired($id)
    {
        $stock = Stock_model::findOrFail($id);
        PartsRepairAssignment::where('stock_id', $stock->id)->whereNull('repaired_at')->update(['repaired_at' => now()]);
        $stock->status = 1;
        $stock->save();
        return redirect()->back()->with('success', 'Item marked as repaired (moved to available).');
    }

    /**
     * Show bulk import form (CSV upload for multiple batches).
     */
    public function bulkImport()
    {
        $data['title_page'] = 'Parts Inventory – Bulk Import Batches';
        session()->put('page_title', $data['title_page']);

        return view('v2.parts-inventory.bulk-import')->with($data);
    }

    /**
     * Process uploaded CSV: each row = one batch. Part is identified by SKU: if SKU exists, use that part; if not, create new part (firstOrCreate). No part_id — use sku.
     */
    public function bulkImportStore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $service = app(RepairPartService::class);
        $created = 0;
        $errors = [];

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return redirect()->route('v2.parts-inventory.bulk-import')->with('error', 'Could not read file.');
        }

        $header = fgetcsv($handle);
        $header = array_map(function ($c) {
            return trim(strtolower($c));
        }, $header);
        $expected = ['sku', 'name', 'quantity_received', 'unit_cost', 'received_at', 'purchase_date', 'supplier', 'notes'];
        $colIndex = [];
        foreach ($expected as $i => $col) {
            $idx = array_search($col, $header);
            if ($idx === false) {
                $idx = $i;
            }
            $colIndex[$col] = $idx;
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) < 3) {
                continue;
            }
            $sku = trim($row[$colIndex['sku']] ?? '');
            $name = trim($row[$colIndex['name']] ?? '');
            $qty = (int) ($row[$colIndex['quantity_received']] ?? 0);
            $unitCost = (float) ($row[$colIndex['unit_cost']] ?? 0);
            $receivedAt = trim($row[$colIndex['received_at']] ?? '');
            $purchaseDate = trim($row[$colIndex['purchase_date']] ?? '');
            $supplier = trim($row[$colIndex['supplier']] ?? '');
            $notes = trim($row[$colIndex['notes']] ?? '');

            if ($sku === '') {
                $errors[] = "Row {$rowNum}: sku is required.";
                continue;
            }
            if ($qty < 1) {
                $errors[] = "Row {$rowNum}: quantity_received required (min 1).";
                continue;
            }

            $part = RepairPart::where('sku', $sku)->first();
            if (! $part) {
                if ($name === '') {
                    $errors[] = "Row {$rowNum}: part with sku \"{$sku}\" not found. For new parts, name is required.";
                    continue;
                }
                $part = RepairPart::create([
                    'sku' => $sku,
                    'name' => $name,
                    'product_id' => null,
                    'on_hand' => 0,
                    'reorder_level' => 0,
                    'unit_cost' => $unitCost ?: 0,
                    'active' => true,
                ]);
            }

            $batchNumber = PartBatch::generateBatchNumber();
            $receivedAtValue = $receivedAt ?: now()->format('Y-m-d');
            $purchaseDateValue = $purchaseDate ?: $receivedAtValue;
            try {
                $service->receiveBatch($part->id, $batchNumber, $qty, $unitCost ?: 0, [
                    'name_label' => $name !== '' ? $name : null,
                    'received_at' => $receivedAtValue,
                    'purchase_date' => $purchaseDateValue,
                    'supplier' => $supplier ?: null,
                    'notes' => $notes ?: null,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }
        fclose($handle);

        $showUrl = route('v2.parts-inventory.inventory');
        $msg = $created . ' batch(es) created. <a href="' . e($showUrl) . '" class="alert-link">Show</a>';
        if (count($errors) > 0) {
            $msg .= ' ' . count($errors) . ' row(s) failed: ' . e(implode(' ', array_slice($errors, 0, 5)));
            if (count($errors) > 5) {
                $msg .= ' ...';
            }
        }

        return redirect()->route('v2.parts-inventory.bulk-import')
            ->with('success', $msg)
            ->with('bulk_import_errors', $errors);
    }

    /**
     * Download sample CSV for bulk import. Batch ref is system-generated on import (not in CSV).
     */
    public function bulkImportSample()
    {
        $today = date('Y-m-d');

        $csv = "sku,name,quantity_received,unit_cost,received_at,purchase_date,supplier,notes\n";
        $csv .= "SCR-001,Screen Assembly XYZ,100,5.50,{$today},{$today},Supplier A,First batch\n";
        $csv .= "BATT-002,Battery 3000mAh,50,12.00,{$today},,Supplier B,Leave purchase_date blank = use received_at\n";
        $csv .= "SCR-001,Screen Assembly XYZ,25,5.25,{$today},{$today},Supplier A,Same SKU = same part\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'parts_inventory_bulk_import_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Download CSV listing parts with SKU for reference when building import file.
     */
    public function bulkImportPartsReference()
    {
        $parts = RepairPart::with('product')->orderBy('id')->get();

        $csv = "sku,name,product_id,product,compatible_device\n";
        foreach ($parts as $p) {
            $name = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->name);
            $sku = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->sku ?? '');
            $product = $p->product ? str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->product->model ?? '') : '';
            $compat = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->compatible_device ?? '');
            $csv .= '"' . $sku . '","' . $name . '",' . $p->product_id . ',"' . $product . '","' . $compat . "\"\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'parts_reference_skus_in_database.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
