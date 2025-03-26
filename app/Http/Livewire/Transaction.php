<?php

namespace App\Http\Livewire;

use App\Exports\RepairersheetExport;
use App\Models\Account_transaction_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class Transaction extends Component
{

    public function mount()
    {
    }
    public function render()
    {

        $data['title_page'] = "Transactions";
        session()->put('page_title', $data['title_page']);

        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->pluck('company','id');
        $data['currencies'] = Currency_model::all();

        $per_page = request('per_page') ?? '20';

        $transactions = Account_transaction_model::when(request('start_date') != '', function ($q) {
            return $q->whereDate('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->whereDate('created_at', '<=', request('end_date') . ' 23:59:59');
        })
        ->orderBy('id','desc')
        ->paginate($per_page);
        $data['transactions'] = $transactions;

        return view('livewire.transaction')->with($data);
    }

    public function update_transaction($id)
    {
        $transaction = Account_transaction_model::find($id);
        $transaction->date = request('date');
        $transaction->description = request('description');
        $transaction->amount = request('amount');
        $transaction->currency = request('currency');
        $transaction->exchange_rate = request('exchange_rate');
        $transaction->save();

        session()->put('success',"Transaction has been updated successfully");
        return redirect()->back();


    }

    public function add_transaction_sheet()
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $issue = [];
        request()->validate([
            'sheet' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        // Store the uploaded file in a temporary location
        $filePath = request()->file('sheet')->store('temp');

        // // Perform operations on the Excel file
        // $spreadsheet = IOFactory::load(storage_path('app/'.$filePath));
        // // Perform your operations here...

        // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        $excelFilePath = storage_path('app/'.$filePath);

        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        // print_r($dh);
        unset($data[0]);
        $arrayLower = array_map('strtolower', $dh);
        // Search for the lowercase version of the search value in the lowercase array
        $order_id = array_search('order_id', $arrayLower);
        if(!in_array('order_id', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(order_id)");
            return redirect()->back();
            // die;
        }
        $value_date = array_search('value_date', $arrayLower);
        if(!in_array('value_date', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(value_date)");
            return redirect()->back();
            // die;
        }
        $invoice_key = array_search('invoice_key', $arrayLower);
        if(!in_array('invoice_key', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(invoice_key)". $invoice_key);
            return redirect()->back();
            // die;
        }
        $amoun = array_search('amount', $arrayLower);
        if(!in_array('amount', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(amount)");
            return redirect()->back();
            // die;
        }
        $currenc = array_search('currency', $arrayLower);
        if(!in_array('currency', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(currency)");
            return redirect()->back();
            // die;
        }
        $designation = array_search('designation', $arrayLower);
        if(!in_array('designation', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(designation)");
            return redirect()->back();
            // die;
        }

        // dd($dh);
        foreach($data as $dr => $d) {
            $order = Order_model::where('reference_id',trim($d[$order_id]))->where('order_type_id',3)->first();
            if($order == null && $d[$order_id] != '' && $d[$order_id] != 'None'){
                $or = new Order();
                $or->recheck(trim($d[$order_id]));
                $order = Order_model::where('reference_id',trim($d[$order_id]))->where('order_type_id',3)->first();
            }elseif($order == null && $d[$order_id] == 'None' && str_contains($d[$designation],'DELIVERY - DHL Express')){
                $tracking = str_replace('DELIVERY - DHL Express - ','',$d[$designation]);
                $order = Order_model::where('tracking_number',$tracking)->where('order_type_id',3)->first();
            }elseif($order == null && $d[$order_id] == '' && str_contains($d[$designation],'avoir_commission_order_id')){
                $or = str_replace('avoir_commission_order_id','',$d[$designation]);
                $order = Order_model::where('reference_id',trim($or))->where('order_type_id',3)->first();
            }
            if($order){

                $amount = str_replace(',','',$d[$amoun]);
                $currency = Currency_model::where('code',$d[$currenc])->first();

                $transaction = Account_transaction_model::firstOrNew([
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'order_type_id' => 3,
                    'amount' => $amount,
                    'currency' => $currency->id,
                    'date' => Carbon::parse($d[$value_date])->format('Y-m-d H:i:s'),
                    'description' => $d[$invoice_key],
                ]);
                if($transaction->id){
                    continue;
                }
                $transaction->customer_id = $order->customer_id;
                $transaction->order_id = $order->id;
                $transaction->order_type_id = 3;
                if($amount < 0){
                    $transaction->transaction_type_id = 2;
                }else{
                    $transaction->transaction_type_id = 1;
                }
                $transaction->date = Carbon::parse($d[$value_date])->format('Y-m-d H:i:s');
                $transaction->description = $d[$invoice_key];
                $transaction->amount = $amount;
                $transaction->currency = $currency->id;
                $transaction->created_by = session('user_id');

                $transaction->save();

            }else{
                $issue[] = $d;
            }
            print_r($d);
        }

        if(count($issue) > 0){
            session()->put('error', json_encode($issue));
        }
        return redirect()->back();

    }
    public function add_payment()
    {
        // dd(request()->all());
        $latest_transaction_reference = Account_transaction_model::orderBy('reference_id','desc')->first();
        $transaction = new Account_transaction_model();
        $transaction->reference_id = $latest_transaction_reference->reference_id + 1;
        $transaction->customer_id = request('customer_id');
        $transaction->transaction_type_id = request('type');
        $transaction->payment_method_id = request('payment_method');
        $transaction->date = request('date');
        $transaction->description = request('description');
        $transaction->amount = request('amount');
        $transaction->currency = request('currency');
        $transaction->exchange_rate = request('exchange_rate');
        $transaction->parent_id = request('transaction_id');
        $transaction->status = 1;
        $transaction->created_by = session('user_id');
        $transaction->save();

        $parent_transaction = Account_transaction_model::find(request('transaction_id'));
        if($parent_transaction->amount <= $parent_transaction->children->sum('amount')){
            $parent_transaction->status = 3;
        }
        $parent_transaction->save();

        session()->put('success',"Payment has been added successfully");
        return redirect()->back();
    }

    public function add_customer()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-customer')->with($data);
    }
    public function delete_transaction($id){
        Account_transaction_model::find($id)->delete();
        session()->put('success',"Transaction has been deleted successfully");
        return redirect()->back();
    }
    public function export_pending_repairs($customer_id)
    {
        $customer = Customer_model::find($customer_id);

        return Excel::download(new RepairersheetExport, 'pending_repairs_'.$customer->company.'.xlsx');
    }

    public function export_reports($customer_id)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $start_date = request('start_date') ?? date('Y-m-d', strtotime('-1 month'));
        $end_date = request('end_date') ?? date('Y-m-d');
        $start = $start_date . ' 00:00:00';
        $end = $end_date . ' 23:59:59';

        if (request('start_date') != '') {

            $balance_bf = Account_transaction_model::where('customer_id', $customer_id)
                ->where('date', '<', $start)
                ->selectRaw('SUM(CASE WHEN transaction_type_id = 1 THEN amount ELSE 0 END) as total_debit, SUM(CASE WHEN transaction_type_id = 2 THEN amount ELSE 0 END) as total_credit')
                ->first();

            $balance_bf = $balance_bf->total_debit - $balance_bf->total_credit;
        } else {
            $balance_bf = 0;
        }

        // Fetch data
        $transactions = Account_transaction_model::where('customer_id', $customer_id)
            // ->whereBetween('date', [$start, $end])
            ->when(request('start_date') != '', function ($q) {
                return $q->whereDate('date', '>=', request('start_date'));
            })
            ->when(request('end_date') != '', function ($q) {
                return $q->whereDate('date', '<=', request('end_date') . ' 23:59:59');
            })
            ->orderBy('date', 'asc')
            ->get();
        $customer = Customer_model::find($customer_id);

        if (!$customer) {
            abort(404, "Customer not found");
        }

        $data = [
            'customer' => $customer,
            'transactions' => $transactions,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'balance_bf' => $balance_bf,

        ];

        // Initialize TCPDF
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetFont('freeserif', '', 12);
        $pdf->AddPage();
        // $pdf->setCellPaddings(1, 1, 1, 1);
        // $pdf->setCellMargins(1, 1, 1, 1);
        $pdf->Image(asset('assets/img/backgrounds/letterhead.png'), 0, 0, 240, 25, 'png', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Generate HTML from Blade view
        if (request('statement') == 1) {
            $pdf->SetTitle('Customer Statement ' . $customer->company . ' pcs');
            $filename = 'Customer_Statement_' . str_replace(' ', '_', $customer->company) . '.pdf';
            $html = view('export.customer_statement', $data)->render();
        } else {
            $pdf->SetTitle('Customer Report');
            $filename = 'Customer_Report.pdf';
            $html = '<h1>Customer Report</h1><p>No statement requested.</p>';
        }


        // Convert HTML to PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Output PDF
        $pdf->Output($filename, 'I'); // 'I' for inline view, 'D' for download
    }

    // public function export_reports($customer_id, $invoice = null)
    // {
    //     ini_set('memory_limit', '512M');
    //     ini_set('max_execution_time', '300');


    //     // Find the order
    //     $transactions = Account_transaction_model::where('customer_id',$customer_id)->get();
    //     $customer = Customer_model::find($customer_id);
    //     // Generate PDF for the invoice content
    //     $data = [
    //         'customer' => $customer,
    //         'transactions' => $transactions,
    //     ];
    //     // Create a new TCPDF instance
    //     $pdf = new TCPDF();

    //     // Set document information
    //     $pdf->SetCreator(PDF_CREATOR);
    //     // $pdf->SetHeaderData('', 0, 'Invoice', '');

    //     // Add a page
    //     $pdf->AddPage();

    //     // Set font
    //     $pdf->SetFont('times', '', 12);

    //     // Additional content from your view
    //     if(request('statement') == 1){
    //         $pdf->SetTitle('Customer Statement '.$customer->company.' pcs');
    //         $filename = 'Customer Statement '.$customer->company.' pcs.pdf';
    //         $html = view('export.customer_statement', $data)->render();
    //     }

    //     $pdf->writeHTML($html, true, false, true, false, '');

    //     // dd($pdfContent);
    //     // Send the invoice via email
    //     // Mail::to($order->customer->email)->send(new InvoiceMail($data));

    //     // Optionally, save the PDF locally
    //     // file_put_contents('invoice.pdf', $pdfContent);

    //     // Get the PDF content
    //     $pdf->Output($filename, 'I');

    //     // $pdfContent = $pdf->Output('', 'S');
    //     // Return a response or redirect

    //     // Pass the PDF content to the view
    //     // return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent, 'delivery_note'=>$order->delivery_note_url]);
    // }



}
