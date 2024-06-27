<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Color_model;
use Livewire\Component;
use App\Models\Stock_model;
use App\Models\Grade_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use Barryvdh\DomPDF\Facade as PDF; // Import the PDF facade
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Carbon\Carbon;
use TCPDF;

class FortnightReturn extends Component
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

        $data['title_page'] = "Fortnight Return";

        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::all();

        $latest_items = Order_item_model::whereHas('variation', function ($q) {
            $q->where('grade',10);
        })
        ->whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })->whereHas('refund_order')->orderBy('created_at','desc')
        ->get();

        $data['latest_items'] = $latest_items;

        if(request('print') == 1){


        }
        return view('livewire.fortnight_return', $data); // Return the Blade view instance with data
    }

    public function print(){
        $latest_items = Order_item_model::whereHas('variation', function ($q) {
            $q->where('grade',10);
        })
        ->whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })->whereHas('refund_order')->orderBy('created_at','desc')
        ->get();

        $data['latest_items'] = $latest_items;

        $pdf = FacadePdf::loadView('export.fortnight_return', compact('latest_items'));
        return $pdf->download('fortnight_returns.pdf');
        $pdf = new TCPDF();

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->AddPage('L');
        $pdf->SetFont('times', '', 12);
        $html = view('export.fortnight_return', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdfContent = $pdf->Output('', 'S');
        return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent]);

    }



}
