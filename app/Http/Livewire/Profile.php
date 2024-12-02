<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Admin_model;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class Profile extends Component
{

    public $first_name;
    public $last_name;
    public $email;
    public $two_factor_enabled;
    public $qrCodeUrl;

    public function mount()
    {
        $admin = Admin_model::find(session('user_id'));

        if (!$admin) {
            return redirect()->route('login');
        }

        // Initialize properties
        $this->first_name = $admin->first_name;
        $this->last_name = $admin->last_name;
        $this->email = $admin->email;
        $this->two_factor_enabled = $admin->google2fa_secret ? true : false;
    }

    public function render()
    {
        $admin = Admin_model::find(session('user_id'));
        return view('livewire.profile')->with('admin', $admin);
    }

    public function updateProfile()
    {
        $admin = Admin_model::find(session('user_id'));

        // Validate inputs
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $admin->id,
        ]);

        // Update the admin profile
        $admin->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
        ]);

        session()->flash('success', __('Profile updated successfully.'));
    }
    public function enable2FA()
    {
        $admin = Admin_model::find(session('user_id'));
        $google2fa = new Google2FA();

        // Generate a new secret key
        $secret = $google2fa->generateSecretKey();

        // Save the secret key
        $admin->google2fa_secret = $secret;
        $admin->is_2fa_enabled = 1;
        $admin->save();

        // Generate the QR Code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'), // Your application name
            $admin->email,      // Admin email
            $secret             // Secret key
        );

        // Generate QR Code
        $qrCodeImage = QrCode::size(200)->generate($qrCodeUrl);

        return view('livewire.profile', [
            'admin' => $admin,
            'qrCodeImage' => $qrCodeImage,
            'secret' => $secret,
        ]);
    }



    public function disable2FA()
    {
        $admin = Admin_model::find(session('user_id'));

        // Remove the secret
        $admin->google2fa_secret = null;
        $admin->is_2fa_enabled = 0;
        $admin->save();

        $this->qrCodeUrl = null;
        $this->two_factor_enabled = false;

        session()->flash('success', 'Two-factor authentication disabled.');
        return redirect('profile');
    }
//     public function mount(Request $request)
//     {
//         // dd(session()->all());
//         $user_id = session('user_id');
//         if($user_id == NULL){
//             return redirect()->route('login');
//         }
//     }
//     public function render()
//     {

//         $data['title_page'] = "Profile";
//         $admin = Admin_model::where('id',session('user_id'))->first();
//         if(request('update_profile') && $admin->id != 1){
//             $admin->first_name = request('first_name');
//             $admin->last_name = request('last_name');
//             $admin->email = request('email');
//         }
//         $data = array(
//             'admin' => $admin,
//         );
//         return view('livewire.profile')->with($data);
//     }

//     public function enable2FA(Request $request)
//     {
//         $admin = Admin_model::where('id',session('user_id'))->first();
//         $google2fa = new Google2FA();

//         // Generate a new secret
//         $secret = $google2fa->generateSecretKey();

//         // Save the secret to the admin record
//         $admin->google2fa_secret = $secret;
//         $admin->save();

//         // Generate QR Code URL
//         $qrCodeUrl = $google2fa->getQRCodeInline(
//             'Your App Name',
//             $admin->email,
//             $secret
//         );

//         return view('admin.2fa_setup', compact('qrCodeUrl'));
//     }

//     public function disable2FA(Request $request)
//     {
//         $admin = Admin_model::where('id',session('user_id'))->first();
//         $admin->google2fa_secret = null;
//         $admin->save();

//         return back();
//     }
}
