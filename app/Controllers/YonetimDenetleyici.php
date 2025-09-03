<?php
namespace App\Controllers;

use App\Core\Auth;

class YonetimDenetleyici extends AdminController
{
    protected array $roller = ['admin','editor','yazar'];

    public function __construct()
    {
        parent::__construct(); // Giriş + rol kontrolü
    }

    // Not: 'ana' metodunu AdminController'dan miras alıyoruz.
    // İstersen burada override etmeden direkt kullanabiliriz.
}
