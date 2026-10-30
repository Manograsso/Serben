(function ($) {
  'use strict';

  function collect($listing, page) {
    var defaults = $listing.data('defaults') || {};
    var data = $.extend({}, defaults);
    var $form = $listing.find('[data-serben-filters]');
    if ($form.length) {
      $.each($form.serializeArray(), function (_, field) {
        data[field.name] = field.value;
      });
    }
    data.page = page || data.page || 1;
    data.action = 'serben_search_partners';
    data.nonce = SerbenPublic.nonce;
    return data;
  }

  var timers = {};
  function load($listing, page) {
    var id = $listing.index('[data-serben-listing]');
    clearTimeout(timers[id]);
    timers[id] = setTimeout(function () {
      $listing.addClass('is-loading');
      $.post(SerbenPublic.ajaxUrl, collect($listing, page), function (response) {
        if (!response || !response.success) return;
        $listing.find('[data-serben-results]').html(response.data.html);
        $listing.find('[data-serben-pagination]').html(response.data.pagination);
        var total = parseInt(response.data.total, 10) || 0;
        $listing.find('[data-serben-count]').text(total === 1 ? '1 parceiro encontrado' : total + ' parceiros encontrados');
      }).always(function () {
        $listing.removeClass('is-loading');
      });
    }, 220);
  }

  function i18n(key, fallback) {
    return SerbenPublic && SerbenPublic.i18n && SerbenPublic.i18n[key]
      ? SerbenPublic.i18n[key]
      : fallback;
  }

  function toast(message) {
    var $toast = $('.serben-toast');
    if (!$toast.length) {
      $toast = $('<div class="serben-toast" role="status" aria-live="polite"></div>').appendTo('body');
    }
    $toast.text(message).addClass('is-visible');
    clearTimeout($toast.data('timer'));
    $toast.data('timer', setTimeout(function () {
      $toast.removeClass('is-visible');
    }, 2800));
  }

  function playRedeemSound() {
    try {
      var AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;
      var ctx = new AudioContext();
      var now = ctx.currentTime;
      var gain = ctx.createGain();
      gain.connect(ctx.destination);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.12, now + 0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.48);

      [523.25, 659.25, 783.99].forEach(function (freq, index) {
        var osc = ctx.createOscillator();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, now + (index * 0.07));
        osc.connect(gain);
        osc.start(now + (index * 0.07));
        osc.stop(now + 0.42 + (index * 0.04));
      });
      setTimeout(function () { if (ctx.close) ctx.close(); }, 700);
    } catch (e) {}
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var $input = $('<textarea>').val(text).css({ position: 'fixed', left: '-9999px', top: '0' }).appendTo('body');
      $input[0].focus();
      $input[0].select();
      try {
        document.execCommand('copy') ? resolve() : reject(new Error('copy_failed'));
      } catch (e) {
        reject(e);
      }
      $input.remove();
    });
  }

  function setRedeemFeedback($scope, message, state) {
    var $feedback = $scope.find('[data-serben-redeem-feedback]').first();
    if (!$feedback.length) return;
    $feedback
      .removeClass('is-error is-success is-loading')
      .addClass(state ? 'is-' + state : '')
      .text(message || '');
  }

  function revealVoucher($scope, data) {
    var voucher = $.trim((data && data.voucher_code) || '');
    var $result = $scope.find('[data-serben-voucher-result]').first();
    if (!$result.length) {
      $result = $('[data-serben-voucher-result]').first();
    }
    if (!$result.length) return;

    $result.find('[data-serben-voucher-code]').text(voucher);
    $result.find('[data-serben-copy-coupon]').attr('data-coupon', voucher).data('coupon', voucher);
    $result.prop('hidden', false).removeAttr('hidden').addClass('is-visible');

    window.setTimeout(function () {
      $('html, body').animate({ scrollTop: Math.max($result.offset().top - 90, 0) }, 320);
      $result.attr('tabindex', '-1').trigger('focus');
    }, 80);
  }

  function ajaxErrorData(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
      return xhr.responseJSON.data;
    }
    return {};
  }

  $(document).on('input change', '[data-serben-filters] input, [data-serben-filters] select', function () {
    load($(this).closest('[data-serben-listing]'), 1);
  });

  $(document).on('reset', '[data-serben-filters]', function () {
    var $listing = $(this).closest('[data-serben-listing]');
    window.setTimeout(function () {
      load($listing, 1);
    }, 0);
  });

  $(document).on('click', '.serben-page', function () {
    var $listing = $(this).closest('[data-serben-listing]');
    load($listing, parseInt($(this).data('page'), 10) || 1);
  });

  $(document).on('click', '[data-serben-whatsapp]', function () {
    $.post(SerbenPublic.ajaxUrl, {
      action: 'serben_track_whatsapp',
      nonce: SerbenPublic.nonce,
      post_id: $(this).data('post-id')
    });
  });

  $(document).on('click', '[data-serben-redeem]', function () {
    var $button = $(this);
    if ($button.prop('disabled') || $button.hasClass('is-loading')) return;

    var confirmMessage = i18n('confirmRedeem', 'Confirmar o resgate? Os Bumes serão debitados do seu saldo.');
    if (!window.confirm(confirmMessage)) return;

    var $scope = $button.closest('.serben-offer-page');
    var originalText = $button.text();
    $button
      .data('original-text', originalText)
      .prop('disabled', true)
      .addClass('is-loading')
      .attr('aria-busy', 'true')
      .text(i18n('redeeming', 'Processando resgate...'));
    setRedeemFeedback($scope, i18n('redeeming', 'Processando resgate...'), 'loading');

    $.ajax({
      url: SerbenPublic.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'serben_redeem_benefit',
        nonce: SerbenPublic.nonce,
        post_id: $button.data('post-id')
      }
    }).done(function (response) {
      if (!response || !response.success) {
        var message = response && response.data && response.data.message
          ? response.data.message
          : i18n('genericError', 'Não foi possível concluir o resgate.');
        setRedeemFeedback($scope, message, 'error');
        toast(message);
        return;
      }

      var data = response.data || {};
      if (data.balance_after !== null && typeof data.balance_after !== 'undefined') {
        $('[data-serben-balance]').text(data.balance_after);
      }
      revealVoucher($scope, data);
      $button
        .removeClass('is-loading')
        .addClass('is-complete')
        .attr('aria-busy', 'false')
        .text(data.already_redeemed ? 'Benefício já resgatado' : 'Resgate confirmado');
      setRedeemFeedback($scope, data.message || i18n('redeemSuccess', 'Resgate realizado com sucesso.'), 'success');
      toast(data.message || i18n('redeemSuccess', 'Resgate realizado com sucesso.'));
      if (($button.data('sound') === 1 || $button.data('sound') === '1') && !data.already_redeemed) {
        playRedeemSound();
      }
    }).fail(function (xhr) {
      var data = ajaxErrorData(xhr);
      var message = data.message || i18n('genericError', 'Não foi possível concluir o resgate.');
      setRedeemFeedback($scope, message, 'error');
      toast(message);

      if (data.error_code === 'login_required' && data.login_url) {
        window.setTimeout(function () {
          window.location.href = data.login_url;
        }, 800);
      }
    }).always(function () {
      if (!$button.hasClass('is-complete')) {
        $button
          .prop('disabled', false)
          .removeClass('is-loading')
          .attr('aria-busy', 'false')
          .text($button.data('original-text') || originalText);
      }
    });
  });

  $(document).on('click', '[data-serben-copy-coupon]', function () {
    var code = $(this).attr('data-coupon') || $(this).data('coupon') || $('[data-serben-voucher-code]').first().text();
    code = $.trim(code || '');
    if (!code) return;
    copyText(code).then(function () {
      toast(i18n('copied', 'Voucher copiado!'));
    }).catch(function () {
      toast(i18n('copyError', 'Copie o código manualmente.'));
    });
  });

  $(document).on('click', '[data-serben-share]', function () {
    var title = $(this).data('title') || document.title;
    var url = $(this).data('url') || window.location.href;

    if (navigator.share) {
      navigator.share({ title: title, url: url }).catch(function () {});
      return;
    }

    copyText(url).then(function () {
      toast('Link da oferta copiado!');
    }).catch(function () {
      toast('Não foi possível compartilhar automaticamente.');
    });
  });


  $(document).on('click', '[data-serben-favorite]', function () {
    var $btn = $(this), login = $btn.attr('data-login-url') || '';
    if (login) { window.location.href = login; return; }
    if ($btn.hasClass('is-loading')) return;
    $btn.addClass('is-loading').prop('disabled', true);
    $.post(SerbenPublic.ajaxUrl, { action: 'serben_toggle_favorite', nonce: SerbenPublic.nonce, post_id: $btn.data('post-id') })
      .done(function (response) {
        if (!response || !response.success) { toast((response && response.data && response.data.message) || 'Não foi possível atualizar o favorito.'); return; }
        var active = !!response.data.active;
        $('[data-serben-favorite][data-post-id="' + response.data.post_id + '"]').each(function(){
          $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false').attr('aria-label', active ? 'Favoritado' : 'Favoritar').find('em').text(active ? 'Favoritado' : 'Favoritar');
        });
        toast(active ? 'Oferta adicionada aos favoritos.' : 'Oferta removida dos favoritos.');
      }).fail(function(xhr){ var data=ajaxErrorData(xhr); if(data.login_url){window.location.href=data.login_url;return;} toast(data.message||'Não foi possível atualizar o favorito.'); })
      .always(function(){ $btn.removeClass('is-loading').prop('disabled', false); });
  });


  // Ajusta automaticamente o enquadramento das imagens de capa.
  // Banners muito largos, imagens verticais ou arquivos pequenos ficam inteiros;
  // fotos com proporção adequada continuam preenchendo o card.
  function serbenSmartMediaFit(img) {
    if (!img || !img.naturalWidth || !img.naturalHeight) return;
    var ratio = img.naturalWidth / img.naturalHeight;
    var isHero = img.getAttribute('data-serben-smart-media') === 'hero' || img.classList.contains('serben-pro-cover');
    var contain = isHero
      ? (ratio > 2.15 || ratio < 1.05)
      : (ratio > 1.92 || ratio < 1.10 || img.naturalWidth < 420 || img.naturalHeight < 210);
    img.classList.toggle('is-contain', contain);
    img.classList.toggle('is-cover', !contain);
  }

  function serbenInitSmartMedia(root) {
    $(root || document).find('[data-serben-smart-media]').each(function(){
      var img = this;
      if (img.complete) serbenSmartMediaFit(img);
      else img.addEventListener('load', function(){ serbenSmartMediaFit(img); }, { once:true });
    });
  }

  $(function(){ serbenInitSmartMedia(document); });


  // Abas fixas da página individual. O conteúdo continua legível sem JS;
  // com JS ativo, uma seção por vez é exibida para reduzir rolagem e facilitar o acesso.
  function serbenInitOfferTabs() {
    var $nav = $('[data-serben-tabs]').first();
    if (!$nav.length) return;

    var $offer = $nav.closest('.serben-offer28');
    var $tabs = $nav.find('[data-serben-tab]');
    var $panels = $offer.find('[data-serben-tab-panel]');
    if (!$tabs.length || !$panels.length) return;

    function activate(id, updateHash, focusTab) {
      var $tab = $tabs.filter('[href="' + id + '"]').first();
      var $panel = $panels.filter(id).first();
      if (!$tab.length || !$panel.length) return;

      $tabs.removeClass('is-active').attr({'aria-selected':'false','tabindex':'-1'});
      $tab.addClass('is-active').attr({'aria-selected':'true','tabindex':'0'});
      $panels.attr('hidden', true).removeClass('is-active');
      $panel.removeAttr('hidden').addClass('is-active');
      $offer.addClass('is-tabs-ready');

      var $rail = $nav.find('.serben-offer28-jump-inner');
      if ($rail.length && $tab[0] && $rail[0]) {
        var targetLeft = $tab[0].offsetLeft - Math.max(0, ($rail.innerWidth() - $tab.outerWidth()) / 2);
        $rail.stop(true).animate({scrollLeft: Math.max(0, targetLeft)}, 220);
      }

      if (updateHash && window.history && history.replaceState) history.replaceState(null, '', id);
      if (focusTab) $tab.trigger('focus');

      // Mantém o início do conteúdo visível logo abaixo da barra sticky.
      var admin = $('body').hasClass('admin-bar') ? ($('#wpadminbar').outerHeight() || 0) : 0;
      var $content = $offer.find('.serben-offer28-content').first();
      var targetTop = $content.length ? ($content.offset().top - ($nav.outerHeight() || 0) - admin - 6) : 0;
      if ($(window).scrollTop() > targetTop + 100) {
        $('html,body').stop(true).animate({scrollTop: Math.max(0, targetTop)}, 260);
      }
    }

    $tabs.on('click', function(e){
      e.preventDefault();
      activate($(this).attr('href'), true, false);
    });

    $offer.on('click', 'a[href^="#"]', function(e){
      if ($(this).is('[data-serben-tab]')) return;
      var id = $(this).attr('href');
      if (!$panels.filter(id).length) return;
      e.preventDefault();
      activate(id, true, false);
      var admin = $('body').hasClass('admin-bar') ? ($('#wpadminbar').outerHeight() || 0) : 0;
      var top = $nav.offset().top - admin;
      $('html,body').stop(true).animate({scrollTop: Math.max(0, top)}, 300);
    });

    $tabs.on('keydown', function(e){
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' && e.key !== 'Home' && e.key !== 'End') return;
      e.preventDefault();
      var index = $tabs.index(this);
      if (e.key === 'Home') index = 0;
      else if (e.key === 'End') index = $tabs.length - 1;
      else if (e.key === 'ArrowRight') index = (index + 1) % $tabs.length;
      else index = (index - 1 + $tabs.length) % $tabs.length;
      activate($tabs.eq(index).attr('href'), true, true);
    });

    var hash = window.location.hash;
    var initial = hash && $tabs.filter('[href="' + hash + '"]').length ? hash : $tabs.first().attr('href');
    activate(initial, false, false);
  }

  $(serbenInitOfferTabs);

})(jQuery);
