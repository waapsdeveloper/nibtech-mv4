<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Merchant_model;
use App\Models\Transactions_model;
use App\Models\Transaction_status_model;
use App\Models\Transaction_type_model;
use GuzzleHttp\Psr7\Request;

class Transactions extends Component
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

        $user_id = session('user_id');
        $filters = [
            'merchant_id' => $user_id,
        ];
        if(isset($_GET['transaction_type']) && $_GET['transaction_type'] != FALSE){
            $filters['transaction_type'] = $_GET['transaction_type'];
        }
        if(isset($_GET['status']) && $_GET['status'] != FALSE){
            $filters['status'] = $_GET['status'];
        }
        $data['our_id'] = Merchant_model::select('our_id')->where('id',$user_id)->first()->our_id;
        $data['transaction_types'] = Transaction_type_model::get();
        $data['transaction_statuses'] = Transaction_status_model::get();
        $data['transactions'] = Transactions_model::select('id','m_ref','datetime','transaction_type','amount','charges','status','authorized_by_id')
        ->when(request('start_date') != '', function ($q) {
            return $q->where('datetime', '>=', request('start_date', 0));
        })->when(request('end_date') != '', function ($q) {
            return $q->where('datetime', '<=', request('end_date', 0));
        })->when(request('id') != '', function ($q) {
            return $q->where('id', 'LIKE', request('id').'%');
        })->when(request('m_ref') != '', function ($q) {
            return $q->where('m_ref', 'LIKE', request('m_ref').'%');
        })->where($filters)->orderBy('id','desc')->Paginate(100);

        return view('livewire.transactions')->with($data);
    }
}
