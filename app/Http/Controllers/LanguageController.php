<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Language_keys_model;
use App\Models\Language_model;
use App\Models\Translations_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\File;

class LanguageController extends Controller
{
    //
    public function swap($locale)
    {
        session()->forget('locale');
        // available language in template array
        $availLocale = ['us' => 'us', 'cn' => 'cn', 'th' => 'th', 'my' => 'my'];
        // check for existing language
        if (array_key_exists($locale, $availLocale)) {

            session()->put('locale', $locale);

        }

        return redirect()->back();
    }


}
