<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\PartsPurchase;
use App\Models\PartsPurchaseBatch;
use App\Models\RepairPart;
use Illuminate\Http\Request;

class PartsPurchaseController extends Controller
{
    /**
     * Add purchase form. Batch-based: new batch (system barcode auto, optional manufacturer barcode)
     * or add to existing batch by system barcode. Optional legacy: stock_id/imei for old flow.
     */
    public function purchaseAdd(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Add Purchase';
        session()->put('page_title', $data['title_page']);

        $batch = null;
        if ($request->filled('batch_id')) {
            $batch = PartsPurchaseBatch::find($request->batch_id);
        } elseif ($request->filled('system_barcode')) {
            $batch = PartsPurchaseBatch::where('system_barcode', trim($request->system_barcode))->first();
        }

        $parts = RepairPart::active()->orderBy('name')->get(['id', 'name', 'sku']);
        $newBatchBarcode = PartsPurchaseBatch::generateSystemBarcode();

        return view('v2.parts-inventory.purchases.add', compact('batch', 'parts', 'newBatchBarcode'))->with($data);
    }

    /**
     * Store new parts purchase. Batch-based: require batch_id (existing) or create new batch
     * with generated system_barcode and optional manufacturer_barcode. No IMEI/stock required.
     */
    public function purchaseStore(Request $request)
    {
        $request->validate([
            'batch_id' => 'nullable|exists:parts_purchase_batches,id',
            'new_batch' => 'nullable|boolean',
            'existing_system_barcode' => 'nullable|string|max:64',
            'manufacturer_barcode' => 'nullable|string|max:255',
            'repair_part_id' => 'required|exists:repair_parts,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'is_lease' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $batchId = $request->filled('batch_id') ? (int) $request->batch_id : null;

        if (! $batchId && $request->filled('existing_system_barcode')) {
            $batch = PartsPurchaseBatch::where('system_barcode', trim($request->existing_system_barcode))->first();
            if ($batch) {
                $batchId = $batch->id;
            } else {
                return redirect()->back()->withInput()->withErrors(['existing_system_barcode' => 'No batch found with this system barcode.']);
            }
        }

        if (! $batchId && $request->boolean('new_batch')) {
            $batch = PartsPurchaseBatch::create([
                'system_barcode' => PartsPurchaseBatch::generateSystemBarcode(),
                'manufacturer_barcode' => $request->filled('manufacturer_barcode') ? trim($request->manufacturer_barcode) : null,
                'notes' => $request->batch_notes ?? null,
            ]);
            $batchId = $batch->id;
        }

        if (! $batchId) {
            return redirect()->back()->withInput()->withErrors(['batch_id' => 'Create a new batch (check the box) or enter an existing batch system barcode.']);
        }

        $isLease = $request->boolean('is_lease');
        $unitPrice = $request->filled('unit_price') ? (float) $request->unit_price : null;

        PartsPurchase::create([
            'batch_id' => $batchId,
            'repair_part_id' => (int) $request->repair_part_id,
            'quantity' => (int) $request->quantity,
            'unit_price' => $unitPrice,
            'is_lease' => $isLease,
            'price_set_at' => $unitPrice !== null ? now() : null,
            'notes' => $request->notes,
            'admin_id' => session('user_id'),
        ]);

        $redirectTo = $request->get('redirect_to', route('v2.parts-inventory.dashboard'));
        return redirect($redirectTo)->with('success', 'Parts purchase recorded.');
    }

    /**
     * Set price on a lease purchase.
     */
    public function purchaseSetPrice(Request $request, $id)
    {
        $purchase = PartsPurchase::findOrFail($id);
        $request->validate([
            'unit_price' => 'required|numeric|min:0',
        ]);

        $purchase->unit_price = (float) $request->unit_price;
        $purchase->price_set_at = now();
        $purchase->save();

        return redirect()->back()->with('success', 'Price set for parts purchase.');
    }

    /**
     * Delete a purchase history record. Admin only (role_id === 1).
     */
    public function purchaseDestroy($id)
    {
        $user = session('user');
        if (! $user || (int) ($user->role_id ?? 0) !== 1) {
            return redirect()->route('v2.parts-inventory.dashboard')
                ->with('error', 'Only admins can delete purchase history.');
        }

        $purchase = PartsPurchase::findOrFail($id);
        $purchase->delete();

        return redirect()->route('v2.parts-inventory.dashboard')
            ->with('success', 'Purchase record deleted.');
    }
}
