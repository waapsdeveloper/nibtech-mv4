<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Stock_deduction_log_model;
use App\Models\Variation_model;
use App\Models\Order_model;
use App\Models\Marketplace_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockDeductionLogController extends Controller
{
    /**
     * Display a listing of stock deduction logs
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Stock Deduction Logs";
        session()->put('page_title', $data['title_page']);
        
        $query = Stock_deduction_log_model::with(['variation', 'order', 'marketplace'])
            ->orderBy('deduction_at', 'desc');
        
        // Filters
        if ($request->filled('variation_id')) {
            $query->where('variation_id', $request->variation_id);
        }
        
        if ($request->filled('order_reference_id')) {
            $query->where('order_reference_id', 'like', '%' . $request->order_reference_id . '%');
        }
        
        if ($request->filled('variation_sku')) {
            $query->where('variation_sku', 'like', '%' . $request->variation_sku . '%');
        }
        
        if ($request->filled('deduction_reason')) {
            $query->where('deduction_reason', $request->deduction_reason);
        }
        
        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->marketplace_id);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('deduction_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('deduction_at', '<=', $request->date_to);
        }
        
        $data['logs'] = $query->paginate(50);
        $data['variations'] = Variation_model::whereNotNull('sku')->orderBy('sku')->get();
        $data['marketplaces'] = Marketplace_model::all();
        $data['deduction_reasons'] = [
            'new_order_status_1' => 'New Order (Status 1)',
            'status_change_1_to_2' => 'Status Change (1 → 2)',
        ];
        
        // Statistics
        $data['total_deductions'] = Stock_deduction_log_model::count();
        $data['today_deductions'] = Stock_deduction_log_model::whereDate('deduction_at', today())->count();
        $data['this_week_deductions'] = Stock_deduction_log_model::whereBetween('deduction_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        
        return view('v2.stock-deduction-logs.index', $data);
    }
    
    /**
     * Show the form for creating a new log entry
     */
    public function create()
    {
        $data['title_page'] = "Create Stock Deduction Log";
        session()->put('page_title', $data['title_page']);
        
        $data['variations'] = Variation_model::whereNotNull('sku')->orderBy('sku')->get();
        $data['orders'] = Order_model::where('order_type_id', 3)->orderBy('created_at', 'desc')->limit(100)->get();
        $data['marketplaces'] = Marketplace_model::all();
        $data['deduction_reasons'] = [
            'new_order_status_1' => 'New Order (Status 1)',
            'status_change_1_to_2' => 'Status Change (1 → 2)',
        ];
        
        return view('v2.stock-deduction-logs.create', $data);
    }
    
    /**
     * Store a newly created log entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'variation_id' => 'required|exists:variation,id',
            'marketplace_id' => 'required|exists:marketplace,id',
            'order_id' => 'nullable|exists:orders,id',
            'order_reference_id' => 'nullable|string|max:255',
            'before_variation_stock' => 'required|integer',
            'before_marketplace_stock' => 'required|integer',
            'after_variation_stock' => 'required|integer',
            'after_marketplace_stock' => 'required|integer',
            'deduction_reason' => 'required|in:new_order_status_1,status_change_1_to_2',
            'order_status' => 'nullable|integer',
            'is_new_order' => 'boolean',
            'old_order_status' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);
        
        $variation = Variation_model::find($validated['variation_id']);
        if ($variation) {
            $validated['variation_sku'] = $variation->sku;
        }
        
        $validated['deduction_at'] = now();
        
        Stock_deduction_log_model::create($validated);
        
        return redirect()->route('v2.stock-deduction-logs.index')
            ->with('success', 'Stock deduction log created successfully.');
    }
    
    /**
     * Display the specified log entry
     */
    public function show($id)
    {
        $data['title_page'] = "Stock Deduction Log Details";
        session()->put('page_title', $data['title_page']);
        
        $data['log'] = Stock_deduction_log_model::with(['variation', 'order', 'marketplace'])->findOrFail($id);
        
        return view('v2.stock-deduction-logs.show', $data);
    }
    
    /**
     * Show the form for editing the specified log entry
     */
    public function edit($id)
    {
        $data['title_page'] = "Edit Stock Deduction Log";
        session()->put('page_title', $data['title_page']);
        
        $data['log'] = Stock_deduction_log_model::findOrFail($id);
        $data['variations'] = Variation_model::whereNotNull('sku')->orderBy('sku')->get();
        $data['orders'] = Order_model::where('order_type_id', 3)->orderBy('created_at', 'desc')->limit(100)->get();
        $data['marketplaces'] = Marketplace_model::all();
        $data['deduction_reasons'] = [
            'new_order_status_1' => 'New Order (Status 1)',
            'status_change_1_to_2' => 'Status Change (1 → 2)',
        ];
        
        return view('v2.stock-deduction-logs.edit', $data);
    }
    
    /**
     * Update the specified log entry
     */
    public function update(Request $request, $id)
    {
        $log = Stock_deduction_log_model::findOrFail($id);
        
        $validated = $request->validate([
            'variation_id' => 'required|exists:variation,id',
            'marketplace_id' => 'required|exists:marketplace,id',
            'order_id' => 'nullable|exists:orders,id',
            'order_reference_id' => 'nullable|string|max:255',
            'before_variation_stock' => 'required|integer',
            'before_marketplace_stock' => 'required|integer',
            'after_variation_stock' => 'required|integer',
            'after_marketplace_stock' => 'required|integer',
            'deduction_reason' => 'required|in:new_order_status_1,status_change_1_to_2',
            'order_status' => 'nullable|integer',
            'is_new_order' => 'boolean',
            'old_order_status' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);
        
        $variation = Variation_model::find($validated['variation_id']);
        if ($variation) {
            $validated['variation_sku'] = $variation->sku;
        }
        
        $log->update($validated);
        
        return redirect()->route('v2.stock-deduction-logs.index')
            ->with('success', 'Stock deduction log updated successfully.');
    }
    
    /**
     * Remove the specified log entry
     */
    public function destroy($id)
    {
        $log = Stock_deduction_log_model::findOrFail($id);
        $log->delete();
        
        return redirect()->route('v2.stock-deduction-logs.index')
            ->with('success', 'Stock deduction log deleted successfully.');
    }
}
