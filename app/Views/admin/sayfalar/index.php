<?php
use App\Core\Csrf;

$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);

$q = htmlspecialchars($_GET['q'] ?? ($q ?? ''), ENT_QUOTES, 'UTF-8');
$p = max(1, (int)($_GET['p'] ?? ($p ?? 1)));
$BASE = rtrim(BASE_URL, '/');

// Çöp modu mu?
$copMod = (($mod ?? '') === 'cop')
       || (strpos(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/sayfalar/cop') !== false);
?>
<div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
  <h1 class="h4 m-0">Sayfalar</h1>
  <div class="d-flex align-items-center gap-2 ms-auto">
  <?php
    $BASE = defined('BASE_URL') ? BASE_URL : '';
    $action      = $BASE . '/admin/sayfalar';
    $placeholder = 'Ara: başlık veya slug';
    $listUrl     = $BASE . '/admin/sayfalar';
    $trashUrl    = $BASE . '/admin/sayfalar/cop';
    $newUrl      = $BASE . '/admin/sayfalar/olustur';
    $newLabel    = 'Yeni Sayfa';
    require dirname(__DIR__) . '/partials/top_bar.php';
  ?>
  </div>
</div>

<?php if ($mesaj): ?><div class="alert alert-success py-2"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
<?php if ($hata):  ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($hata)  ?></div><?php endif; ?>

<!-- TEK FORM: sil/geri al/kalıcı sil (tekil + toplu) -->
<form id="sayfaForm" action="<?= $BASE ?>/admin/sayfalar/sil" method="post">
  <?= Csrf::input(); ?>
  <input type="hidden" name="id" id="hiddenSingleId" value="">
  <input type="hidden" id="csrf" name="csrf" value="<?= \App\Core\Csrf::token() ?>">
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="secTum"></th>
          <th style="width:72px">#</th>
          <th>Başlık</th>
          <th>Slug</th>
          <th>SEO</th>
          <th>Özet</th>
          <th style="width:110px">Durum</th>
          <th style="width:160px">Tarih</th>
          <th class="text-end" style="width:240px">İşlemler</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($sayfalar ?? []) as $s): ?>
        <?php
          $id     = (int)($s['id'] ?? 0);
          $baslik = htmlspecialchars($s['baslik_goster'] ?? $s['baslik'] ?? '', ENT_QUOTES, 'UTF-8');
          $slug   = htmlspecialchars($s['slug_goster']   ?? $s['slug']   ?? '', ENT_QUOTES, 'UTF-8');

          // Özet: HTML temizle + 160 karakterde kırp + title'a tam metni koy
          $__ozetTam  = (string)($s['ozet_goster'] ?? $s['ozet'] ?? '');
          $__ozetKisa = mb_strimwidth(strip_tags($__ozetTam), 0, 40, '…', 'UTF-8');
          $__ozetHTML = htmlspecialchars($__ozetKisa, ENT_QUOTES, 'UTF-8');
          $__ozetTTL  = htmlspecialchars(trim($__ozetTam), ENT_QUOTES, 'UTF-8');

          // 1 = yayında, 0 = taslak varsayımı + 'aktif' desteği
          $durumHam = $s['durum'] ?? null;
          $yayinda  = in_array((string)$durumHam, ['yayinda','yayında','aktif','1'], true) || $durumHam === 1;
        ?>
        <tr data-id="<?= $id ?>">
          <td><input type="checkbox" name="ids[]" value="<?= $id ?>" class="secKutusu chk sec-kayit"></td>
          <td><?= $id ?></td>
          <td><?= $baslik ?></td>
          <td><code><?= $slug ?></code></td>

          <?php
          // --- SEO rozetini hesapla (çeviri öncelikli) ---
          $autoTitle = ($s['baslik_goster'] ?? $s['baslik'] ?? '') !== '' ? rtrim(mb_strimwidth(($s['baslik_goster'] ?? $s['baslik']), 0, 60, '…', 'UTF-8')): '';
          $kaynak    = ($s['ozet_goster']   ?? $s['ozet']   ?? '') !== '' ? ($s['ozet_goster'] ?? $s['ozet']) : strip_tags((string)($s['icerik'] ?? ''));
          $autoDesc  = $kaynak !== '' ? rtrim(mb_strimwidth($kaynak, 0, 160, '…', 'UTF-8')) : '';

          $mb = trim((string)($s['meta_baslik_goster']   ?? $s['meta_baslik']   ?? ''));
          $ma = trim((string)($s['meta_aciklama_goster'] ?? $s['meta_aciklama'] ?? ''));

          $hasTitle = $mb !== '';
          $hasDesc  = $ma !== '';

          // Karşılaştırmalar
          $isAutoTitle = $hasTitle && ($mb === $autoTitle);
          $isAutoDesc  = $hasDesc  && ($ma === $autoDesc);

          $seoBadge = 'Otomatik';
          $seoClass = 'badge bg-secondary';
          $seoTitle = "Başlık: ".mb_strlen($mb, 'UTF-8')."c · Açıklama: ".mb_strlen($ma, 'UTF-8')."c";

          if ($hasTitle || $hasDesc) {
              if (!$isAutoTitle && !$isAutoDesc) {
                  $seoBadge = 'Özel';
                  $seoClass = 'badge bg-success';
              } elseif (($isAutoTitle && !$isAutoDesc) || (!$isAutoTitle && $isAutoDesc)) {
                  $seoBadge = 'Karışık';
                  $seoClass = 'badge bg-info text-dark';
              }
          }
          // kalite uyarısı
          $warn = '';
          $lenT = mb_strlen($mb, 'UTF-8');
          $lenD = mb_strlen($ma, 'UTF-8');
          if ($lenD > 160 || $lenT > 60) { $warn = ' ⚠︎'; }
          elseif ($lenD < 30 && ($lenD > 0 || $lenT > 0)) { $warn = ' ⓘ'; }
          $seoBadge .= $warn;
          ?>
          <td><span class="<?= $seoClass ?>" title="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>"><?= $seoBadge ?></span></td>
          <td class="ozet-cell" title="<?= $__ozetTTL ?>"><?= $__ozetHTML ?></td>

          <td>
            <?php if (!$copMod): ?>
              <button type="button"
                      class="btn btn-sm <?= $yayinda ? 'btn-success' : 'btn-secondary' ?> js-durum-btn"
                      data-id="<?= $id ?>"
                      data-url="<?= $BASE ?>/admin/sayfalar/durum">
                <?= $yayinda ? 'Aktif' : 'Taslak' ?>
              </button>
            <?php else: ?>
              <span class="badge <?= $yayinda ? 'bg-success' : 'bg-secondary' ?>">
                <?= $yayinda ? 'Aktif' : 'Taslak' ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $ol = $s['olusturma_tarihi']  ?? $s['olusturma']  ?? null;
              $gn = $s['guncelleme_tarihi'] ?? $s['guncelleme'] ?? null;
              $goster = $s['tarih'] ?? ($gn ?: $ol);
              $fmt = function($v){ $ts = strtotime((string)$v); return $ts ? date('d.m.Y H:i', $ts) : (string)$v; };
              $title = 'Oluşturma: ' . ($ol ? $fmt($ol) : '—') . ' | Güncelleme: ' . ($gn ? $fmt($gn) : '—');
            ?>
            <span title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($goster ? $fmt($goster) : '—', ENT_QUOTES, 'UTF-8') ?>
            </span>
          </td>
          <td class="text-end">
            <?php if (!$copMod): ?>
              <a href="<?= $BASE ?>/admin/sayfalar/duzenle?id=<?= $id ?>" class="btn btn-sm btn-primary">Düzenle</a>
              <form class="d-inline" method="post" action="<?= $BASE ?>/admin/sayfalar/sil">
                <?= Csrf::input(); ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="button"
                        class="btn btn-sm btn-danger js-confirm-submit"
                        data-msg="Bu sayfa çöp kutusuna taşınacak. Onaylıyor musunuz?">
                  Sil
                </button>
              </form>
            <?php else: ?>
              <button type="button"
                      class="btn btn-sm btn-success js-trash-tekli"
                      data-url="<?= $BASE ?>/admin/sayfalar/geri-al"
                      data-id="<?= $id ?>"
                      onclick="if(!window.bootstrap){ if(!confirm('Kayıt geri alınacak, onaylıyor musunuz?')) return; 
                               var f=this.closest('form'); var i=document.createElement('input'); i.type='hidden'; i.name='id'; i.value=<?= (int)$id ?>; f.appendChild(i); f.action='<?= $BASE ?>/admin/sayfalar/geri-al'; f.submit(); }">
                Geri Al
              </button>

              <button type="button"
                      class="btn btn-sm btn-outline-danger js-trash-tekli"
                      data-url="<?= $BASE ?>/admin/sayfalar/yok-et"
                      data-id="<?= $id ?>"
                      onclick="if(!window.bootstrap){ if(!confirm('Kayıt KALICI olarak silinecek, onaylıyor musunuz?')) return;
                               var f=this.closest('form'); var i=document.createElement('input'); i.type='hidden'; i.name='id'; i.value=<?= (int)$id ?>; f.appendChild(i); f.action='<?= $BASE ?>/admin/sayfalar/yok-et'; f.submit(); }">
                Kalıcı Sil
              </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($sayfalar)): ?>
        <tr><td colspan="7" class="text-muted">Kayıt bulunamadı.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php if (!$copMod): ?>
  <?php
    $BASE          = defined('BASE_URL') ? BASE_URL : '';
    $bulkDurumUrl  = $BASE . '/admin/sayfalar/durum-toplu';
    $bulkSilUrl    = $BASE . '/admin/sayfalar/sil-toplu';
    $passiveAction = 'taslak'; // << sayfalar string durum kullanıyor
    $trashLinkUrl  = $BASE . '/admin/sayfalar/cop';
    require dirname(__DIR__) . '/partials/bulk_bar.php';
  ?>
