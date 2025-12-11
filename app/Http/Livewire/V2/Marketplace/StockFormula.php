<?php

namespace App\Http\Livewire\V2\Marketplace;

use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Marketplace_model;
use App\Models\MarketplaceStockModel;
use App\Services\Marketplace\StockDistributionService;
use Illuminate\Support\Facades\Log;

class StockFormula extends Component
{
    public $searchTerm = '';
    public $selectedVariationId = null;
    public $selectedVariation = null;
    public $marketplaces = [];
    public $marketplaceStocks = [];
    public $formulas = [];
    public $showFormulaForm = false;
    public $editingMarketplaceId = null;
    
    // Formula form fields
    public $formulaType = 'percentage'; // 'percentage' or 'fixed'
    public $formulaMarketplaces = [];
    public $remainingToMarketplace1 = true;

    protected $listeners = ['variationSelected'];

    public function mount($variation_id = null)
    {
        $user_id = session('user_id');
        if ($user_id == NULL) {
            return redirect()->route('login');
        }

        // Load all marketplaces
        $this->marketplaces = Marketplace_model::orderBy('name', 'ASC')->get();

        // If variation_id is provided, load it
        if ($variation_id) {
            $this->selectVariation($variation_id);
        }
    }

    public function updatedSearchTerm()
    {
        // This method is called automatically when searchTerm is updated
        // No need to do anything here, render() will handle it
    }

    public function performSearch()
    {
        // Explicit search method that can be called
        // This ensures search happens even if wire:model doesn't trigger
        $this->searchTerm = trim($this->searchTerm);
    }

    public function render()
    {
        $data['title_page'] = "Stock Formula Management";
        session()->put('page_title', $data['title_page']);

        // Search variations
        $variations = collect();
        $searchTerm = trim($this->searchTerm ?? '');
        
        if (strlen($searchTerm) >= 2) {
            $variations = Variation_model::with(['product', 'color_id', 'storage_id', 'grade_id'])
                ->where(function ($query) use ($searchTerm) {
                    $query->where('sku', 'like', '%' . $searchTerm . '%')
                        ->orWhereHas('product', function ($q) use ($searchTerm) {
                            $q->where('model', 'like', '%' . $searchTerm . '%');
                        });
                })
                ->limit(20)
                ->get();
        }

        return view('livewire.v2.marketplace.stock-formula', [
            'variations' => $variations
        ])->with($data);
    }

    public function selectVariation($variationId)
    {
        $this->selectedVariationId = $variationId;
        $this->selectedVariation = Variation_model::with(['product', 'color_id', 'storage_id', 'grade_id'])
            ->find($variationId);

        if (!$this->selectedVariation) {
            session()->flash('error', 'Variation not found');
            return;
        }

        // Load marketplace stocks for this variation
        $this->loadMarketplaceStocks();
    }

    public function variationSelected($variationId)
    {
        $this->selectVariation($variationId);
    }

    public function loadMarketplaceStocks()
    {
        if (!$this->selectedVariationId) {
            return;
        }

        // Get all marketplace stocks for this variation
        $stocks = MarketplaceStockModel::where('variation_id', $this->selectedVariationId)
            ->with('marketplace')
            ->get();

        // Initialize marketplace stocks array
        $this->marketplaceStocks = [];
        $this->formulas = [];

        foreach ($this->marketplaces as $marketplace) {
            $stock = $stocks->firstWhere('marketplace_id', $marketplace->id);
            
            $this->marketplaceStocks[$marketplace->id] = [
                'marketplace_id' => $marketplace->id,
                'marketplace_name' => $marketplace->name,
                'listed_stock' => $stock ? $stock->listed_stock : 0,
                'formula' => $stock && $stock->formula ? $stock->formula : null,
                'has_formula' => $stock && $stock->formula ? true : false,
                'stock_id' => $stock ? $stock->id : null,
            ];

            // Initialize formula array for this marketplace
            if ($stock && $stock->formula) {
                $this->formulas[$marketplace->id] = $stock->formula;
            } else {
                $this->formulas[$marketplace->id] = [
                    'type' => 'percentage',
                    'marketplaces' => [],
                    'remaining_to_marketplace_1' => true,
                ];
            }
        }
    }

