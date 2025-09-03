<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use PDO;

class GirisController
{

    public function __construct()
    {
        // Tüm giriş akışında session garanti açık olsun
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }    
    // Giriş formu: kendi HTML'ini döndürür (layouta bağımlı değil)
    public function form(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        \App\Core\Auth::girisliyseYonlendir('/admin');  // girişliyse /admin'e gönder

        ob_start();
        require dirname(__DIR__) . '/Views/giris/form.php';
        return (string)ob_get_clean();
    }

    public function post(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        // CSRF
        if (!\App\Core\Csrf::check()) {
            $_SESSION['hata'] = 'Güvenlik anahtarı geçersiz ya da süresi doldu.';
            header('Location: ' . BASE_URL . '/admin/giris');
            return '';
        }

        // Form alanları
        $email = trim($_POST['email'] ?? '');
        $sifre = (string)($_POST['sifre'] ?? '');

        if ($email === '' || $sifre === '') {
            $_SESSION['hata'] = 'E-posta ve şifre zorunludur.';
            header('Location: ' . BASE_URL . '/admin/giris');
            return '';
        }

        try {
            // Girişi tek yerden yap: Auth::giris
            $ok = \App\Core\Auth::giris($email, $sifre);
        } catch (\Throwable $e) {
            $_SESSION['hata'] = 'Giriş sırasında bir hata oluştu: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/admin/giris');
            return '';
        }

        if (!$ok) {
            // Auth::giris, hem şifre hatasını hem pasif/banlı durumlarını false döndürür
            $_SESSION['hata'] = 'E-posta veya şifre hatalı ya da hesabınız pasif/engelli.';
            header('Location: ' . BASE_URL . '/admin/giris');
            return '';
        }

        // Başarılı giriş
        session_regenerate_id(true);
        $_SESSION['mesaj'] = 'Hoş geldiniz ' . (\App\Core\Auth::rol() ? 'Yonetici' : '');

        header('Location: ' . BASE_URL . '/admin');
        return '';
    }

    public function cikis(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/admin/giris');
        return '';
    }
}
