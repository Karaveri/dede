<?php /** @var array $kolonlar, $satirlar, $eylemler, $toplu, $sayfalama, $csrf, $baslik */ ?>
<?php $this->insert('admin/parcalar/liste_ust', compact('baslik', 'toplu')); ?>

<form id="<?= htmlspecialchars($toplu['form_id'] ?? 'listeForm') ?>" method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="secTum"></th>
          <?php foreach ($kolonlar as $k): ?>
            <th><?= htmlspecialchars($k['baslik']) ?></th>
          <?php endforeach; ?>
          <th class="text-end" style="width:240px">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($satirlar as $s): $id=(int)$s['id']; ?>
          <tr>
            <td><input type="checkbox" class="chk" value="<?= $id ?>"></td>
            <?php foreach ($kolonlar as $k): ?>
              <td><?= $this->e($s[$k['alan']] ?? '') ?></td>
            <?php endforeach; ?>
            <td class="text-end">
              <?php foreach ($eylemler as $btn): ?>
                <?php if ($btn['tip']==='durum'): ?>
                  <button type="button"
                          class="btn btn-sm <?= $s['aktif'] ? 'btn-success':'btn-secondary' ?> js-durum-btn"
                          data-id="<?= $id ?>"
                          data-url="<?= $this->e($btn['url']) ?>">
                    <?= $s['aktif'] ? 'Aktif' : ($s['durum_yazi'] ?? 'Pasif') ?>
                  </button>
                <?php elseif ($btn['tip']==='duzenle'): ?>
                  <a class="btn btn-sm btn-primary" href="<?= $this->e($btn['url']).'/'.$id.'/duzenle' ?>">Düzenle</a>
                <?php elseif ($btn['tip']==='sil'): ?>
                  <a class="btn btn-sm btn-outline-danger" href="<?= $this->e($btn['url']).'/'.$id.'/sil' ?>">Sil</a>
                <?php endif; ?>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $this->insert('admin/parcalar/toplu_islemler', compact('toplu', 'csrf')); ?>
</form>

<?php $this->insert('admin/parcalar/sayfalama', compact('sayfalama')); ?>
