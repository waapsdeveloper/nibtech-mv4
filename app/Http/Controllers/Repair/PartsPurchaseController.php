<?php

namespace App\Http\Controllers\Repair;

use App\Http\Controllers\Controller;
use App\Models\PartsPurchase;
use App\Models\Stock_model;
use Illuminate\Http\Request;

class PartsPurchaseController extends Controller
{
    /**
     * Get parts purchases for a stock/IMEI (JSON for modal).
     */
    public function index($stock_id)
    {
        $stock = Stock_model::find($stock_id);
        if (! $stock) {
            return response()->json(['purchases' => [], 'imei' => '', 'error' => 'Stock not found'], 404);
        }

        $purchases = PartsPurchase::where('stock_id', $stock_id)
            ->with(['repairPart', 'admin'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'part_name' => $p->repairPart ? $p->repairPart->name : '–',
                    'part_sku' => $p->repairPart->sku ?? '',
                    'quantity' => $p->quantity,
                    'unit_price' => $p->unit_price !== null ? (float) $p->unit_price : null,
                    'total_price' => $p->total_price,
                    'is_lease' => $p->is_lease,
                    'price_set_at' => $p->price_set_at ? $p->price_set_at->format('Y-m-d H:i') : null,
                    'notes' => $p->notes,
                    'created_at' => $p->created_at->format('Y-m-d H:i'),
                    'recorded_by' => $p->admin ? trim(($p->admin->first_name ?? '') . ' ' . ($p->admin->last_name ?? '')) : '',
                ];
            });

        $imei = trim(($stock->imei ?? '') . ($stock->serial_number ?? ''));

        return response()->json([
            'purchases' => $purchases,
            'imei' => $imei,
        ]);
    }

    /**
     * Store a new parts purchase (from internal repair page when IMEI selected).
     * Price can be set now or left null (on lease).
     */
    public function store(Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stock,id',
            'repair_part_id' => 'required|exists:repair_parts,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'is_lease' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $isLease = $request->boolean('is_lease');
        $unitPrice = $request->filled('unit_price') ? (float) $request->unit_price : null;
        if ($isLease && $unitPrice === null) {
            $unitPrice = null;
        }

        PartsPurchase::create([
            'stock_id' => (int) $request->stock_id,
            'repair_part_id' => (int) $request->repair_part_id,
            'quantity' => (int) $request->quantity,
            'unit_price' => $unitPrice,
            'is_lease' => $isLease,
            'price_set_at' => $unitPrice !== null ? now() : null,
            'notes' => $request->notes,
            'admin_id' => session('user_id'),
        ]);

        return redirect()->back()->with('success', 'Parts purchase recorded for this IMEI.');
    }

    /**
     * Set price on a lease purchase (price decided later).
     */
    public function setPrice(Request $request, $id)
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