    public function editFormula($marketplaceId)
    {
        $this->editingMarketplaceId = $marketplaceId;
        $formula = $this->formulas[$marketplaceId] ?? [
            'type' => 'percentage',
            'marketplaces' => [],
            'remaining_to_marketplace_1' => true,
        ];

        $this->formulaType = $formula['type'] ?? 'percentage';
        $this->remainingToMarketplace1 = $formula['remaining_to_marketplace_1'] ?? true;
        
        // Initialize formula marketplaces
        $this->formulaMarketplaces = [];
        if (isset($formula['marketplaces'])) {
            foreach ($formula['marketplaces'] as $mp) {
                $this->formulaMarketplaces[] = [
                    'marketplace_id' => $mp['marketplace_id'],
                    'value' => $mp['value'],
                ];
            }
        }

        $this->showFormulaForm = true;
    }

    public function addFormulaMarketplace()
    {
        $this->formulaMarketplaces[] = [
            'marketplace_id' => '',
            'value' => '',
        ];
    }

    public function removeFormulaMarketplace($index)
    {
        unset($this->formulaMarketplaces[$index]);
        $this->formulaMarketplaces = array_values($this->formulaMarketplaces);
    }

    public function saveFormula()
    {
        if (!$this->editingMarketplaceId || !$this->selectedVariationId) {
            session()->flash('error', 'Invalid operation');
            return;
        }

        // Validate formula
        $formula = [
            'type' => $this->formulaType,
            'marketplaces' => [],
            'remaining_to_marketplace_1' => $this->remainingToMarketplace1,
        ];

        foreach ($this->formulaMarketplaces as $mp) {
            if (!empty($mp['marketplace_id']) && !empty($mp['value'])) {
                $formula['marketplaces'][] = [
                    'marketplace_id' => (int)$mp['marketplace_id'],
                    'value' => (float)$mp['value'],
                ];
            }
        }

        // Validate using service
        $service = new StockDistributionService();
        $validation = $service->validateFormula($formula);

        if (!$validation['valid']) {
            session()->flash('error', implode(', ', $validation['errors']));
            return;
        }

        // Get or create marketplace stock record
        $marketplaceStock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => $this->selectedVariationId,
                'marketplace_id' => $this->editingMarketplaceId,
            ],
            [
                'listed_stock' => 0,
                'admin_id' => session('user_id'),
            ]
        );

        // Update formula
        $marketplaceStock->formula = $formula;
        $marketplaceStock->admin_id = session('user_id');
        $marketplaceStock->save();

        // Update local data
        $this->formulas[$this->editingMarketplaceId] = $formula;
        $this->marketplaceStocks[$this->editingMarketplaceId]['formula'] = $formula;
        $this->marketplaceStocks[$this->editingMarketplaceId]['has_formula'] = true;

        $this->showFormulaForm = false;
        $this->editingMarketplaceId = null;
        $this->resetFormulaForm();

        session()->flash('success', 'Formula saved successfully');
    }

    public function deleteFormula($marketplaceId)
    {
        if (!$this->selectedVariationId) {
            return;
        }

        $marketplaceStock = MarketplaceStockModel::where('variation_id', $this->selectedVariationId)
            ->where('marketplace_id', $marketplaceId)
            ->first();

        if ($marketplaceStock) {
            $marketplaceStock->formula = null;
            $marketplaceStock->save();

            $this->formulas[$marketplaceId] = [
                'type' => 'percentage',
                'marketplaces' => [],
                'remaining_to_marketplace_1' => true,
            ];
            $this->marketplaceStocks[$marketplaceId]['formula'] = null;
            $this->marketplaceStocks[$marketplaceId]['has_formula'] = false;

            session()->flash('success', 'Formula deleted successfully');
        }
    }

    public function cancelEdit()
    {
        $this->showFormulaForm = false;
        $this->editingMarketplaceId = null;
        $this->resetFormulaForm();
    }

    private function resetFormulaForm()
    {
        $this->formulaType = 'percentage';
        $this->formulaMarketplaces = [];
        $this->remainingToMarketplace1 = true;
    }
}
