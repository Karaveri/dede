<?php
// Controller iki farklı isimle veri gönderiyor:
// - normal liste: $kategoriler
// - ağaç görünüm : $tumKategoriler (g='agac')
$ustMap = $ustMap ?? [];   // görünüm bu değişkenle geldi; gelmezse boş dizi olsun
$g    = $g    ?? '';
$rows = isset($kategoriler) && is_array($kategoriler) ? $kategoriler
      : (isset($tumKategoriler) && is_array($tumKategoriler) ? $tumKategoriler : []);

$csrf = \App\Core\Csrf::token();
$BASE = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="">
    <div class="d-flex justify-content-between align-items-center w-10 mb-3">
      <h1 class="h4 m-0"><?= htmlspecialchars($baslik ?? 'Kategoriler') ?></h1>

        <?php
            $BASE = defined('BASE_URL') ? BASE_URL : '';
            $action      = $BASE . '/admin/kategoriler';
            $placeholder = 'Ara: ad veya slug';
            $listUrl     = $BASE . '/admin/kategoriler';
            $trashUrl    = $BASE . '/admin/kategoriler/cop';
            $newUrl      = $BASE . '/admin/kategoriler/olustur';
            $newLabel    = 'Yeni Kategori';
            require dirname(__DIR__) . '/partials/top_bar.php';
        ?>

    </div>

    <?php if (!empty($_SESSION['mesaj'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mesaj']) ?></div>
        <?php unset($_SESSION['mesaj']); endif; ?>
    <?php if (!empty($_SESSION['hata'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['hata']) ?></div>
        <?php unset($_SESSION['hata']); endif; ?>

    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead>
            <tr>
                <th style="width:36px"><input type="checkbox" id="secTum"></th>
                <th style="width:70px">ID</th>
                <th>Ad</th>
                <th>Slug</th>
                <th style="width:100px">Üst Kat</th>
                <th style="width:100px">Durum</th>
                <th style="width:160px">Tarih</th>
                <th style="width:180px">İşlem</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-id="<?= (int)$r['id'] ?>">
                    <td><input type="checkbox" class="sec-kayit" value="<?= (int)$r['id'] ?>"></td>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['ad'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['slug'] ?? '') ?></td>

                    <td>
                        <?php
                          if (!empty($r['ust_ad'])) {
                              echo htmlspecialchars($r['ust_ad'], ENT_QUOTES, 'UTF-8');
                          } else {
                              // Fallback: parent_id üzerinden harita
                              $pid = isset($r['parent_id']) ? (int)$r['parent_id']
                                   : (isset($r['ust_id']) ? (int)$r['ust_id']
                                   : (isset($r['parent']) ? (int)$r['parent'] : 0));
                              echo $pid > 0
                                ? htmlspecialchars($ustMap[$pid] ?? ('#'.$pid), ENT_QUOTES, 'UTF-8')
                                : '—';
                          }
                        ?>
                    </td>

                    <td>
                    <?php $aktif = (int)($r['durum'] ?? 0) === 1; ?>
                    <button type="button"
                            class="btn btn-sm <?= $aktif ? 'btn-success' : 'btn-secondary' ?> js-durum-btn"
                            data-url="<?= $BASE ?>/admin/kategoriler/durum-tekil"
                            data-id="<?= (int)$r['id'] ?>"
                            data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                      <?= $aktif ? 'Aktif' : 'Pasif' ?>
                    </button>
                    </td>

                    <td>
                      <?php
                        $ol = $r['olusturma_tarihi']  ?? null;
                        $gn = $r['guncelleme_tarihi'] ?? null;
                        // Görünürde önce güncelleme, yoksa oluşturma:
                        $goster = $r['tarih'] ?? ($gn ?: $ol);
                        $fmt = function($v){ $ts = strtotime((string)$v); return $ts ? date('d.m.Y H:i', $ts) : (string)$v; };
                        $title = 'Oluşturma: ' . ($ol ? $fmt($ol) : '—') . ' | Güncelleme: ' . ($gn ? $fmt($gn) : '—');
                      ?>
                      <span title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($fmt($goster) ?: '—', ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>

                    <td>
                      <?php if (($mod ?? '') === 'cop'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary js-trash-tekli"
                                data-url="<?= $BASE ?>/admin/kategoriler/cop/geri-al"
                                data-id="<?= (int)$r['id'] ?>"
                                data-csrf="<?= htmlspecialchars($csrf ?? \App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                          Geri Al
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger js-trash-tekli"
                                data-url="<?= $BASE ?>/admin/kategoriler/cop/kalici-sil"
                                data-id="<?= (int)$r['id'] ?>"
                                data-csrf="<?= htmlspecialchars($csrf ?? \App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                          Kalıcı Sil
                        </button>
                      <?php else: ?>
                        <a class="btn btn-sm btn-primary"
                           href="<?= $BASE ?>/admin/kategoriler/duzenle?id=<?= (int)$r['id'] ?>">Düzenle</a>
                      <form class="d-inline" method="post" action="<?= $BASE ?>/admin/kategoriler/sil">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id"   value="<?= (int)$r['id'] ?>">
                        <button type="button"
                                class="btn btn-sm btn-danger js-confirm-submit"
                                data-msg="Bu kategori çöp kutusuna taşınacak. Onaylıyor musunuz?">
                          Sil
                        </button>
                      </form>      
                      <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div> <!-- tablo responsive kapanışı -->

    <?php if (($mod ?? '') === 'cop' && !empty($sayfaSayisi) && $sayfaSayisi > 1): ?>
      <nav class="mt-2">
        <ul class="pagination pagination-sm mb-0">
          <?php
            $cp = max(1, (int)($p ?? 1));
            $ss = (int)$sayfaSayisi;
            $BASE = defined('BASE_URL') ? BASE_URL : '';
            $qstr = $q !== '' ? '&q='.urlencode($q) : '';
            for ($i=1; $i <= $ss; $i++):
              $active = $i === $cp ? ' active' : '';
          ?>
            <li class="page-item<?= $active ?>">
              <a class="page-link" href="<?= $BASE ?>/admin/kategoriler/cop?p=<?= $i . $qstr ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

<?php
$BASE = defined('BASE_URL') ? BASE_URL : '';
$mod  = $mod ?? ''; // 'cop' geliyorsa çöp modu
$csrfVal = htmlspecialchars($csrf ?? \App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8');
?>

<input type="hidden" id="csrf" value="<?= $csrfVal ?>">

<?php if ($mod !== 'cop'): ?>
    <?php
    $BASE          = defined('BASE_URL') ? BASE_URL : '';
    $bulkDurumUrl  = $BASE . '/admin/kategoriler/durum-toplu';
    $bulkSilUrl    = $BASE . '/admin/kategoriler/sil-toplu';
    $passiveAction = 'pasif'; // << kategoriler 0/1 mantığı
    $trashLinkUrl  = $BASE . '/admin/kategoriler/cop';
    require dirname(__DIR__) . '/partials/bulk_bar.php';
    ?>  
<?php else: ?>
  <!-- ÇÖP MODU: sadece Geri Al / Kalıcı Sil ve Listeye Dön -->
  <div class="d-flex gap-2 mt-2">
    <button type="button"
            class="btn btn-outline-primary btn-sm js-trash"
            data-url="<?= $BASE ?>/admin/kategoriler/cop/geri-al"
            data-aksiyon="geri-al">
      Seçilenleri Geri Al
    </button>
    <button type="button"
            class="btn btn-outline-danger btn-sm js-trash"
            data-url="<?= $BASE ?>/admin/kategoriler/cop/kalici-sil"
            data-aksiyon="kalici">
      Seçilenleri Kalıcı Sil
    </button>
    <span class="btn btn-outline-primary btn-sm js-trash" id="secimSayaci">0 seçili</span>
    <a class="btn btn-sm btn-outline-dark ms-auto" href="<?= $BASE ?>/admin/kategoriler">
      ← Listeye Dön
    </a>
  </div>

<?php endif; ?>

</div>