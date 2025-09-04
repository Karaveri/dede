document.addEventListener('DOMContentLoaded', () => {
  // Baz URL (layout'a meta koyduysak ordan, yoksa /fevzi/public varsayılan)
  const BASE = document.querySelector('meta[name="base-url"]')?.content || '/fevzi/public';
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || document.getElementById('csrf')?.value || '';  
  // Sayfada icerik alanı yoksa ya da TinyMCE yüklenmediyse çık
  const textarea = document.querySelector('textarea#icerik');
  if (!textarea || typeof tinymce === 'undefined') return;

  tinymce.init({
    selector: 'textarea#icerik',
    height: 520,
    menubar: 'file edit view insert format tools table help',
    plugins: [
      'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor',
      'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media',
      'table', 'wordcount', 'autosave'
    ],
    toolbar:
      'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | ' +
      'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
      'link image media table | forecolor backcolor removeformat | preview code fullscreen',
    branding: false,
    relative_urls: false,
    remove_script_host: false,
    convert_urls: true,

    // >>> EKLE: upload ayarları
    images_upload_url: BASE + '/admin/medya/yukle',
    images_upload_credentials: true,         // cookie gönder
    automatic_uploads: true,
    file_picker_types: 'image',

    // >>> EKLE: özel upload handler (CSRF + FormData + {location})
    images_upload_handler: function (blobInfo, success, failure, progress) {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', BASE + '/admin/medya/yukle');
      if (CSRF) xhr.setRequestHeader('X-CSRF-Token', CSRF);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.responseType = 'json';

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100));
      };

      xhr.onload = function () {
        if (xhr.status < 200 || xhr.status >= 300) return failure('HTTP ' + xhr.status);
        const json = xhr.response || {};
        if (json && json.location) return success(json.location); // backend -> {location}
        return failure(json.mesaj || json.error || 'Geçersiz yanıt');
      };

      xhr.onerror = function () { failure('Ağ hatası'); };

      const fd = new FormData();
      if (CSRF) fd.append('csrf', CSRF);
      fd.append('file', blobInfo.blob(), blobInfo.filename());
      xhr.send(fd);
    },

    setup: (editor) => {
      const form = textarea.closest('form');
      if (form) form.addEventListener('submit', () => editor.save());
    }
  });

