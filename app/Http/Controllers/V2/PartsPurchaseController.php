<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\PartsPurchase;
use App\Models\RepairPart;
use App\Models\Stock_model;
use Illuminate\Http\Request;

class PartsPurchaseController extends Controller
{
    /**
     * Purchase history list with search. Optional ?imei= or ?stock_id= to filter by device.
     */
    public function purchaseHistory(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Purchase History';
        session()->put('page_title', $data['title_page']);

        $query = PartsPurchase::with(['repairPart', 'stock', 'admin']);

        if ($request->filled('imei')) {
            $imei = trim($request->imei);
            $query->whereHas('stock', function ($q) use ($imei) {
                $q->where('imei', 'like', '%' . $imei . '%')
                    ->orWhere('serial_number', 'like', '%' . $imei . '%');
            });
        }
        if ($request->filled('stock_id')) {
            $query->where('stock_id', $request->stock_id);
        }
        if ($request->filled('part_id')) {
            $query->where('repair_part_id', $request->part_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $purchases = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
        $partsForFilter = RepairPart::active()->orderBy('name')->pluck('name', 'id');

        return view('v2.parts-inventory.purchase-history', compact('purchases', 'partsForFilter'))->with($data);
    }

    /**
     * Add purchase form. Pre-fill IMEI/stock when ?imei= or ?stock_id= passed.
     */
    public function purchaseAdd(Request $request)
    {
        $data['title_page'] = 'Parts Inventory – Add Purchase';
        session()->put('page_title', $data['title_page']);

        $stock = null;
        if ($request->filled('stock_id')) {
            $stock = Stock_model::find($request->stock_id);
        } elseif ($request->filled('imei')) {
            $imei = trim($request->imei);
            $stock = Stock_model::where('imei', $imei)
                ->orWhere('serial_number', $imei)
                ->first();
        }

        $parts = RepairPart::active()->orderBy('name')->get(['id', 'name', 'sku']);

        return view('v2.parts-inventory.purchases.add', compact('stock', 'parts'))->with($data);
    }

    /**
     * Store new parts purchase. Redirect back to purchase history or add form with success.
     * Accepts stock_id or imei (resolves stock from IMEI/serial).
     */
    public function purchaseStore(Request $request)
    {
        $request->validate([
            'stock_id' => 'nullable|exists:stock,id',
            'imei' => 'nullable|string|max:255',
            'repair_part_id' => 'required|exists:repair_parts,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'is_lease' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $stockId = $request->filled('stock_id') ? (int) $request->stock_id : null;
        if (! $stockId && $request->filled('imei')) {
            $imei = trim($request->imei);
            $stock = Stock_model::where('imei', $imei)->orWhere('serial_number', $imei)->first();
            if (! $stock) {
                return redirect()->back()->withInput()->withErrors(['imei' => 'No stock found for this IMEI/serial. Add the device to Inventory first.']);
            }
            $stockId = $stock->id;
        }
        if (! $stockId) {
            return redirect()->back()->withInput()->withErrors(['imei' => 'IMEI/Serial or Stock is required.']);
        }

        $isLease = $request->boolean('is_lease');
        $unitPrice = $request->filled('unit_price') ? (float) $request->unit_price : null;

        PartsPurchase::create([
            'stock_id' => $stockId,
            'repair_part_id' => (int) $request->repair_part_id,
            'quantity' => (int) $request->quantity,
            'unit_price' => $unitPrice,
            'is_lease' => $isLease,
            'price_set_at' => $unitPrice !== null ? now() : null,
            'notes' => $request->notes,
            'admin_id' => session('user_id'),
        ]);

        $redirectTo = $request->get('redirect_to', route('v2.parts-inventory.purchase-history'));
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
}
