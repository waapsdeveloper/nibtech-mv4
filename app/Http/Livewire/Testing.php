<?php

namespace App\Http\Livewire;


use App\Models\Api_request_model;
use Livewire\Component;

use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Process_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class Testing extends Component
{

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {


        $testing = new Api_request_model();
        $testing->push_testing();
        $this->remove_extra_variations();

        die;

        // $data['requests'] = $requests;




        // return view('livewire.testing', $data); // Return the Blade view instance with data
    }


    private function remove_extra_variations(){
        // $variations = Variation_model::limit(100)->pluck('id');
        // if(file_exists('variations.txt')){
        //     $last_id = file_get_contents('variations.txt');
        //     $variations = Variation_model::where('id','>',$last_id)->limit(100)->pluck('id');
        //     if($variations->count() == 0){
        //         $variations = Variation_model::limit(100)->pluck('id');
        //     }
        // }
        // foreach($variations as $id){
        //     $variation = Variation_model::find($id);
        //     if($variation != null){

        //         $duplicates = Variation_model::where(['product_id'=>$variation->product_id,'reference_id'=>$variation->reference_id,'storage'=>$variation->storage,'color'=>$variation->color,'grade'=>$variation->grade])
        //         ->whereNot('id',$variation->id)->get();
        //         if($duplicates->count() > 0){
        //             foreach($duplicates as $duplicate){
        //                 Listing_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Order_item_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Process_model::where('old_variation_id',$duplicate->id)->update(['old_variation_id'=>$variation->id]);
        //                 Process_model::where('new_variation_id',$duplicate->id)->update(['new_variation_id'=>$variation->id]);
        //                 Stock_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Stock_operations_model::where('old_variation_id',$duplicate->id)->update(['old_variation_id'=>$variation->id]);
        //                 Stock_operations_model::where('new_variation_id',$duplicate->id)->update(['new_variation_id'=>$variation->id]);

        //                 $duplicate->delete();
        //             }
        //         }
        //         file_put_contents('variations.txt', $id);
        //     }
        // }

        // $variations_2 = Variation_model::where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        // echo $variations_2->count();
        // die;
        // if(file_exists('variations_2.txt')){
        //     $last_id = file_get_contents('variations_2.txt');
        //     $variations_2 = Variation_model::where('id','>',$last_id)->where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        //     if($variations_2->count() == 0){
        //         $variations_2 = Variation_model::where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        //     }
        // }
        // foreach($var
        // Find all duplicate SKUs
        $duplicateSKUs = Variation_model::select('sku')
        ->whereNotNull('sku')
        ->whereNotNull('product_id')
        ->withTrashed()
        ->groupBy('sku')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('sku');

        // Fetch IDs of variations with duplicate SKUs
        $variations = Variation_model::whereIn('sku', $duplicateSKUs)
        ->withTrashed()
        ->pluck('sku','id');
        print_r($variations);
        die;
        $variations = Variation_model::whereNotNull('sku')
            ->whereNotNull('product_id')
            ->withTrashed()
            ->pluck('id');

        $processedVariations = $variations->map(function ($id) {
            $variation = Variation_model::withTrashed()->find($id);

            if ($variation) {
                // Find duplicates
                $duplicates = Variation_model::where('sku', $variation->sku)
                    ->where('grade', $variation->grade)
                    ->where('id', '!=', $variation->id)
                    ->withTrashed()
                    ->get();

                if ($duplicates->isNotEmpty()) {
                    DB::transaction(function () use ($variation, $duplicates) {
                        foreach ($duplicates as $duplicate) {
                            // Update related records to point to the original variation
                            Listing_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Order_item_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Process_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $variation->id]);
                            Process_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $variation->id]);
                            Stock_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Stock_operations_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $variation->id]);
                            Stock_operations_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $variation->id]);

                            // Soft delete the duplicate
                            $duplicate->delete();
                        }

                        // Restore the original variation if it was soft deleted
                        if ($variation->trashed()) {
                            $variation->restore();
                        }
                    });

                    Log::info('Processed variation ID: ' . $variation->id);
                }

                // Save the last processed ID to a file (or a more suitable persistent storage)
                file_put_contents('variations_2.txt', $id);
            }
        });

        echo 'Processing competed';

    }



}
