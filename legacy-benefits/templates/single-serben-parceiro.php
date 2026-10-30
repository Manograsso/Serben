<?php
/**
 * Página individual premium — Club Serben v2.8.0.
 * Foco em vitrine do parceiro, benefício, serviços, galeria e conversão.
 */
defined('ABSPATH') || exit;

wp_enqueue_style('serben-public');
wp_enqueue_script('serben-public');
get_header();

while (have_posts()) :
    the_post();

    $id   = get_the_ID();
    $name = get_the_title();

    $awin_id = absint(get_post_meta($id, 'awin_advertiser_id', true));
    $type_raw = (string) get_post_meta($id, SERBEN_META_PREFIX . 'tipo', true);
    if ($type_raw === '') $type_raw = (string) get_post_meta($id, 'tipo', true);
    $type_norm = strtolower(remove_accents(trim($type_raw)));
    $online = (
        strpos($type_norm, 'online') !== false ||
        strpos($type_norm, 'virtual') !== false ||
        strpos($type_norm, 'ecommerce') !== false ||
        strpos($type_norm, 'e-commerce') !== false ||
        $awin_id > 0 ||
        trim((string) get_post_meta($id, 'link_afiliado', true)) !== '' ||
        trim((string) get_post_meta($id, 'awin_click_through_url', true)) !== ''
    );

    $cover = class_exists('Serben_Cover_Studio') ? Serben_Cover_Studio::public_cover_url($id, 'large') : '';
    $has_cover = $cover !== '';
    $use_cover_as_primary = (!$online && $has_cover);

    $logo = class_exists('Serben_Utils') ? Serben_Utils::partner_logo_url($id, 'large') : '';

    $brand_profile = class_exists('Serben_Utils')
        ? Serben_Utils::brand_profile($id, $name)
        : array('color' => '#FFFFFF', 'foreground' => '#1D1920');
    $brand_color = (string) ($brand_profile['color'] ?? '#FFFFFF');
    $brand_fg    = (string) ($brand_profile['foreground'] ?? '#1D1920');

    $description = trim((string) get_post_meta($id, 'descricao_curta', true));
    if (!$description) $description = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'descricao', true));
    if (!$description) $description = trim((string) get_post_meta($id, 'descricao_completa', true));
    if (!$description) $description = get_the_excerpt() ?: get_the_content();
    $description_plain = trim(wp_strip_all_tags($description));

    $brand_intro = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'brand_intro', true));
    $showcase_text = $brand_intro !== '' ? $brand_intro : $description;

    $services_raw = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'services', true));
    $services = array();
    if ($services_raw !== '') {
        foreach (preg_split('/\R+/u', $services_raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line, 2));
            $services[] = array(
                'title' => sanitize_text_field($parts[0] ?? ''),
                'text'  => sanitize_text_field($parts[1] ?? ''),
            );
            if (count($services) >= 12) break;
        }
    }

    $gallery_raw = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'gallery_ids', true));
    $gallery_ids = array_values(array_filter(array_map('absint', preg_split('/\s*,\s*/', $gallery_raw))));
    $gallery = array();
    foreach (array_slice($gallery_ids, 0, 12) as $gallery_id) {
        $src = wp_get_attachment_image_url($gallery_id, 'large');
        if (!$src) continue;
        $gallery[] = array(
            'src' => $src,
            'alt' => trim((string) get_post_meta($gallery_id, '_wp_attachment_image_alt', true)) ?: $name,
        );
    }
    // Em parceiros online, a capa deixa de competir com o logo e entra como mídia secundária da galeria.
    if ($online && $has_cover) {
        $already = array_map(static fn($item) => (string) ($item['src'] ?? ''), $gallery);
        if (!in_array($cover, $already, true)) {
            array_unshift($gallery, array('src' => $cover, 'alt' => 'Imagem de divulgação de ' . $name));
            $gallery = array_slice($gallery, 0, 12);
        }
    }

    $rules = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'regras', true));
    if (!$rules) $rules = trim((string) get_post_meta($id, 'regras', true));
    $how_use = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'como_utilizar', true));
    $benefit_desc = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'descricao_beneficio', true));
    $coupon = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'cupom', true));
    $valid = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'validade', true));
    $valid_ts = $valid ? strtotime($valid) : false;
    $non_cumulative_raw = (string) get_post_meta($id, SERBEN_META_PREFIX . 'nao_cumulativo', true);
    $non_cumulative = ($non_cumulative_raw === '' || $non_cumulative_raw === '1');

    $benefit = class_exists('Serben_Utils')
        ? Serben_Utils::benefit_text($id, $online)
        : ($online ? 'Vantagem exclusiva na loja online' : 'Vantagem exclusiva para associados');

    $awin_status = strtolower(trim((string) get_post_meta($id, 'awin_program_status', true)));
    $awin_enabled = strtolower(trim((string) get_post_meta($id, 'awin_cashback_enabled', true)));
    $awin_pct = (float) str_replace(',', '.', (string) get_post_meta($id, 'awin_cashback_percent', true));
    $cash_eligible = $online && $awin_id > 0 && ($awin_status === '' || $awin_status === 'active') && in_array($awin_enabled, array('1', 'true', 'yes', 'on'), true) && $awin_pct > 0;

    $terms = wp_get_post_terms($id, SERBEN_TAXONOMY, array('fields' => 'names'));
    if (is_wp_error($terms)) $terms = array();
    $category_values = array_values($terms);
    foreach (preg_split('/[,;|]+/u', (string) get_post_meta($id, 'subcategoria', true)) as $sub_value) {
        if (trim($sub_value) !== '') $category_values[] = trim($sub_value);
    }
    $market_category = class_exists('Serben_Utils') ? Serben_Utils::market_primary_category($category_values) : array('name' => 'Benefícios', 'slug' => 'beneficios', 'icon' => 'tag');
    $primary_term = (string) ($market_category['name'] ?? 'Benefícios');
    $category_slug = (string) ($market_category['slug'] ?? 'beneficios');
    $category_icon = (string) ($market_category['icon'] ?? 'tag');
    $category_url = add_query_arg('categoria', rawurlencode($category_slug), home_url('/credenciados/'));

    $normalize = static function ($value) {
        $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
        $value = preg_replace('/[^\p{L}\p{N}%]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    };
    $similar = static function ($a, $b) use ($normalize) {
        $a = $normalize($a); $b = $normalize($b);
        if ($a === '' || $b === '') return false;
        if ($a === $b) return true;
        $min = min(strlen($a), strlen($b));
        return $min >= 22 && (strpos($a, $b) !== false || strpos($b, $a) !== false);
    };
    $show_benefit_desc = $benefit_desc !== '' && !$similar($benefit_desc, $benefit) && !$similar($benefit_desc, $showcase_text);
    if ($brand_intro === '' && $showcase_text !== '' && $similar($showcase_text, $benefit)) $showcase_text = '';
    $show_how_use = $how_use !== '' && !$similar($how_use, $rules) && !$similar($how_use, $benefit_desc);

    $tracked = '';
    $cta_label = $online ? ($cash_eligible ? 'Ir para a loja e ativar o benefício' : 'Acessar oferta') : 'Ver como aproveitar';
    if ($online && shortcode_exists('serben_partner_link')) {
        $tracked = do_shortcode('[serben_partner_link id="' . absint($id) . '" text="' . esc_attr($cta_label) . '" class="serben-offer28-cta"]');
        if ($tracked && strpos($tracked, 'serben-fa-icon--arrow-right') === false) {
            $tracked = preg_replace('/<\/a>\s*$/i', Serben_Utils::fa_icon('arrow-right') . '</a>', $tracked, 1);
        }
    }

    $site = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'site', true));
    if (!$site) $site = trim((string) get_post_meta($id, 'site', true));
    $fallback_link = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'link_parceiro', true));
    if (!$fallback_link) $fallback_link = trim((string) get_post_meta($id, 'link_afiliado', true));
    if (!$tracked && $online && ($fallback_link || $site)) {
        $tracked = '<a class="serben-offer28-cta" href="' . esc_url($fallback_link ?: $site) . '" target="_blank" rel="sponsored nofollow noopener">' . esc_html($cta_label) . Serben_Utils::fa_icon('arrow-right') . '</a>';
    }

    $whatsapp = trim((string) get_post_meta($id, SERBEN_META_PREFIX . 'whatsapp', true));
    $socials = array();
    foreach (array('instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'tiktok' => 'TikTok') as $key => $label) {
        $social_url = trim((string) get_post_meta($id, SERBEN_META_PREFIX . $key, true));
        if ($social_url) $socials[$key] = array('label' => $label, 'url' => $social_url);
    }
    $branches = class_exists('Serben_Branches') ? Serben_Branches::get_for_post($id) : array();

    $contacts = array();
    if ($site && !$online) {
        $display_site = preg_replace('#^https?://#i', '', $site);
        $display_site = preg_replace('#^www\.#i', '', $display_site);
        $display_site = rtrim($display_site, '/');
        $contacts[] = array('icon' => 'globe', 'label' => 'Site oficial', 'value' => $display_site, 'url' => $site);
    }
    if ($whatsapp) {
        $wa_url = class_exists('Serben_Utils') ? Serben_Utils::whatsapp_link($whatsapp, 'Olá! Vim pelo Club Serben e gostaria de saber mais sobre o benefício.') : '';
        $contacts[] = array('icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => $whatsapp, 'url' => $wa_url);
    }
    foreach ($socials as $key => $social) {
        $contacts[] = array('icon' => $key, 'label' => $social['label'], 'value' => $social['label'], 'url' => $social['url']);
    }

    if ($online) {
        $steps = array(
            array('icon' => 'external', 'title' => 'Acesse por aqui', 'text' => 'Clique obrigatoriamente no botão desta página para iniciar a compra pelo Club Serben e preservar o rastreamento.'),
            array('icon' => 'cart', 'title' => 'Escolha e finalize', 'text' => 'Compre na mesma navegação aberta pelo clube. Não use aba anônima e evite trocar de navegador durante a compra.'),
            array('icon' => $cash_eligible ? 'coins' : 'check-circle', 'title' => $cash_eligible ? 'Aguarde a validação' : 'Aproveite a vantagem', 'text' => $cash_eligible ? 'Depois da confirmação da loja, o CashBume é processado conforme as regras e o prazo da oferta.' : 'A vantagem será aplicada conforme os critérios apresentados nesta página.'),
        );
    } else {
        $steps = array(
            array('icon' => 'id-card', 'title' => 'Identifique-se', 'text' => 'Informe que é associado Club Serben antes do atendimento ou do pagamento.'),
            array('icon' => 'tag', 'title' => 'Apresente a condição', 'text' => 'Mostre sua identificação e solicite o benefício vigente para associados.'),
            array('icon' => 'check-circle', 'title' => 'Aproveite', 'text' => 'Conclua o atendimento já com a vantagem prevista nesta oferta.'),
        );
    }

    $initials = '';
    foreach (preg_split('/\s+/u', trim($name)) as $word) {
        if ($word === '') continue;
        $initials .= function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
        if ((function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials)) >= 2) break;
    }
    $initials = function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);

    $has_showcase = ($showcase_text !== '' || !empty($services) || !empty($gallery));
    $has_conditions = ($rules !== '' || $coupon !== '' || $valid_ts || $non_cumulative);
    $has_details = ($show_how_use || $has_conditions);
    $has_units = (!$online && !empty($branches));
    $has_contacts = !empty($contacts);
