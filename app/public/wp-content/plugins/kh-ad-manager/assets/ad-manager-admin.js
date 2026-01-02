(function($){
  $(document).ready(function() {

    // --- Preview Button Handling ---
    const previewBtn = $('#ad-preview-modal-btn');
    const postId = acf.get('post_id');
    const isSaved = postId && !String(postId).startsWith('new_post') && parseInt(postId) > 0;

    if (previewBtn.length && isSaved) {
      previewBtn.on('click', function() {
        const $preview = $('#hidden-ad-preview-content').html();
        $('#ad-preview-modal').html($preview);
        $('#ad-preview-modal-bg').fadeIn();
      });
    } else {
      previewBtn.prop('disabled', true).addClass('is-disabled').css({
        'background-color': '#ccc',
        'border-color': '#aaa',
        'color': '#666',
        'cursor': 'not-allowed',
        'pointer-events': 'none',
        'opacity': '0.6'
      });

      if (!$('#kh-preview-helper').length) {
        previewBtn.after(`
          <p id="kh-preview-helper" style="color:#666;font-size:12px;margin-top:4px;font-style:italic;">
            💡 Save this ad first to enable preview.
          </p>
        `);
      }
    }

    // --- Close Modal ---
    $('#ad-preview-modal-bg').on('click', function(e) {
      if (e.target === this) $(this).fadeOut();
    });

    // --- Image Dimension Warning ---
    function checkImageDimensions() {
      const slot = $('#select2-acf-field_68652678baa37-container').attr('title');
      const img = $('.acf-field[data-name="ad_image"] img');
      $('#dimension-warning').remove();

      if (slot && img.length) {
        const image = img.get(0);
        const width = image.naturalWidth;
        const height = image.naturalHeight;
        let warnMsg = '';

        if (slot === 'header' && width <= height) {
          warnMsg = '⚠️ Header ads should be landscape (wider than tall). Wanna check that?';
        }
        if ((slot === 'sidebar1' || slot === 'sidebar2') && width >= height) {
          warnMsg = '⚠️ Sidebar ads should be portrait (taller than wide). Wanna check that?';
        }

        if (warnMsg) {
          img.closest('.acf-image-uploader').after(`
            <div id="dimension-warning" style="background:#ffd600;color:#900;font-weight:bold;padding:10px 12px;margin:8px 0 12px 0;border-radius:5px;border:2px solid #900;">
              ${warnMsg}
            </div>
          `);
        }
      }
    }

    $(document).on('change', '[data-key="field_68652678baa37"] select', checkImageDimensions);


    // --- Card Builder Toggle ---
    function toggleCardBuilderFields() {
      const select = $('#acf-field_68652678baa37');
      const selectedSlug = select.attr('data-selected-slot') || '';
      const cardBuilderGroup = $('#acf-group_68a0fd1858e61');
      const show = selectedSlug === 'slide-in' || selectedSlug === 'pop-up';
      cardBuilderGroup.toggle(show);
    }


    toggleCardBuilderFields();
    $('#acf-field_68652678baa37').on('change', toggleCardBuilderFields);
  });
})(jQuery);
