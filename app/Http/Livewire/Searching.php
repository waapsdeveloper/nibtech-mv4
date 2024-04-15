<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Transactions_model;
use App\Models\Charges_model;
use App\Models\Balance_log_model;
use App\Models\Balance_model;
use Symfony\Component\HttpFoundation\Request;

class Searching extends Component
{
    public function render(Request $request)
    {
        $user_id = session('user_id');
        $merchant_details = DB::table('merchant')->where('id',$user_id)->first();
        $current_balance = DB::table('balance')->where('parties_id',3)->where('keeper_id',$user_id)->where('currency_id',27)->first();
        // print_r($current_balance);
        // die;
        $start_date= $request['start']." 00:00:00";
        $end_date = $request['end']." 23:59:59";
        // echo $start_date;
        // die;
        $total_payments = Transactions_model::where('transaction_type',1)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $failed_payments = Transactions_model::where('transaction_type',1)->where('status',2)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $payments = Transactions_model::where('transaction_type',1)->where('status',1)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $mdr_in = 0;
        $in_charges= 0;
        foreach ($payments as $payment) {
            $mdr_in += $payment->amount;
            $in_charges += $payment->charges;
        }
        $rolling_reserve = 0;
        $all_reserve_charges = Charges_model::select('id')->where(['take_from'=>3,'take_from_id'=>$user_id,'transaction_charges_id'=>7])->get();
        $all_charges = [];
        foreach($all_reserve_charges as $charge){
            $all_charges[] = $charge->id;
        }
        $merchant_balance = Balance_model::select('id')->where(['parties_id'=>3,'keeper_id'=>$user_id,'currency_id'=>27,'type'=>1])->first();
        $all_logs = Balance_log_model::select('amount')->where('balance_id',$merchant_balance->id)->whereIn('charges_id',$all_charges)->get();
        foreach($all_logs as $log){
            $rolling_reserve += $all_logs->amount;
        }

        $total_payouts = Transactions_model::where('transaction_type',2)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $failed_payouts = Transactions_model::where('transaction_type',2)->where('status',2)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $payouts = Transactions_model::where('transaction_type',2)->where('status',1)->where('merchant_id',$user_id)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $mdr_out = 0;
        $out_charges= 0;
        foreach ($payouts as $payout) {
            $mdr_out += $payout->amount;
            $out_charges += $payout->charges;
        }
        $settlements = DB::table('settlements')->where('give_to',3)->where('give_to_id',$user_id)->whereIn('settlement_type',array(1,2))->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $settle = 0 ;
        $settle_charges = 0 ;
        foreach ($settlements as $settlement ) {
           $settle += $settlement->amount;
           $settle_charges += $settlement->charges;
        }
        $other_charges = DB::table('settlements')->where('give_to',3)->where('give_to_id',$user_id)->where('settlement_type',4)->where('datetime','>=',$start_date)->where('datetime','<=',$end_date)->get();
        $charges = 0 ;
        foreach ($other_charges as $other_charge ) {
           $charges += $other_charge->charges;
        }
        $data= array(
        'mdr_in'=>$mdr_in,
        'in_charges'=>$in_charges,
        'mdr_out'=>$mdr_out,
        'out_charges'=>$out_charges,
        'rolling_reserve'=>$rolling_reserve,
        'total_payments'=>$total_payments->count(),
        'failed_payments'=>$failed_payments->count(),
        'approved_payments'=>$payments->count(),
        'total_payouts'=>$total_payouts->count(),
        'failed_payouts'=>$failed_payouts->count(),
        'approved_payouts'=>$payouts->count(),
        'merchant_name' => $merchant_details->firstname." ".$merchant_details->lastname,
        'current_balance'=> $current_balance->balance,
        'settlement' => $settle,
        'settlement_charges' => $settle_charges,
        'other_charges' => $charges,
        );
        return view('livewire.index')->with($data);
    }
}
