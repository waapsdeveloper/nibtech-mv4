<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Admin_model;
use App\Models\PartBatch;
use App\Models\Process_model;
use App\Models\Products_model;
use App\Models\RepairPart;
use App\Models\PartsRepairAssignment;
use App\Models\RepairPartUsage;
use App\Models\Stock_model;
use App\Models\Variation_model;
use App\Services\Repair\RepairPartService;
use Illuminate\Http\Request;

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

        $query = RepairPart::with('product')->withCount('batches');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('compatible_device', 'like', "%{$q}%")
                    ->orWhereHas('product', fn ($p) => $p->where('model', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('active')) {
            if ($request->active === '1') {
                $query->where('active', true);
            } elseif ($request->active === '0') {
                $query->where('active', false);
            }
        }

        $parts = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('v2.parts-inventory.catalog.index', compact('parts'))->with($data);
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

    public function catalogCreate()
    {
        $data['title_page'] = 'Parts Inventory – Add Part';
        session()->put('page_title', $data['title_page']);
        $part = new RepairPart;

        return view('v2.parts-inventory.catalog.form', compact('part'))->with($data);
    }

    public function catalogStore(Request $request)
    {
        $request->validate([
            'imei' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'compatible_device' => 'nullable|string|max:255',
            'on_hand' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productId = null;
        if ($request->filled('imei')) {
            $productId = $this->productIdFromImei($request->imei);
            if (! $productId) {
                return redirect()->back()->withInput()->withErrors(['imei' => 'IMEI not found in inventory. Use an IMEI from your stock (e.g. Inventory).']);
            }
        }

        RepairPart::create([
            'product_id' => $productId,
            'name' => $request->name,
            'sku' => $request->sku,
            'compatible_device' => $request->compatible_device,
            'on_hand' => (int) ($request->on_hand ?? 0),
            'reorder_level' => (int) ($request->reorder_level ?? 0),
            'unit_cost' => (float) ($request->unit_cost ?? 0),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('v2.parts-inventory.catalog')->with('success', 'Part added successfully.');
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
            'imei' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'compatible_device' => 'nullable|string|max:255',
            'on_hand' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        $productId = $part->product_id;
        if ($request->filled('imei')) {
            $resolved = $this->productIdFromImei($request->imei);
            if (! $resolved) {
                return redirect()->back()->withInput()->withErrors(['imei' => 'IMEI not found in inventory.']);
            }
            $productId = $resolved;
        }

        $part->update([
            'product_id' => $productId,
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
        $parts = RepairPart::active()->orderBy('name')->get();

        return view('v2.parts-inventory.batch-receive', compact('parts'))->with($data);
    }

    public function batchReceiveStore(Request $request)
    {
        $request->validate([
            'repair_part_id' => 'required|exists:repair_parts,id',
            'batch_number' => 'required|string|max:255',
            'quantity_received' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'received_at' => 'nullable|date',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $service = app(RepairPartService::class);
        $service->receiveBatch(
            (int) $request->repair_part_id,
            $request->batch_number,
            (int) $request->quantity_received,
            (float) $request->unit_cost,
            [
                'received_at' => $request->received_at ?: now()->format('Y-m-d'),
                'supplier' => $request->supplier,
                'notes' => $request->notes,
            ]
        );

        return redirect()->route('v2.parts-inventory.batch-receive')->with('success', 'Batch received successfully.');
    }

    public function inventory(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Inventory';
        session()->put('page_title', $data['title_page']);

        $query = RepairPart::with('product');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhereHas('product', fn ($p) => $p->where('model', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('low_stock') && $request->low_stock === '1') {
            $query->whereColumn('on_hand', '<=', 'reorder_level');
        }

        $parts = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('v2.parts-inventory.inventory', compact('parts'))->with($data);
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
     * List stock/items that are "to be repaired" (aftersale status 2, grade Repair/Hold etc.).
     * Same pool as dashboard Aftersale Inventory – Repair/Hold grades.
     */
    public function itemsToRepair(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Items to Repair';
        session()->put('page_title', $data['title_page']);

        $query = Stock_model::with(['variation.product', 'sale_order'])
            ->where('stock.status', 2)
            ->whereDoesntHave('sale_order', function ($q) {
                $q->where('customer_id', 3955);
            })
            ->whereHas('sale_order', function ($q) {
                $q->where('order_type_id', 3)->orWhere('reference_id', 999);
            })
            ->whereHas('variation');

        $grades = [8, 12, 17]; // 8 = Repair, 12 = Hold, 17 = other aftersale
        if ($request->filled('grade')) {
            $grades = array_map('intval', (array) $request->grade);
            $query->whereHas('variation', fn ($q) => $q->whereIn('grade', $grades));
        } else {
            $query->whereHas('variation', fn ($q) => $q->whereIn('grade', $grades));
        }

        if ($request->filled('imei')) {
            $imei = trim($request->imei);
            $query->where(function ($q) use ($imei) {
                $q->where('imei', 'like', '%' . $imei . '%')
                    ->orWhere('serial_number', 'like', '%' . $imei . '%');
            });
        }

        $items = $query->orderBy('stock.id', 'desc')->paginate(25)->withQueryString();

        $gradeNames = \Illuminate\Support\Facades\DB::table('grade')->whereIn('id', [8, 12, 17])->pluck('name', 'id')->toArray();
        if (empty($gradeNames)) {
            $gradeNames = [8 => 'Repair', 12 => 'Hold', 17 => 'Other'];
        }

        $stockIds = $items->pluck('id')->toArray();
        $assignmentsByStock = PartsRepairAssignment::whereIn('stock_id', $stockIds)
            ->whereNull('repaired_at')
            ->with('repairPart')
            ->get()
            ->keyBy('stock_id');

        return view('v2.parts-inventory.items-to-repair', compact('items', 'gradeNames', 'assignmentsByStock'))->with($data);
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

    public function usage(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Usage History';
        session()->put('page_title', $data['title_page']);

        $query = RepairPartUsage::with(['part.product', 'batch', 'stock.variation.product', 'process', 'technician']);

        if ($request->filled('part_id')) {
            $query->where('repair_part_id', $request->part_id);
        }
        if ($request->filled('imei')) {
            $imei = trim($request->imei);
            $query->whereHas('stock', function ($q) use ($imei) {
                $q->where('imei', 'like', '%' . $imei . '%')
                    ->orWhere('serial_number', 'like', '%' . $imei . '%');
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $usages = $query->latest()->paginate(25)->withQueryString();
        $partsForFilter = RepairPart::active()->orderBy('name')->pluck('name', 'id');
        $partsForRecord = RepairPart::active()->orderBy('name')->get();
        $processes = Process_model::orderBy('id', 'desc')->limit(300)->pluck('reference_id', 'id');
        $technicians = Admin_model::orderBy('first_name')->get()->mapWithKeys(function ($a) {
            $name = trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')) ?: ('ID ' . $a->id);
            return [$a->id => $name];
        });

        return view('v2.parts-inventory.usage', compact('usages', 'partsForFilter', 'partsForRecord', 'processes', 'technicians'))->with($data);
    }

    /**
     * Get one usage record (JSON) for detail modal.
     */
    public function usageDetail($id)
    {
        $u = RepairPartUsage::with(['part.product', 'batch', 'stock', 'process', 'technician'])->findOrFail($id);
        $imei = $u->stock ? ($u->stock->imei ?? $u->stock->serial_number ?? '') : '';
        return response()->json([
            'id' => $u->id,
            'created_at' => $u->created_at->format('Y-m-d H:i'),
            'part' => $u->part ? $u->part->name : '–',
            'part_sku' => $u->part->sku ?? '',
            'batch' => $u->batch ? $u->batch->batch_number : '–',
            'qty' => $u->qty,
            'unit_cost' => $u->unit_cost,
            'total_cost' => $u->total_cost,
            'imei' => $imei,
            'notes' => $u->notes ?? '',
            'process_id' => $u->process_id,
            'process' => $u->process ? ($u->process->reference_id ?? '#' . $u->process_id) : '–',
            'technician_id' => $u->technician_id,
            'technician' => $u->technician ? trim(($u->technician->first_name ?? '') . ' ' . ($u->technician->last_name ?? '')) : '–',
        ]);
    }

    /**
     * Update usage record (IMEI/stock, process, technician, notes).
     */
    public function usageUpdate(Request $request, $id)
    {
        $usage = RepairPartUsage::findOrFail($id);
        $request->validate([
            'imei' => 'nullable|string|max:255',
            'process_id' => 'nullable|exists:process,id',
            'technician_id' => 'nullable|exists:admin,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($request->filled('imei')) {
            $imei = trim($request->imei);
            $stock = Stock_model::where('imei', $imei)->orWhere('serial_number', $imei)->first();
            if (! $stock) {
                return redirect()->route('v2.parts-inventory.usage')
                    ->with('error', 'Stock not found for IMEI/serial: ' . e($imei));
            }
            $usage->stock_id = $stock->id;
        }
        $usage->process_id = $request->filled('process_id') ? $request->process_id : null;
        $usage->technician_id = $request->filled('technician_id') ? $request->technician_id : null;
        $usage->notes = $request->filled('notes') ? $request->notes : null;
        $usage->save();

        return redirect()->route('v2.parts-inventory.usage')->with('success', 'Usage record updated.');
    }

    /**
     * Delete (soft delete) a usage record.
     */
    public function usageDelete($id)
    {
        $usage = RepairPartUsage::findOrFail($id);
        $usage->delete();
        return redirect()->route('v2.parts-inventory.usage')->with('success', 'Usage record deleted.');
    }

    /**
     * Record part usage: part used to fix a stock item (e.g. faulty battery on a phone).
     */
    public function usageStore(Request $request)
    {
        $request->validate([
            'imei' => 'required|string|max:255',
            'repair_part_id' => 'required|exists:repair_parts,id',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $imei = trim($request->imei);
        $stock = Stock_model::where('imei', $imei)
            ->orWhere('serial_number', $imei)
            ->first();

        if (! $stock) {
            return redirect()->route('v2.parts-inventory.usage')
                ->with('error', 'Stock item not found for IMEI/serial: ' . e($imei) . '. Use an IMEI or serial from your Inventory.')
                ->with('open_record_usage_modal', true)
                ->withInput();
        }

        $service = app(RepairPartService::class);
        try {
            $service->consumePart(
                (int) $request->repair_part_id,
                (int) $request->qty,
                [
                    'stock_id' => $stock->id,
                    'technician_id' => auth()->id(),
                    'notes' => $request->filled('notes') ? $request->notes : null,
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('v2.parts-inventory.usage')
                ->with('error', $e->getMessage())
                ->with('open_record_usage_modal', true)
                ->withInput();
        }

        return redirect()->route('v2.parts-inventory.usage')
            ->with('success', 'Part usage recorded. Stock item (IMEI ' . e($imei) . ') linked to this part.');
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
        $expected = ['sku', 'name', 'imei', 'batch_number', 'quantity_received', 'unit_cost', 'received_at', 'purchase_date', 'supplier', 'notes'];
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
            $imei = trim($row[$colIndex['imei']] ?? '');
            $batchNumber = trim($row[$colIndex['batch_number']] ?? '');
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
            if (! $batchNumber || $qty < 1) {
                $errors[] = "Row {$rowNum}: batch_number and quantity_received required.";
                continue;
            }

            $invalidImeiNote = '';
            $part = RepairPart::where('sku', $sku)->first();
            if (! $part) {
                if ($name === '') {
                    $errors[] = "Row {$rowNum}: part with sku \"{$sku}\" not found. For new parts, name is required (imei optional; attach later from catalog).";
                    continue;
                }
                $productId = null;
                if ($imei !== '') {
                    $productId = $this->productIdFromImei($imei);
                    if (! $productId) {
                        $invalidImeiNote = ' [IMEI ' . $imei . ' not found in inventory; attach from catalog if needed.]';
                    }
                }
                $part = RepairPart::create([
                    'sku' => $sku,
                    'name' => $name,
                    'product_id' => $productId,
                    'on_hand' => 0,
                    'reorder_level' => 0,
                    'unit_cost' => $unitCost ?: 0,
                    'active' => true,
                ]);
            }

            $batchNotes = $notes;
            if ($invalidImeiNote !== '') {
                $batchNotes = trim($batchNotes . $invalidImeiNote);
            }
            $receivedAtValue = $receivedAt ?: now()->format('Y-m-d');
            $purchaseDateValue = $purchaseDate ?: $receivedAtValue;
            try {
                $service->receiveBatch($part->id, $batchNumber, $qty, $unitCost ?: 0, [
                    'received_at' => $receivedAtValue,
                    'purchase_date' => $purchaseDateValue,
                    'supplier' => $supplier ?: null,
                    'notes' => $batchNotes ?: null,
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
     * Download sample CSV for bulk import. Uses imei (from inventory); product is resolved from stock by IMEI.
     */
    public function bulkImportSample()
    {
        $today = date('Y-m-d');
        $stocksWithImei = Stock_model::with('variation')
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->limit(3)
            ->get();
        $exampleImeis = $stocksWithImei->pluck('imei')->filter()->values()->all();
        $imei1 = $exampleImeis[0] ?? 'REPLACE_WITH_IMEI_FROM_INVENTORY';
        $imei2 = $exampleImeis[1] ?? $imei1;
        $imei3 = $exampleImeis[2] ?? $imei1;

        $csv = "sku,name,imei,batch_number,quantity_received,unit_cost,received_at,purchase_date,supplier,notes\n";
        $csv .= sprintf(
            "SCR-001,Screen Assembly XYZ,%s,BATCH-001,100,5.50,%s,%s,Supplier A,First batch\n",
            $imei1,
            $today,
            $today
        );
        $csv .= sprintf(
            "BATT-002,Battery 3000mAh,%s,BATCH-002,50,12.00,%s,,Supplier B,Leave purchase_date blank = use received_at\n",
            $imei2,
            $today
        );
        $csv .= sprintf(
            "SCR-001,Screen Assembly XYZ,%s,BATCH-003,25,5.25,%s,%s,Supplier A,Same SKU = same part\n",
            $imei3,
            $today,
            $today
        );

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'parts_inventory_bulk_import_sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Download CSV listing parts with SKU and example IMEI (from inventory) for that product.
     */
    public function bulkImportPartsReference()
    {
        $parts = RepairPart::with('product')->orderBy('id')->get();
        $imeiByProductId = Stock_model::with('variation')
            ->whereNotNull('imei')
            ->get()
            ->filter(fn ($s) => $s->variation)
            ->groupBy(fn ($s) => $s->variation->product_id)
            ->map(fn ($stocks) => $stocks->first()->imei ?? '');

        $csv = "sku,name,product_id,product,example_imei,compatible_device\n";
        foreach ($parts as $p) {
            $name = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->name);
            $sku = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->sku ?? '');
            $product = $p->product ? str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->product->model ?? '') : '';
            $compat = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $p->compatible_device ?? '');
            $exampleImei = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], $imeiByProductId->get($p->product_id, ''));
            $csv .= '"' . $sku . '","' . $name . '",' . $p->product_id . ',"' . $product . '","' . $exampleImei . '","' . $compat . "\"\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'parts_reference_skus_in_database.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
