(function ($) {
  'use strict';

  function togglePartnerFields() {
    var type = $('.serben-partner-type').val();
    $('.serben-online-fields').toggle(type === 'online');
    $('.serben-physical-fields, #serben_partner_branches').toggle(type !== 'online');
  }

  $(document).on('change', '.serben-partner-type', togglePartnerFields);
  $(togglePartnerFields);

  $(document).on('click', '.serben-upload-image', function () {
    var $field = $(this).closest('.serben-image-field');
    var frame = wp.media({ title: 'Selecionar imagem', multiple: false, library: { type: 'image' } });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $field.find('.serben-image-id').val(attachment.id);
      if ($field.attr('data-key') === 'logo_id') $field.find('.serben-logo-mode').val('local');
      $field.find('.serben-image-preview').html('<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" alt="">');
    });
    frame.open();
  });

  $(document).on('click', '.serben-remove-image', function () {
    var $field = $(this).closest('.serben-image-field');
    $field.find('.serben-image-id').val('');
    if ($field.attr('data-key') === 'logo_id') $field.find('.serben-logo-mode').val('none');
    $field.find('.serben-image-preview').empty();
  });

  $(document).on('click', '.serben-add-branch', function () {
    var $wrap = $(this).closest('.serben-branches');
    var index = parseInt($wrap.attr('data-next-index'), 10) || 0;
    var html = $('#serben-branch-template').html().replaceAll('__INDEX__', index);
    $wrap.find('.serben-branch-list').append(html);
    $wrap.attr('data-next-index', index + 1);
  });

  $(document).on('click', '.serben-remove-branch', function () {
    if ($('.serben-branch-item').length > 1) {
      $(this).closest('.serben-branch-item').remove();
    } else {
      $(this).closest('.serben-branch-item').find('input, textarea').val('');
    }
  });


  $(document).on('click', '.serben-select-gallery', function () {
    var $field = $(this).closest('.serben-gallery-field');
    var frame = wp.media({ title: 'Selecionar fotos da galeria', multiple: true, library: { type: 'image' } });
    frame.on('select', function () {
      var selection = frame.state().get('selection').toJSON();
      var ids = [];
      var html = '';
      selection.slice(0, 12).forEach(function (attachment) {
        ids.push(attachment.id);
        var src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
        html += '<span data-id="' + attachment.id + '"><img src="' + src + '" alt=""></span>';
      });
      $field.find('.serben-gallery-ids').val(ids.join(','));
      $field.find('.serben-gallery-preview').html(html);
    });
    frame.open();
  });

  $(document).on('click', '.serben-clear-gallery', function () {
    var $field = $(this).closest('.serben-gallery-field');
    $field.find('.serben-gallery-ids').val('');
    $field.find('.serben-gallery-preview').empty();
  });


  function escHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  $(document).on('click', '.serben-awin-find-logo', function () {
    var $tools = $(this).closest('.serben-awin-logo-tools');
    var postId = parseInt($tools.attr('data-post-id'), 10) || 0;
    var $status = $tools.find('.serben-awin-status');
    var $results = $tools.find('.serben-awin-results');
    if (!window.SerbenAdmin || !SerbenAdmin.awinReady) {
      $status.text('Configure a integração Awin primeiro.');
      return;
    }
    $status.text('Pesquisando na Awin…');
    $results.empty();
    $.post(SerbenAdmin.ajaxUrl, {
      action: 'serben_awin_find_logo', nonce: SerbenAdmin.awinNonce, post_id: postId
    }).done(function (res) {
      if (!res || !res.success) {
        $status.text(res && res.data && res.data.message ? res.data.message : 'Não foi possível consultar a Awin.');
        return;
      }
      var items = res.data.programmes || [];
      if (!items.length) {
        $status.text('Nenhuma correspondência encontrada entre os programas da sua conta Awin.');
        return;
      }
      $status.text(items.length + ' resultado(s). Confira antes de usar.');
      items.forEach(function (item) {
        var logo = item.logoUrl ? '<img src="' + escHtml(item.logoUrl) + '" alt="">' : '<span class="serben-awin-no-logo">Sem logo</span>';
        var score = item.score ? '<small>' + escHtml(item.score) + '% compatível</small>' : '';
        $results.append(
          '<div class="serben-awin-result">' +
            '<div class="serben-awin-result-logo">' + logo + '</div>' +
            '<div class="serben-awin-result-copy"><strong>' + escHtml(item.name) + '</strong><span>ID ' + escHtml(item.id) + '</span>' + score + '</div>' +
            '<button type="button" class="button button-primary serben-awin-use-logo" data-advertiser-id="' + escHtml(item.id) + '"' + (item.logoUrl ? '' : ' disabled') + '>Usar este logo</button>' +
          '</div>'
        );
      });
    }).fail(function () { $status.text('Falha de comunicação ao consultar a Awin.'); });
  });

  $(document).on('click', '.serben-awin-use-logo', function () {
    var $button = $(this);
    var $tools = $button.closest('.serben-awin-logo-tools');
    var $field = $tools.closest('.serben-image-field');
    var postId = parseInt($tools.attr('data-post-id'), 10) || 0;
    $button.prop('disabled', true).text('Importando…');
    $.post(SerbenAdmin.ajaxUrl, {
      action: 'serben_awin_import_logo', nonce: SerbenAdmin.awinNonce,
      post_id: postId, advertiser_id: $button.attr('data-advertiser-id')
    }).done(function (res) {
      if (!res || !res.success) {
        $tools.find('.serben-awin-status').text(res && res.data && res.data.message ? res.data.message : 'Não foi possível importar o logo.');
        $button.prop('disabled', false).text('Usar este logo');
        return;
      }
      $field.find('.serben-image-id').val(res.data.attachmentId);
      $field.find('.serben-logo-mode').val('local');
      $field.find('.serben-image-preview').html('<img src="' + escHtml(res.data.url) + '" alt="">');
      $tools.find('.serben-awin-status').text('Logo Awin importado. Salve/atualize o parceiro para concluir as demais alterações da página.');
      $tools.find('.serben-awin-results').empty();
    }).fail(function () {
      $tools.find('.serben-awin-status').text('Falha ao importar o logo da Awin.');
      $button.prop('disabled', false).text('Usar este logo');
    });
  });
})(jQuery);
