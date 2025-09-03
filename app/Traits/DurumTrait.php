<?php
namespace App\Traits;

use App\Core\Csrf;

trait DurumTrait {
    public function durumTekil(): void {
        header('Content-Type: application/json; charset=UTF-8');
        Csrf::dogrula($_POST['csrf'] ?? '');
        $id    = (int)($_POST['id'] ?? 0);
        $deger = $_POST['deger'] ?? null; // 'aktif'|'taslak'|1|0
        $yeni  = $this->model->durumDegistir($id, $deger);
        echo json_encode(['ok'=>true, 'durum'=>$yeni, 'ids'=>[$id]]);
    }

    public function durumToplu(): void {
        header('Content-Type: application/json; charset=UTF-8');
        Csrf::dogrula($_POST['csrf'] ?? '');
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $deger = $_POST['durum'] ?? null;
        $yeni  = $this->model->topluDurum($ids, $deger);
        echo json_encode(['ok'=>true, 'durum'=>$yeni, 'ids'=>$ids]);
    }
}
