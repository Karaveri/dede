<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

abstract class AdminController extends Controller
{
    public function __construct()
    {
        \App\Core\Auth::zorunlu();

        // Alt denetleyici isterse $roller özelliğini override edebilir.
        $roller = property_exists($this, 'roller') ? $this->roller : ['admin','editor','yazar'];
        \App\Core\Auth::zorunluRol($roller);
    }

    public function ana(): string
    {
        return $this->view('admin/ana', [
            'baslik'       => 'Yönetim Paneli',
            'kullanici_rol'=> Auth::rol(),
        ]);
    }
}
