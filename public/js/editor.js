document.addEventListener('DOMContentLoaded', () => {
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
    setup: (editor) => {
      // Submit’te içeriği textarea’ya yazdır
      const form = textarea.closest('form');
      if (form) form.addEventListener('submit', () => editor.save());
    }
  });
});