<?php else: ?>
  <div class="d-flex gap-2 mt-2">
    <button type="button"
            class="btn btn-sm btn-outline-success js-trash"
            data-url="<?= $BASE ?>/admin/sayfalar/geri-al">
      Seçilenleri Geri Al
    </button>
    <button type="button"
            class="btn btn-sm btn-danger js-trash"
            data-url="<?= $BASE ?>/admin/sayfalar/yok-et">
      Seçilenleri Kalıcı Sil
    </button>
    <a class="btn btn-sm btn-outline-dark ms-auto" href="<?= $BASE ?>/admin/sayfalar">
      ← Listeye Dön
    </a>    
  </div>
<?php endif; ?>

</form><!-- Durum toggle gizli formları (normal modda) -->

<?php if (($sayfaSayisi ?? 1) > 1): ?>
  <nav aria-label="Sayfalama" class="mt-3">
    <ul class="pagination pagination-sm mb-0">
      <?php for ($i=1; $i<=($sayfaSayisi ?? 1); $i++): ?>
        <li class="page-item <?= $i===$p ? 'active' : '' ?>">
          <a class="page-link" href="<?= $copMod ? ($BASE.'/admin/sayfalar/cop') : ($BASE.'/admin/sayfalar') ?>?p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/onay_modal.php'; ?>

<?php if ($copMod): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
</script>
<!-- Uygulama JS -->
<script>window.BASE_URL="<?= $base ?>";</script>
<script src="<?= $base ?>/js/sayfalar.js?v=20250920"></script>
<?php endif; ?>