?>
<main class="serben-offer28">
    <div class="serben-offer28-container">
        <header class="serben-offer28-top">
            <nav class="serben-offer28-breadcrumb" aria-label="Navegação estrutural">
                <a href="<?php echo esc_url(home_url('/')); ?>">Início</a>
                <span>/</span>
                <a href="<?php echo esc_url(home_url('/credenciados/')); ?>">Ofertas</a>
                <span>/</span>
                <strong><?php echo esc_html($name); ?></strong>
            </nav>
            <div class="serben-offer28-actions">
                <?php if (class_exists('Serben_Favorites')) echo Serben_Favorites::button($id, 'Favoritar'); ?>
                <button type="button" data-serben-share data-title="<?php echo esc_attr($name); ?>" data-url="<?php echo esc_url(get_permalink()); ?>">
                    <?php echo Serben_Utils::fa_icon('share'); ?><span>Compartilhar</span>
                </button>
            </div>
        </header>

        <section class="serben-offer28-hero">
            <div class="serben-offer28-media<?php echo $use_cover_as_primary ? ' has-cover' : ' is-logo-only'; ?>" style="--brand-bg:<?php echo esc_attr($brand_color); ?>;--brand-fg:<?php echo esc_attr($brand_fg); ?>;">
                <?php if ($use_cover_as_primary) : ?>
                    <img class="serben-offer28-cover" src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr('Imagem de ' . $name); ?>" loading="eager" decoding="async">
                <?php elseif ($logo) : ?>
                    <img class="serben-offer28-brand-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr('Logo ' . $name); ?>" loading="eager" decoding="async">
                <?php else : ?>
                    <span class="serben-offer28-initials"><?php echo esc_html($initials ?: 'CS'); ?></span>
                <?php endif; ?>
            </div>

            <div class="serben-offer28-copy">
                <?php if ($use_cover_as_primary && $logo) : ?>
                    <div class="serben-offer28-logo"><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr('Logo ' . $name); ?>"></div>
                <?php endif; ?>

                <div class="serben-offer28-overline">
                    <?php echo Serben_Utils::fa_icon($online ? 'globe' : 'pin'); ?>
                    <span><?php echo esc_html($online ? 'Oferta online' : 'Vantagem presencial'); ?></span>
                </div>

                <h1><?php echo esc_html($name); ?></h1>

                <div class="serben-offer28-benefit">
                    <span>Benefício exclusivo</span>
                    <strong><?php echo esc_html($benefit); ?></strong>
                    <?php if ($show_benefit_desc) : ?><p><?php echo esc_html($benefit_desc); ?></p><?php endif; ?>
                </div>

                <div class="serben-offer28-facts">
                    <a class="serben-offer28-category" href="<?php echo esc_url($category_url); ?>">
                        <?php echo Serben_Utils::fa_icon($category_icon); ?>
                        <span><?php echo esc_html($primary_term); ?></span>
                        <?php echo Serben_Utils::fa_icon('chevron-right'); ?>
                    </a>
                    <?php if ($valid_ts) : ?><span><?php echo Serben_Utils::fa_icon('calendar'); ?>Até <?php echo esc_html(date_i18n(get_option('date_format'), $valid_ts)); ?></span><?php endif; ?>
                    <?php if ($cash_eligible) : ?><span><?php echo Serben_Utils::fa_icon('coins'); ?>CashBume</span><?php endif; ?>
                </div>

                <div class="serben-offer28-primary">
                    <?php if ($online) : ?>
                        <?php echo $tracked ? wp_kses_post($tracked) : '<span class="serben-offer28-unavailable">Oferta temporariamente indisponível.</span>'; ?>
                    <?php else : ?>
                        <a class="serben-offer28-cta" href="#como-aproveitar">Ver como aproveitar<?php echo Serben_Utils::fa_icon('arrow-right'); ?></a>
                    <?php endif; ?>
                </div>

                <p class="serben-offer28-helper">
                    <?php echo Serben_Utils::fa_icon($online ? 'shield' : 'info'); ?>
                    <span><?php echo esc_html($online ? 'Acesse a loja pelo botão acima para que a condição do Club Serben seja aplicada corretamente.' : 'Apresente sua identificação de associado antes do atendimento ou pagamento para utilizar a vantagem.'); ?></span>
                </p>
            </div>
        </section>
    </div>

    <nav class="serben-offer28-jump" aria-label="Conteúdo da oferta" data-serben-sticky-nav data-serben-tabs>
        <div class="serben-offer28-jump-inner" role="tablist" aria-label="Informações da oferta">
            <a href="#como-aproveitar" role="tab" aria-controls="como-aproveitar" aria-selected="true" class="is-active" data-serben-tab>Como aproveitar</a>
            <?php if ($has_showcase) : ?><a href="#vitrine-parceiro" role="tab" aria-controls="vitrine-parceiro" aria-selected="false" data-serben-tab>Sobre & serviços</a><?php endif; ?>
            <?php if (!empty($gallery)) : ?><a href="#galeria" role="tab" aria-controls="galeria" aria-selected="false" data-serben-tab>Galeria</a><?php endif; ?>
            <?php if ($has_details) : ?><a href="#detalhes-oferta" role="tab" aria-controls="detalhes-oferta" aria-selected="false" data-serben-tab>Detalhes</a><?php endif; ?>
            <?php if ($has_units) : ?><a href="#unidades" role="tab" aria-controls="unidades" aria-selected="false" data-serben-tab>Unidades</a><?php endif; ?>
            <?php if ($has_contacts) : ?><a href="#contato" role="tab" aria-controls="contato" aria-selected="false" data-serben-tab>Contato</a><?php endif; ?>
        </div>
    </nav>

    <div class="serben-offer28-container serben-offer28-content">
        <section class="serben-offer28-section" id="como-aproveitar" data-serben-section data-serben-tab-panel role="tabpanel">
            <header class="serben-offer28-section-head">
                <span>Como aproveitar</span>
                <h2><?php echo esc_html($online ? 'Da oferta à compra em três etapas' : 'Use sua vantagem de forma simples'); ?></h2>
            </header>
            <div class="serben-offer28-steps">
                <?php foreach ($steps as $index => $step) : ?>
                    <article>
                        <div class="serben-offer28-step-icon"><?php echo Serben_Utils::fa_icon($step['icon']); ?></div>
                        <div>
                            <span><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span>
                            <h3><?php echo esc_html($step['title']); ?></h3>
                            <p><?php echo esc_html($step['text']); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($online) : ?>
                <div class="serben-offer28-tracking-note">
                    <?php echo Serben_Utils::fa_icon('shield'); ?>
                    <p><strong>Para o cashback ser rastreado:</strong> inicie a compra pelo botão do Club Serben, não use navegação anônima e evite trocar de navegador. Se você pesquisou a loja antes em buscadores, limpar cookies/cache pode evitar que outra origem fique associada à compra.</p>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($has_showcase) : ?>
            <section class="serben-offer28-section serben-offer28-showcase" id="vitrine-parceiro" data-serben-section data-serben-tab-panel role="tabpanel">
                <header class="serben-offer28-section-head serben-offer28-section-head--wide">
                    <div><span>Vitrine do parceiro</span><h2>Conheça melhor <?php echo esc_html($name); ?></h2></div>
                    <?php if (!$online) : ?><p>Veja os diferenciais, serviços e experiências que o parceiro disponibiliza aos associados.</p><?php endif; ?>
                </header>

                <?php if ($showcase_text !== '') : ?>
                    <div class="serben-offer28-about serben-offer28-rich"><?php echo wp_kses_post(wpautop($showcase_text)); ?></div>
                <?php endif; ?>

                <?php if (!empty($services)) : ?>
                    <div class="serben-offer28-services">
                        <?php foreach ($services as $service) : ?>
                            <article>
                                <span><?php echo Serben_Utils::fa_icon('check-circle'); ?></span>
                                <div>
                                    <h3><?php echo esc_html($service['title']); ?></h3>
                                    <?php if ($service['text'] !== '') : ?><p><?php echo esc_html($service['text']); ?></p><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($gallery)) : ?>
            <section class="serben-offer28-section" id="galeria" data-serben-section data-serben-tab-panel role="tabpanel">
                <header class="serben-offer28-section-head"><span>Galeria</span><h2>Veja o espaço e a experiência</h2></header>
                <div class="serben-offer28-gallery">
                    <?php foreach ($gallery as $index => $image) : ?>
                        <figure class="<?php echo $index === 0 ? 'is-featured' : ''; ?>">
                            <img src="<?php echo esc_url($image['src']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async">
                        </figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($has_details) : ?>
            <section class="serben-offer28-section" id="detalhes-oferta" data-serben-section data-serben-tab-panel role="tabpanel">
                <header class="serben-offer28-section-head"><span>Detalhes da oferta</span><h2>Informações para aproveitar sem dúvidas</h2></header>
                <div class="serben-offer28-detail-grid<?php echo !($show_how_use && $has_conditions) ? ' is-single' : ''; ?>">
                    <?php if ($show_how_use) : ?>
                        <article class="serben-offer28-detail">
                            <h3>Orientações de uso</h3>
                            <div class="serben-offer28-rich"><?php echo wp_kses_post(wpautop($how_use)); ?></div>
                        </article>
                    <?php endif; ?>

                    <?php if ($has_conditions) : ?>
                        <article class="serben-offer28-detail">
                            <h3>Condições do benefício</h3>
                            <?php if ($coupon) : ?><div class="serben-offer28-coupon"><span>Cupom</span><strong><?php echo esc_html($coupon); ?></strong></div><?php endif; ?>
                            <?php if ($rules) : ?><div class="serben-offer28-rich"><?php echo wp_kses_post(wpautop($rules)); ?></div><?php endif; ?>
                            <ul class="serben-offer28-condition-list">
                                <?php if ($valid_ts) : ?><li><?php echo Serben_Utils::fa_icon('calendar'); ?><span>Válida até <?php echo esc_html(date_i18n(get_option('date_format'), $valid_ts)); ?>.</span></li><?php endif; ?>
                                <?php if ($non_cumulative) : ?><li><?php echo Serben_Utils::fa_icon('info'); ?><span>Não cumulativa com outras promoções, salvo condição diferente informada pelo parceiro.</span></li><?php endif; ?>
                            </ul>
                        </article>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($has_units) : ?>
            <section class="serben-offer28-section" id="unidades" data-serben-section data-serben-tab-panel role="tabpanel">
                <header class="serben-offer28-section-head"><span>Unidades participantes</span><h2>Onde aproveitar</h2></header>
                <div class="serben-offer28-branches">
                    <?php foreach (array_slice($branches, 0, 12) as $branch) : ?>
                        <article>
                            <?php echo Serben_Utils::fa_icon('pin'); ?>
                            <div>
                                <h3><?php echo esc_html($branch['unit_name'] ?: 'Unidade participante'); ?></h3>
                                <?php if (!empty($branch['address'])) : ?><p><?php echo esc_html($branch['address']); ?></p><?php endif; ?>
                                <?php if (!empty($branch['city']) || !empty($branch['state'])) : ?><p><?php echo esc_html(trim(($branch['city'] ?? '') . (!empty($branch['state']) ? ' - ' . $branch['state'] : ''))); ?></p><?php endif; ?>
                                <?php if (!empty($branch['hours'])) : ?><small><?php echo Serben_Utils::fa_icon('clock'); ?><?php echo esc_html($branch['hours']); ?></small><?php endif; ?>
                                <?php if (!empty($branch['maps_url'])) : ?><a href="<?php echo esc_url($branch['maps_url']); ?>" target="_blank" rel="noopener">Ver no mapa <?php echo Serben_Utils::fa_icon('arrow-right'); ?></a><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($has_contacts) : ?>
            <section class="serben-offer28-section" id="contato" data-serben-section data-serben-tab-panel role="tabpanel">
                <header class="serben-offer28-section-head"><span>Canais oficiais</span><h2>Fale com <?php echo esc_html($name); ?></h2></header>
                <div class="serben-offer28-contacts">
                    <?php foreach ($contacts as $contact) : ?>
                        <?php if ($contact['url']) : ?><a href="<?php echo esc_url($contact['url']); ?>" target="_blank" rel="noopener"><?php else : ?><span><?php endif; ?>
                            <?php echo Serben_Utils::fa_icon($contact['icon']); ?>
                            <div><span><?php echo esc_html($contact['label']); ?></span><strong><?php echo esc_html($contact['value']); ?></strong></div>
                            <?php if ($contact['url']) echo Serben_Utils::fa_icon('arrow-right'); ?>
                        <?php if ($contact['url']) : ?></a><?php else : ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php
        $related_args = array('post_type' => SERBEN_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 4, 'post__not_in' => array($id), 'orderby' => 'rand');
        $term_ids = wp_get_post_terms($id, SERBEN_TAXONOMY, array('fields' => 'ids'));
        if (!is_wp_error($term_ids) && !empty($term_ids)) $related_args['tax_query'] = array(array('taxonomy' => SERBEN_TAXONOMY, 'field' => 'term_id', 'terms' => $term_ids));
        $related = new WP_Query($related_args);
        if ($related->have_posts()) : ?>
            <section class="serben-offer28-section serben-offer28-related">
                <header class="serben-offer28-section-head"><span>Mais benefícios</span><h2>Você também pode gostar</h2></header>
                <div class="serben-offer28-related-grid">
                    <?php while ($related->have_posts()) : $related->the_post(); echo Serben_Shortcodes::render_card(get_the_ID(), false); endwhile; ?>
                </div>
            </section>
            <?php wp_reset_postdata(); ?>
        <?php endif; ?>
    </div>
</main>
<?php
endwhile;
get_footer();

