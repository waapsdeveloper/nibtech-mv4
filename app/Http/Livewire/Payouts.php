<?php

namespace App\Http\Livewire;
use App\Models\Transactions_model;
use App\Models\Settlement_model;
use App\Models\Transaction_status_model;
use App\Models\Transaction_type_model;
use App\Models\Api_required_fields_model;
use App\Models\Currency_model;
use App\Models\Merchant_model;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\DB;
use Session;
use Livewire\Component;
use App\Models\Balance_model;

class Payouts extends Component
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
        $id = $_GET['id'];
        $method_id = $_GET['method_id'];
        $allowed_methods = DB::table('merchant_allowed_method')->where('merchant_id',$user_id)->get();
        // $filters = array_filter($_GET);
        $current_balance = Balance_model::where('parties_id',3)->where('keeper_id',$user_id)->first();
        if(isset($current_balance)){
            $balance = $current_balance->balance;
        }
        else{
            $balance = 0;
        }

        if(isset($current_balance->currency)){
            $currency = $current_balance->currency->code;
        }
        else{
            $currency = '';
        }
        $data['balance'] = $balance;
        $data['currency_code'] = $currency;
        $data['payout'] = 1;
        $data['page'] = "payout";
        $data['Page'] = "Payout";
        $data['type'] = 2;
        $data['transaction_types'] = Transaction_type_model::get();
        $data['transaction_statuses'] = Transaction_status_model::get();
        $data['transactions'] = Transactions_model::select('id','m_ref','datetime','transaction_type','amount','charges','status','authorized_by_id')
                                                ->where('merchant_id', $user_id)
                                                ->where('transaction_type',2)
                                                ->where('merchant_allowed_method_id',$id)
                                                ->orderBy('id','desc')->Paginate(100);
        $currency = DB::table('merchant_allowed_method')->where('id',$id)->first();
        $data['api_required_fields'] = Api_required_fields_model::whereIn('payment_method_id',array(0,$method_id))
                                                                    ->where('transaction_type_id',2)
                                                                    ->whereIn('currency_id',[0,$currency->currency_id])
                                                                    ->whereIn('filled_by',array(0,3))->get();
                                                                    // dd($data['api_required_fields']);
        return view('livewire.payouts')->with($data);
    }

    public function submit(Request $request)
    {
        $trnx_type = $_POST['type'];
        $payment_type = $_POST['payment_type'];
        // dd($request->server('HTTP_ACCEPT'));
        $merchant_info = Merchant_model::where('id',session('user_id'))->first();

        $currency = DB::table('currency')->where('id',DB::table('merchant_allowed_method')->where('merchant_id',$merchant_info->id)->first()->currency_id)->first();

        if($request->type > 2 && $_POST['code'] != $merchant_info->settlement_password){
            session()->put('error', 'Incorrect Settlement Password');
            return redirect()->back();
        }
        $data = array(

			'useragent' => '',
			'browseragent' => '',
			'mid' => session('our_id'),
			'apikey' => $merchant_info->api_key,
			'server_ip' => $request->server('SERVER_ADDR'),
			'M_HTTP_HOST' => $request->server('HTTP_HOST'),
			'M_SERVER_NAME' => $request->server('SERVER_NAME'),
			'payment_type' => $payment_type,
			'transaction_type' => $trnx_type,
			'ip' => $_SERVER['REMOTE_ADDR'],
			'postback_url' => 'https://trnxpay.xyz/',
			'wid' => 9638729,
			'currency' => $currency->code,
		  );
          foreach ($_POST as $key => $value) {
            $data[$key] = $value ;
          }
          if($request->type == 2){
            $url = "https://transaction9.xyz/gateway/2.5";
          }else if($request->type == 3){
            $url = "https://transaction9.xyz/gateway/call/settlement";
          }else{
            $url = "https://transaction9.xyz/gateway/call/usdt_settlement";
          }

            $ch = curl_init( $url );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec( $ch );
            // echo $url;
            // if($request->type >= 3){
            // print_r($data);
            // echo "hello".$response;
            // die;
            // }
            curl_close( $ch );
            // echo $response;
            $resp = json_decode($response);
            if($resp->status == 'PROCESSING'){
                session()->put('success', $resp->message);
            }else{
                session()->put('error', $resp->message);
            }

            return redirect()->back();
    }

    public function settlements()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('MdrybNRSDCmuKvbk5P6mF4jUsGWGH8VfpvN27G76vTZMtLGQAK3ZNjzkGgWwNK2L5HgRPNmk');
        }
        $user_id = session('user_id');
        $id = $_GET['id'];
        $method_id = $_GET['method_id'];
        $allowed_methods = DB::table('merchant_allowed_method')->where('merchant_id',$user_id)->get();
        // $filters = array_filter($_GET);
        $current_balance = Balance_model::where('parties_id',3)->where('keeper_id',$user_id)->first();
        if(isset($current_balance)){
            $balance = $current_balance->balance;
        }
        else{
            $balance = 0;
        }

        if(isset($current_balance->currency)){
            $currency = $current_balance->currency->code;
        }
        else{
            $currency = '';
        }
        $data['balance'] = $balance;
        $data['currency_code'] = $currency;
        $data['page'] = "settlement";
        $data['Page'] = "Settlement";
        $data['type'] = 3;
        $data['settlement'] = 1;
        $data['transaction_types'] = Transaction_type_model::get();
        $data['transaction_statuses'] = Transaction_status_model::get();
        $data['transactions'] = Settlement_model::select('id','datetime','amount','charges','status')
                                                ->where('give_to', 3)
                                                ->where('give_to_id', $user_id)
                                                ->where('settlement_type',1)
                                                ->orderBy('id','desc')->Paginate(100);
        $currency = DB::table('merchant_allowed_method')->where('id',$id)->first();
        $data['api_required_fields'] = Api_required_fields_model::whereIn('payment_method_id',array(0,$method_id))
                                                                    ->where('transaction_type_id',3)
                                                                    ->whereIn('currency_id',[0,$currency->currency_id])
                                                                    ->whereIn('filled_by',array(0,3))->get();
                                                                    $data['api_required_fields'];
        return view('livewire.payouts')->with($data);
    }
    public function usdt_settlements()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('MdrybNRSDCmuKvbk5P6mF4jUsGWGH8VfpvN27G76vTZMtLGQAK3ZNjzkGgWwNK2L5HgRPNmk');
        }
        $user_id = session('user_id');
        $id = $_GET['id'];
        $method_id = $_GET['method_id'];
        $current_balance = Balance_model::where('parties_id',3)->where('keeper_id',$user_id)->first();
        if(isset($current_balance)){
            $balance = $current_balance->balance;
        }
        else{
            $balance = 0;
        }

        if(isset($current_balance->currency)){
            $currency = $current_balance->currency->code;
        }
        else{
            $currency = '';
        }
        $data['balance'] = $balance;
        $data['currency_code'] = $currency;
        $allowed_methods = DB::table('merchant_allowed_method')->where('merchant_id',$user_id)->get();
        // $filters = array_filter($_GET);
        $data['usdt'] = 1;
        $data['page'] = "usdt settlement";
        $data['Page'] = "USDT Settlement";
        $data['type'] = 4;
        $data['transaction_types'] = Transaction_type_model::get();
        $data['transaction_statuses'] = Transaction_status_model::get();
        $data['transactions'] = Transactions_model::select('id','m_ref','datetime','transaction_type','amount','charges','status','authorized_by_id')
                                                ->where('merchant_id', $user_id)
                                                ->where('transaction_type',4)
                                                ->where('merchant_allowed_method_id',$id)
                                                ->orderBy('id','desc')->Paginate(100);
        $currency = DB::table('merchant_allowed_method')->where('id',$id)->first();
        $data['api_required_fields'] = Api_required_fields_model::whereIn('payment_method_id',array(0,$method_id))
                                                                    ->where('transaction_type_id',4)
                                                                    ->whereIn('currency_id',[0,$currency->currency_id])
                                                                    ->whereIn('filled_by',array(0,3))->get();
                                                                    // dd($data['api_required_fields']);
        return view('livewire.payouts')->with($data);
    }
}
