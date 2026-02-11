<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ListingThirtyOrder;
use App\Models\ListingThirtyOrderRef;
use App\Models\Variation_model;
use App\Models\Order_model;
use Illuminate\Http\Request;

class ListingThirtyController extends Controller
{
    /**
     * Display a listing of listing_thirty_orders (BM sync records).
     */
    public function index(Request $request)
    {
        $data['title_page'] = 'Listing-30';
        session()->put('page_title', $data['title_page']);

        $query = ListingThirtyOrder::with('variation')
            ->orderBy('synced_at', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('variation_id')) {
            $query->where('variation_id', $request->variation_id);
        }
        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }
        if ($request->filled('country_code')) {
            $query->where('country_code', $request->country_code);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('bm_listing_id')) {
            $query->where('bm_listing_id', 'like', '%' . $request->bm_listing_id . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('synced_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('synced_at', '<=', $request->date_to);
        }

        $data['items'] = $query->paginate(25)->withQueryString();
        $data['total_count'] = ListingThirtyOrder::count();
        $data['sources'] = [
            'get_listings' => 'get_listings',
            'get_listingsBi' => 'get_listingsBi',
        ];

        return view('v2.listing-thirty.index', $data);
    }

    /**
     * Show the form for creating a new listing thirty order.
     */
    public function create()
    {
        $data['title_page'] = 'Create Listing-30 Record';
        session()->put('page_title', $data['title_page']);
        $data['variations'] = Variation_model::whereNotNull('sku')->orderBy('sku')->get();

        return view('v2.listing-thirty.create', $data);
    }

    /**
     * Store a newly created listing thirty order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'variation_id' => 'nullable|exists:variation,id',
            'country_code' => 'nullable|string|max:10',
            'bm_listing_id' => 'required|string|max:255',
            'bm_listing_uuid' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'source' => 'required|in:get_listings,get_listingsBi',
            'quantity' => 'required|integer|min:0',
            'publication_state' => 'nullable|integer|min:0|max:4',
            'state' => 'nullable|integer',
            'title' => 'nullable|string|max:500',
            'price_amount' => 'nullable|numeric',
            'price_currency' => 'nullable|string|max:10',
            'min_price' => 'nullable|numeric',
            'max_price' => 'nullable|numeric',
        ]);

        $validated['synced_at'] = now();

        ListingThirtyOrder::create($validated);

        return redirect()->route('v2.listing-thirty.index')
            ->with('success', 'Listing-30 record created successfully.');
    }

    /**
     * Display the specified listing thirty order and its refs.
     */
    public function show($id)
    {
        $data['title_page'] = 'Listing-30 Details';
        session()->put('page_title', $data['title_page']);

        $data['item'] = ListingThirtyOrder::with(['variation', 'refs.order', 'refs.variation'])->findOrFail($id);
        $data['orders'] = Order_model::where('order_type_id', 3)->orderBy('created_at', 'desc')->limit(200)->get();

        return view('v2.listing-thirty.show', $data);
    }

    /**
     * Show the form for editing the specified listing thirty order.
     */
    public function edit($id)
    {
        $data['title_page'] = 'Edit Listing-30 Record';
        session()->put('page_title', $data['title_page']);

        $data['item'] = ListingThirtyOrder::findOrFail($id);
        $data['variations'] = Variation_model::whereNotNull('sku')->orderBy('sku')->get();

        return view('v2.listing-thirty.edit', $data);
    }

    /**
     * Update the specified listing thirty order.
     */
    public function update(Request $request, $id)
    {
        $item = ListingThirtyOrder::findOrFail($id);

        $validated = $request->validate([
            'variation_id' => 'nullable|exists:variation,id',
            'country_code' => 'nullable|string|max:10',
            'bm_listing_id' => 'required|string|max:255',
            'bm_listing_uuid' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'source' => 'required|in:get_listings,get_listingsBi',
            'quantity' => 'required|integer|min:0',
            'publication_state' => 'nullable|integer|min:0|max:4',
            'state' => 'nullable|integer',
            'title' => 'nullable|string|max:500',
            'price_amount' => 'nullable|numeric',
            'price_currency' => 'nullable|string|max:10',
            'min_price' => 'nullable|numeric',
            'max_price' => 'nullable|numeric',
        ]);

        $item->update($validated);

        return redirect()->route('v2.listing-thirty.index')
            ->with('success', 'Listing-30 record updated successfully.');
    }

    /**
     * Remove the specified listing thirty order.
     */
    public function destroy($id)
    {
        $item = ListingThirtyOrder::findOrFail($id);
        $item->refs()->delete();
        $item->delete();

        return redirect()->route('v2.listing-thirty.index')
            ->with('success', 'Listing-30 record deleted successfully.');
    }

    /**
     * Store a new ref (order link) for a listing thirty order.
     */
    public function storeRef(Request $request, $id)
    {
        $listingThirty = ListingThirtyOrder::findOrFail($id);

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_item_id' => 'nullable|exists:order_items,id',
            'variation_id' => 'nullable|exists:variation,id',
            'bm_order_id' => 'nullable|string|max:255',
            'source_command' => 'required|in:refresh:new,refresh:orders',
        ]);

        $validated['listing_thirty_order_id'] = $listingThirty->id;
        $validated['synced_at'] = now();

        ListingThirtyOrderRef::create($validated);

        return redirect()->route('v2.listing-thirty.show', $id)
            ->with('success', 'Order ref added successfully.');
    }

    /**
     * Remove a ref.
     */
    public function destroyRef($id, $refId)
    {
        $ref = ListingThirtyOrderRef::where('listing_thirty_order_id', $id)->findOrFail($refId);
        $ref->delete();

        return redirect()->route('v2.listing-thirty.show', $id)
            ->with('success', 'Order ref removed.');
    }
}
