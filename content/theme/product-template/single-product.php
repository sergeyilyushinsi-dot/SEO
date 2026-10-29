<?php
/**
 * Карточка товара в дизайне блога — woocommerce/single-product.php.
 *
 * Сохранено из старого шаблона: шапка сайта (header-nav), крошки Yoast, галерея Swiper, первые атрибуты,
 * краткое описание, кнопки «Запросить КП» / «Заказать звонок» / «Демонстрация» с формами Bitrix24
 * по категориям (разметка модальных окон та же — их открывает скрипт темы), описание, «Преимущества»
 * (_custom_advantages), характеристики (видимые атрибуты), видео («Файл видео»), документы («Документы товара»),
 * [partners_display], видеоблок категории (display_video_block), [recommended_news], footer-nav, форма Elementor 3699.
 * Новое: всё на одной странице с якорным меню вместо вкладок, «Модели линейки» (свои карточки вместо
 * [similar_products] — с уменьшенными картинками), стили описаний amt-pd-*, на телефоне старые описания
 * со своей вёрсткой перестраиваются в одну колонку.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

global $product;
$product = wc_get_product( get_the_ID() );
if ( ! $product instanceof WC_Product ) {
	get_footer();
	return;
}

$amt_pid        = $product->get_id();
$amt_lang_ru    = ! function_exists( 'pll_current_language' ) || 'ru' === pll_current_language();
$amt_categories = wc_get_product_terms( $amt_pid, 'product_cat', array( 'fields' => 'all' ) );

/* Изображения: главное + галерея. Слайды — large, миниатюры — thumbnail (раньше везде full — тяжело). */
$amt_images = array();
if ( has_post_thumbnail( $amt_pid ) ) {
	$amt_images[] = get_post_thumbnail_id( $amt_pid );
}
foreach ( $product->get_gallery_image_ids() as $aid ) {
	$amt_images[] = $aid;
}

/* Видимые атрибуты: первые 4 — ключевые цифры в первом экране, все — в таблице характеристик. */
$amt_specs = array();
foreach ( $product->get_attributes() as $attribute ) {
	if ( ! $attribute->get_visible() ) {
		continue;
	}
	$value = $product->get_attribute( $attribute->get_name() );
	if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
		continue;
	}
	$amt_specs[] = array( wc_attribute_label( $attribute->get_name() ), $value );
}
$amt_kpis = array_slice( $amt_specs, 0, 4 );

/* Форма Bitrix24 для «Запросить КП» — по категории верхнего уровня (как в старом шаблоне). */
$amt_top_cat = null;
$amt_depth   = PHP_INT_MAX;
foreach ( wc_get_product_term_ids( $amt_pid, 'product_cat' ) as $cat_id ) {
	$anc = get_ancestors( $cat_id, 'product_cat' );
	if ( count( $anc ) + 1 < $amt_depth ) {
		$amt_depth   = count( $anc ) + 1;
		$amt_top_cat = empty( $anc ) ? $cat_id : $anc[ count( $anc ) - 1 ];
	}
}
$amt_forms = array(
	18 => array( 'id' => 'inline/65/uy2pqb', 'loader' => 'loader_65.js' ),
	19 => array( 'id' => 'inline/93/2rkjsr', 'loader' => 'loader_93.js' ),
	17 => array( 'id' => 'inline/71/mac0bd', 'loader' => 'loader_71.js' ),
	23 => array( 'id' => 'inline/53/7ighaq', 'loader' => 'loader_53.js' ),
	24 => array( 'id' => 'inline/41/hsr7bh', 'loader' => 'loader_41.js' ),
	20 => array( 'id' => 'inline/77/fjkjgm', 'loader' => 'loader_77.js' ),
	21 => array( 'id' => 'inline/103/o5ytiy', 'loader' => 'loader_103.js' ),
	22 => array( 'id' => 'inline/119/jz6ers', 'loader' => 'loader_119.js' ),
);
$amt_form = ( $amt_top_cat && isset( $amt_forms[ $amt_top_cat ] ) ) ? $amt_forms[ $amt_top_cat ] : array( 'id' => 'inline/41/hsr7bh', 'loader' => 'loader_41.js' );

/* Категория для подписи и для «Моделей линейки» — самая глубокая. */
$amt_cat = null;
foreach ( $amt_categories as $c ) {
	if ( ! $amt_cat || count( get_ancestors( $c->term_id, 'product_cat' ) ) > count( get_ancestors( $amt_cat->term_id, 'product_cat' ) ) ) {
		$amt_cat = $c;
	}
}

/* Описание: h1 из старых текстов → h2 (на странице один H1). */
$amt_desc = $product->get_description();
$amt_desc = preg_replace( '~<h1(\s|>)~i', '<h2$1', $amt_desc );
$amt_desc = preg_replace( '~</h1>~i', '</h2>', $amt_desc );
$amt_desc = apply_filters( 'the_content', $amt_desc );

$amt_adv = (string) get_post_meta( $amt_pid, '_custom_advantages', true );
$amt_adv = preg_replace( array( '~<h1(\s|>)~i', '~</h1>~i' ), array( '<h2$1', '</h2>' ), $amt_adv );

$amt_video_id  = get_post_meta( $amt_pid, 'Файл видео', true );
$amt_video_url = $amt_video_id ? wp_get_attachment_url( $amt_video_id ) : '';
$amt_doc_id    = get_post_meta( $amt_pid, 'Документы товара', true );
$amt_doc_url   = $amt_doc_id ? wp_get_attachment_url( $amt_doc_id ) : '';

/* Модели линейки: товары той же категории. */
$amt_related = array();
if ( $amt_cat ) {
	$amt_related = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 4,
			'exclude'  => array( $amt_pid ),
			'category' => array( $amt_cat->slug ),
			'orderby'  => 'menu_order',
			'order'    => 'ASC',
		)
	);
}

/* Видеоблок категории выводим, только если в нём есть видео (иначе тема пишет «Видео не найдено»). */
$amt_cat_video = '';
if ( function_exists( 'display_video_block' ) ) {
	ob_start();
	display_video_block( $amt_pid, 'product' );
	$amt_cat_video = ob_get_clean();
	if ( false === stripos( $amt_cat_video, '<video' ) && false === stripos( $amt_cat_video, '<iframe' ) ) {
		$amt_cat_video = '';
	}
}

$amt_nav = array( array( 'amt-p-desc', 'Описание' ) );
if ( trim( wp_strip_all_tags( $amt_adv ) ) ) {
	$amt_nav[] = array( 'amt-p-adv', 'Преимущества' );
}
if ( $amt_specs ) {
	$amt_nav[] = array( 'amt-p-specs', 'Характеристики' );
}
if ( $amt_video_url ) {
	$amt_nav[] = array( 'amt-p-video', 'Видео' );
}
if ( $amt_doc_url ) {
	$amt_nav[] = array( 'amt-p-docs', 'Документы' );
}
if ( $amt_related ) {
	$amt_nav[] = array( 'amt-p-models', 'Модели линейки' );
}
$amt_nav[] = array( 'amt-form', 'Заявка' );

get_template_part( 'header-nav' );
?>
<div id="amt-blog" class="amt-single amt-product">

	<section class="amt-hero amt-phero">
		<div class="amt-container">
			<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
				<nav class="amt-crumbs" aria-label="Хлебные крошки"><?php yoast_breadcrumb( '<span>', '</span>' ); ?></nav>
			<?php endif; ?>

			<div class="amt-phero__grid">
				<div class="amt-gallery">
					<div class="swiper main-swiper">
						<div class="swiper-wrapper">
							<?php foreach ( $amt_images as $i => $aid ) : ?>
								<div class="swiper-slide"><?php echo wp_get_attachment_image( $aid, 'large', false, array( 'class' => 'slide-image', 'alt' => $product->get_name(), 'loading' => $i ? 'lazy' : 'eager' ) ); ?></div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php if ( count( $amt_images ) > 1 ) : ?>
						<div class="swiper thumbs-swiper">
							<div class="swiper-wrapper">
								<?php foreach ( $amt_images as $aid ) : ?>
									<div class="swiper-slide"><?php echo wp_get_attachment_image( $aid, 'thumbnail', false, array( 'class' => 'thumb-image', 'alt' => '', 'loading' => 'lazy' ) ); ?></div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="amt-pinfo">
					<?php if ( $amt_cat ) : ?>
						<a class="amt-label" href="<?php echo esc_url( get_term_link( $amt_cat ) ); ?>"><?php echo esc_html( $amt_cat->name ); ?></a>
					<?php endif; ?>
					<h1 class="amt-shero__title amt-pinfo__title"><?php echo esc_html( $product->get_name() ); ?></h1>

					<?php if ( $amt_kpis ) : ?>
						<dl class="amt-pkpis">
							<?php foreach ( $amt_kpis as $k ) : ?>
								<div><dt><?php echo esc_html( $k[0] ); ?></dt><dd><?php echo wp_kses_post( $k[1] ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php if ( trim( wp_strip_all_tags( $product->get_short_description() ) ) ) : ?>
						<div class="amt-pshort"><?php echo wp_kses_post( $product->get_short_description() ); ?></div>
					<?php endif; ?>

					<div class="amt-pbuy">
						<div class="amt-pprice">Цена по запросу</div>
						<div class="btn_product_single amt-pbuttons">
							<button class="amt-button amt-button--primary" data-modal="request"
								data-form-id="<?php echo esc_attr( $amt_form['id'] ); ?>"
								data-form-loader="<?php echo esc_attr( $amt_form['loader'] ); ?>"
								data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
								data-category-id="<?php echo esc_attr( $amt_top_cat ? $amt_top_cat : 0 ); ?>">Запросить КП <span>→</span></button>
							<button class="amt-button amt-button--secondary" data-modal="call" data-form-id="inline/6/hkgqtn"
								data-form-loader="loader_6.js"
								data-product-name="<?php echo esc_attr( $product->get_name() ); ?>">Заказать звонок</button>
							<a class="amt-button amt-button--secondary" href="#amt-form">Демонстрация</a>
						</div>
					</div>

				</div>
			</div>
		</div>
	</section>

	<nav class="amt-topics amt-pnav" aria-label="Разделы карточки">
		<div class="amt-container amt-topics__row">
			<?php foreach ( $amt_nav as $i => $n ) : ?>
				<a class="amt-topic<?php echo 0 === $i ? ' is-active' : ''; ?>" href="#<?php echo esc_attr( $n[0] ); ?>"><?php echo esc_html( $n[1] ); ?></a>
			<?php endforeach; ?>
		</div>
	</nav>

	<section class="amt-section amt-psec" id="amt-p-desc">
		<div class="amt-container">
			<div class="amt-prose amt-pd"><?php echo $amt_desc; // phpcs:ignore WordPress.Security.EscapeOutput -- описание товара ?></div>
		</div>
	</section>

	<?php if ( trim( wp_strip_all_tags( $amt_adv ) ) ) : ?>
		<section class="amt-section amt-psec" id="amt-p-adv">
			<div class="amt-container">
				<div class="amt-section__head"><div class="amt-section__copy"><div class="amt-eyebrow">Преимущества</div><h2 class="amt-title amt-title--section">Почему <?php echo esc_html( $product->get_name() ); ?></h2></div></div>
				<div class="amt-prose amt-pd"><?php echo do_shortcode( $amt_adv ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $amt_specs ) : ?>
		<section class="amt-section amt-section--soft amt-psec" id="amt-p-specs">
			<div class="amt-container">
				<div class="amt-section__head"><div class="amt-section__copy"><div class="amt-eyebrow">Характеристики</div><h2 class="amt-title amt-title--section">Технические характеристики</h2></div></div>
				<div class="amt-pspecs">
					<table>
						<tbody>
							<?php foreach ( $amt_specs as $s ) : ?>
								<tr><th scope="row"><?php echo esc_html( $s[0] ); ?></th><td><?php echo wp_kses_post( $s[1] ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $amt_video_url ) : ?>
		<section class="amt-section amt-psec" id="amt-p-video">
			<div class="amt-container">
				<div class="amt-section__head"><div class="amt-section__copy"><div class="amt-eyebrow">Видео</div><h2 class="amt-title amt-title--section">Видеообзор</h2></div></div>
				<div class="amt-pvideo"><video controls preload="metadata" playsinline><source src="<?php echo esc_url( $amt_video_url ); ?>" type="video/mp4"></video></div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $amt_doc_url ) : ?>
		<section class="amt-section amt-psec" id="amt-p-docs">
			<div class="amt-container">
				<div class="amt-section__head"><div class="amt-section__copy"><div class="amt-eyebrow">Документы</div><h2 class="amt-title amt-title--section">Документация</h2></div></div>
				<a class="amt-pdoc" href="<?php echo esc_url( $amt_doc_url ); ?>" target="_blank" rel="noopener">
					<span class="amt-pdoc__ext"><?php echo esc_html( strtoupper( pathinfo( wp_parse_url( $amt_doc_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ); ?></span>
					<span class="amt-pdoc__name"><?php echo esc_html( get_the_title( $amt_doc_id ) ? get_the_title( $amt_doc_id ) : $product->get_name() ); ?></span>
					<span class="amt-pdoc__more">Открыть →</span>
				</a>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $amt_related ) : ?>
		<section class="amt-section amt-section--soft amt-psec" id="amt-p-models">
			<div class="amt-container">
				<div class="amt-section__head">
					<div class="amt-section__copy"><div class="amt-eyebrow">Линейка</div><h2 class="amt-title amt-title--section"><?php echo esc_html( $amt_cat ? $amt_cat->name : 'Другие модели' ); ?></h2></div>
					<?php if ( $amt_cat ) : ?><a class="amt-button amt-button--secondary" href="<?php echo esc_url( get_term_link( $amt_cat ) ); ?>">Весь раздел <span>→</span></a><?php endif; ?>
				</div>
				<div class="amt-pmodels">
					<?php foreach ( $amt_related as $rp ) : ?>
						<a class="amt-pmodel" href="<?php echo esc_url( $rp->get_permalink() ); ?>">
							<span class="amt-pmodel__img"><?php echo wp_get_attachment_image( $rp->get_image_id(), 'medium', false, array( 'loading' => 'lazy', 'alt' => $rp->get_name() ) ); ?></span>
							<span class="amt-pmodel__title"><?php echo esc_html( $rp->get_name() ); ?></span>
							<span class="amt-pmodel__more">Подробнее →</span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="amt-section amt-ppartners">
		<div class="amt-container"><?php echo do_shortcode( '[partners_display]' ); ?></div>
	</section>

	<?php if ( $amt_cat_video ) : ?>
		<section class="amt-section amt-section--soft amt-pcatvideo">
			<div class="amt-container"><?php echo $amt_cat_video; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		</section>
	<?php endif; ?>

	<section class="amt-section amt-pnews">
		<div class="amt-container"><?php echo do_shortcode( '[recommended_news]' ); ?></div>
	</section>
</div>

<!-- Модальные окна форм (открывает скрипт темы) -->
	<div class="modal_item add_basket" style="display: none;">
		<div class="modal-content">
			<div class="form-header"><div class="text"><div class="title switcher-title font_24 color_333"><?php echo $amt_lang_ru ? 'Запросить КП' : 'Request CP'; ?></div></div></div>
			<div class="form-container"></div>
			<span class="jqmClose top-close stroke-theme-hover close_kp_modal" title="<?php echo $amt_lang_ru ? 'Закрыть' : 'Close'; ?>"><i class="svg inline svg-inline-" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 1L1 13M1 1L13 13" stroke="#999999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></i></span>
		</div>
	</div>
	<div class="modal_item modal_cb" style="display: none;">
		<div class="modal-content">
			<div class="form-header"><div class="text"><div class="title switcher-title font_24 color_333"><?php echo $amt_lang_ru ? 'Заказать звонок' : 'Request a Call'; ?></div></div></div>
			<div class="form-container"></div>
			<span class="jqmClose top-close stroke-theme-hover close_cb_modal" title="<?php echo $amt_lang_ru ? 'Закрыть' : 'Close'; ?>"><i class="svg inline svg-inline-" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 1L1 13M1 1L13 13" stroke="#999999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></i></span>
		</div>
	</div>

<!-- Форма «Заказать звонок» из старого шаблона -->
<div class="modal_item modal_cb">
	<div class="modal-content">
		<div class="form-header"><div class="text"><div class="title switcher-title font_24 color_333">Заказать звонок</div></div></div>
		<script data-b24-form="inline/41/hsr7bh" data-skip-moving="true">(function (w, d, u) { var s = d.createElement('script'); s.async = true; s.src = u + '?' + (Date.now() / 180000 | 0); var h = d.getElementsByTagName('script')[0]; h.parentNode.insertBefore(s, h); })(window, document, 'https://cdn-ru.bitrix24.ru/b29360544/crm/form/loader_41.js');</script>
	</div>
</div>

<?php get_template_part( 'footer-nav' ); ?>

<div class="amt-section amt-sform" id="amt-form">
	<div class="custom-contactblock">
		<?php
		if ( class_exists( '\Elementor\Plugin' ) ) {
			echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( 3699 ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>
</div>

<style id="amt-product-styles">
#amt-blog{
  --amt-orange:#ff7300;--amt-orange-2:#ff9a3d;--amt-orange-soft:#fff3e9;
  --amt-ink:#171717;--amt-text:#4f4f4f;--amt-muted:#777;
  --amt-line:#e9e9e9;--amt-surface:#f7f7f5;--amt-white:#fff;--amt-dark:#171717;
  --amt-radius-xl:34px;--amt-radius-lg:24px;--amt-radius-md:18px;--amt-pill:999px;
  --amt-shadow:0 22px 70px rgba(22,22,22,.08);
  position:relative;width:100%;color:var(--amt-ink);background:var(--amt-white);
  font-family:"Montserrat",Arial,sans-serif;line-height:1.55;isolation:isolate;
}
#amt-blog *{box-sizing:border-box}
#amt-blog a{color:inherit;text-decoration:none}
#amt-blog h1,#amt-blog h2,#amt-blog h3,#amt-blog p{margin-top:0}
#amt-blog .amt-container{width:min(1180px,calc(100% - 48px));margin-inline:auto}
#amt-blog .amt-section{position:relative;padding:112px 0}
#amt-blog .amt-section--soft{background:var(--amt-surface)}
#amt-blog .amt-section--dark{background:var(--amt-dark);color:#fff}
#amt-blog .amt-eyebrow{display:inline-flex;align-items:center;gap:10px;margin-bottom:22px;color:var(--amt-orange);font-size:12px;font-weight:800;line-height:1.2;letter-spacing:.14em;text-transform:uppercase}
#amt-blog .amt-eyebrow::before{width:28px;height:2px;content:"";background:currentColor}
#amt-blog .amt-title{max-width:880px;font-size:clamp(38px,5vw,68px);font-weight:760;line-height:1.04;letter-spacing:-.045em}
#amt-blog .amt-title--section{font-size:clamp(32px,4vw,52px);line-height:1.08}
#amt-blog .amt-title em{color:var(--amt-orange);font-style:normal}
#amt-blog .amt-lead{max-width:640px;margin-top:26px;color:var(--amt-text);font-size:17px;line-height:1.7}

#amt-blog .amt-button{min-height:54px;display:inline-flex;align-items:center;justify-content:center;gap:11px;padding:14px 23px;border:1px solid transparent;border-radius:var(--amt-pill);cursor:pointer;font-family:inherit;font-size:14px;font-weight:760;line-height:1;transition:transform .25s ease,box-shadow .25s ease,background-color .25s ease,color .25s ease}
#amt-blog .amt-button:hover{transform:translateY(-2px)}
#amt-blog .amt-button--primary{color:var(--amt-white);background:var(--amt-orange);box-shadow:0 12px 28px rgba(255,115,0,.28)}
#amt-blog .amt-button--primary:hover{color:var(--amt-white);background:#e86500;box-shadow:0 16px 34px rgba(255,115,0,.34)}
#amt-blog .amt-button--secondary{color:var(--amt-ink);border-color:#d9d9d9;background:rgba(255,255,255,.72)}
#amt-blog .amt-button--secondary:hover{border-color:var(--amt-ink);background:#fff}
#amt-blog .amt-section--dark .amt-button--secondary{color:#fff;background:transparent;border-color:#4a4a4a}
#amt-blog .amt-section--dark .amt-button--secondary:hover{color:var(--amt-orange);border-color:var(--amt-orange)}

/* HERO */
#amt-blog .amt-hero{position:relative;overflow:hidden;padding:92px 0 72px;background:radial-gradient(circle at 84% 20%,rgba(255,115,0,.12),transparent 28%),linear-gradient(180deg,#fff 0%,#fbfbfa 100%)}
#amt-blog .amt-hero::before{position:absolute;inset:0;z-index:-1;content:"";opacity:.4;background-image:linear-gradient(rgba(30,30,30,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(30,30,30,.035) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(to bottom,#000 30%,transparent 92%)}
#amt-blog .amt-hero__grid{position:relative;display:grid;grid-template-columns:1.35fr .65fr;gap:72px;align-items:end}
#amt-blog .amt-hero__aside{padding-left:30px;border-left:1px solid var(--amt-line)}
#amt-blog .amt-hero__aside p{color:var(--amt-text);font-size:15px;line-height:1.7;margin:0}
#amt-blog .amt-hero__actions{margin-top:30px;display:flex;gap:10px;flex-wrap:wrap}
#amt-blog .amt-trust{position:relative;margin-top:58px;display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid var(--amt-line)}
#amt-blog .amt-trust__item{min-height:104px;padding:22px 24px 0 0;border-right:1px solid var(--amt-line)}
#amt-blog .amt-trust__item:not(:first-child){padding-left:24px}
#amt-blog .amt-trust__item:last-child{border-right:0}
#amt-blog .amt-trust__item b{display:block;margin-bottom:6px;font-size:14px;font-weight:760}
#amt-blog .amt-trust__item span{color:var(--amt-muted);font-size:13px;line-height:1.55}

/* TOPIC NAV */
#amt-blog .amt-topics{position:sticky;top:0;z-index:20;background:#fff;border-top:1px solid var(--amt-line);border-bottom:1px solid var(--amt-line)}
#amt-blog .amt-topics__row{display:flex;gap:24px;align-items:center;padding:18px 0;overflow:auto;scrollbar-width:none}
#amt-blog .amt-topics__row::-webkit-scrollbar{display:none}
#amt-blog .amt-topic{padding:0;border:0;background:transparent;color:#6a6a65;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;position:relative;transition:color .2s ease}
#amt-blog .amt-topic:hover{color:var(--amt-ink)}
#amt-blog .amt-topic.is-active{color:var(--amt-ink)}
#amt-blog .amt-topic.is-active::after{content:"";position:absolute;left:0;right:0;bottom:-19px;height:2px;background:var(--amt-orange)}
#amt-blog .amt-search{min-width:220px;margin-left:auto;border-bottom:1px solid #b8b8b2}
#amt-blog .amt-search input{width:100%;padding:8px 0;border:0;outline:0;background:transparent;font-family:inherit;font-size:14px;color:var(--amt-ink)}

/* SECTION HEAD */
#amt-blog .amt-section__head{margin-bottom:48px;display:flex;justify-content:space-between;gap:30px;align-items:end}
#amt-blog .amt-section__copy{max-width:940px}

/* MEDIA IMAGE */
#amt-blog .amt-media{position:relative;overflow:hidden;border-radius:20px;background:radial-gradient(circle at 70% 30%,rgba(255,115,0,.12),transparent 24%),linear-gradient(135deg,#e7e7e3,#cdcdc7);min-height:230px}
#amt-blog .amt-media__label{position:absolute;left:16px;bottom:16px;padding:7px 12px;border-radius:var(--amt-pill);background:rgba(255,255,255,.92);color:#444;font-size:11px;font-weight:600;letter-spacing:.02em}

/* FEATURED */
#amt-blog .amt-featured{display:grid;grid-template-columns:1.55fr .9fr;gap:20px}
#amt-blog .amt-featured__main{padding:14px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-featured__main:hover{transform:translateY(-3px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-featured__main .amt-media{min-height:400px;border-radius:18px}
#amt-blog .amt-featured__main .amt-card__body{padding:22px 16px 14px}
#amt-blog .amt-featured__main h3{max-width:850px;font-size:clamp(28px,3vw,44px);font-weight:760;line-height:1.1;letter-spacing:-.035em;margin:0 0 14px}
#amt-blog .amt-featured__main p{color:var(--amt-text);margin:0}
#amt-blog .amt-featured__side{display:grid;gap:18px}
#amt-blog .amt-mini{min-height:168px;padding:10px;display:grid;grid-template-columns:165px 1fr;gap:16px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-mini:hover{transform:translateY(-2px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-mini .amt-media{min-height:146px;border-radius:14px}
#amt-blog .amt-mini .amt-card__body{padding:8px 8px 8px 0;align-self:center}
#amt-blog .amt-mini h3{margin:0 0 8px;font-size:17px;font-weight:760;line-height:1.28;letter-spacing:-.02em}
#amt-blog .amt-label{display:block;margin-bottom:10px;color:var(--amt-orange);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}
#amt-blog .amt-meta{margin-top:16px;display:flex;gap:14px;flex-wrap:wrap;color:var(--amt-muted);font-size:12px;font-weight:600}
#amt-blog .amt-meta span:last-child{color:var(--amt-orange)}

/* DIRECTIONS */
#amt-blog .amt-directions{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
#amt-blog .amt-direction{min-height:230px;padding:28px 30px;display:flex;flex-direction:column;justify-content:space-between;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-direction:hover{transform:translateY(-3px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-direction__index{color:#7a7a75;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
#amt-blog .amt-direction__index span{color:var(--amt-orange);font-weight:800}
#amt-blog .amt-direction h3{margin:0 0 12px;font-size:24px;font-weight:760;letter-spacing:-.025em;line-height:1.15}
#amt-blog .amt-direction p{max-width:520px;margin:0;color:var(--amt-text);font-size:14px;line-height:1.55}

/* ARTICLES GRID */
#amt-blog .amt-articles{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
#amt-blog .amt-article{padding:12px;display:flex;flex-direction:column;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-article:hover{transform:translateY(-3px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-article .amt-media{min-height:220px;border-radius:16px}
#amt-blog .amt-article__body{flex:1;padding:20px 10px 10px;display:flex;flex-direction:column}
#amt-blog .amt-article h3{font-size:22px;font-weight:760;letter-spacing:-.02em;line-height:1.22;margin:0 0 10px}
#amt-blog .amt-article p{color:var(--amt-text);font-size:14px;line-height:1.55;margin:0}
#amt-blog .amt-article .amt-meta{margin-top:auto}
#amt-blog .amt-loadmore{margin-top:44px;display:flex;justify-content:center}

/* CASE */
#amt-blog .amt-case{padding:14px;display:grid;grid-template-columns:1.12fr .88fr;gap:20px;border:1px solid var(--amt-line);border-radius:28px;background:#fff;box-shadow:var(--amt-shadow)}
#amt-blog .amt-case .amt-media{min-height:520px;border-radius:20px}
#amt-blog .amt-case__content{padding:clamp(28px,4vw,60px);align-self:center}
#amt-blog .amt-case__content h3{margin:0 0 16px;font-size:clamp(24px,2.5vw,32px);font-weight:760;letter-spacing:-.03em;line-height:1.15}
#amt-blog .amt-case__content p{color:var(--amt-text);font-size:15px;line-height:1.65;margin:0}
#amt-blog .amt-stats{margin:34px 0;display:grid;grid-template-columns:repeat(2,1fr);gap:22px}
#amt-blog .amt-stat{padding-top:14px;border-top:1px solid var(--amt-line)}
#amt-blog .amt-stat b{display:block;margin-bottom:4px;font-size:22px;font-weight:760;letter-spacing:-.02em}
#amt-blog .amt-stat span{color:var(--amt-muted);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em}

/* NEWS LIST */
#amt-blog .amt-newslist{border-top:1px solid var(--amt-line)}
#amt-blog .amt-newsitem{min-height:100px;padding:0 14px;display:grid;grid-template-columns:150px 1fr auto;gap:26px;align-items:center;border-bottom:1px solid var(--amt-line);border-radius:14px;transition:background .2s ease}
#amt-blog .amt-newsitem:hover{background:var(--amt-surface)}
#amt-blog .amt-newsitem time{color:var(--amt-muted);font-size:13px;font-weight:600}
#amt-blog .amt-newsitem h3{margin:0;font-size:clamp(18px,1.6vw,26px);font-weight:760;letter-spacing:-.02em;line-height:1.25}
#amt-blog .amt-newsitem__arrow{color:var(--amt-orange);font-size:22px}

/* VIDEO */
#amt-blog .amt-videos{display:grid;grid-template-columns:1.45fr .8fr;gap:18px}
#amt-blog .amt-videomain{padding:12px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-videomain:hover{transform:translateY(-3px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-videomain .amt-media{min-height:400px;border-radius:18px}
#amt-blog .amt-videomain .amt-card__body{padding:20px 10px 10px}
#amt-blog .amt-videomain h3{margin:0 0 10px;font-size:24px;font-weight:760;line-height:1.2;letter-spacing:-.025em}
#amt-blog .amt-videoside{display:grid;gap:18px}
#amt-blog .amt-videocard{padding:12px;display:grid;grid-template-columns:165px 1fr;gap:16px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
#amt-blog .amt-videocard:hover{transform:translateY(-2px);box-shadow:var(--amt-shadow);border-color:transparent}
#amt-blog .amt-videocard .amt-media{min-height:146px;border-radius:14px}
#amt-blog .amt-videocard .amt-card__body{align-self:center;padding-right:8px}
#amt-blog .amt-videocard h3{margin:0;font-size:15px;font-weight:760;line-height:1.3;letter-spacing:-.02em}
#amt-blog .amt-play{position:absolute;inset:0;display:grid;place-items:center;color:#fff;pointer-events:none}
#amt-blog .amt-play::before{content:"";width:64px;height:64px;border-radius:50%;background:rgba(255,115,0,.92);display:block}
#amt-blog .amt-play::after{content:"";position:absolute;width:0;height:0;border-left:16px solid #fff;border-top:10px solid transparent;border-bottom:10px solid transparent}
#amt-blog .amt-videocard .amt-play::before{width:44px;height:44px}
#amt-blog .amt-videocard .amt-play::after{border-left-width:11px;border-top-width:7px;border-bottom-width:7px}

/* CTA — dark */
#amt-blog .amt-cta__grid{display:grid;grid-template-columns:1.25fr .75fr;gap:64px;align-items:end}
#amt-blog .amt-cta .amt-eyebrow{color:#ffb471}
#amt-blog .amt-cta .amt-title{color:#fff}
#amt-blog .amt-cta .amt-lead{color:#cacac6}
#amt-blog .amt-cta__actions{display:flex;gap:10px;flex-wrap:wrap}
#amt-blog .amt-contacts{margin-top:28px;display:flex;gap:26px;flex-wrap:wrap;color:#aaa;font-size:14px}

/* Full-bleed для страницы Блог (post 1733) */

@media(max-width:1100px){
  #amt-blog .amt-hero__grid,#amt-blog .amt-featured,#amt-blog .amt-case,#amt-blog .amt-videos,#amt-blog .amt-cta__grid{grid-template-columns:1fr}
  #amt-blog .amt-hero__aside{padding-left:0;border-left:0}
  #amt-blog .amt-articles{grid-template-columns:repeat(2,1fr)}
  #amt-blog .amt-case .amt-media{min-height:420px}
  #amt-blog .amt-videomain .amt-media{min-height:320px}
}
@media(max-width:760px){
  #amt-blog .amt-section{padding:76px 0}
  #amt-blog .amt-hero{padding:64px 0 44px}
  #amt-blog .amt-trust{grid-template-columns:1fr;margin-top:38px}
  #amt-blog .amt-trust__item,#amt-blog .amt-trust__item:not(:first-child){min-height:auto;padding:16px 0;border-right:0;border-bottom:1px solid var(--amt-line)}
  #amt-blog .amt-search{display:none}
  #amt-blog .amt-featured__main .amt-media{min-height:260px}
  #amt-blog .amt-mini,#amt-blog .amt-videocard{grid-template-columns:1fr}
  #amt-blog .amt-mini .amt-media,#amt-blog .amt-videocard .amt-media{min-height:200px}
  #amt-blog .amt-directions,#amt-blog .amt-articles{grid-template-columns:1fr}
  #amt-blog .amt-newsitem{grid-template-columns:1fr auto;gap:8px 14px;padding:16px 12px}
  #amt-blog .amt-newsitem time{grid-column:1/-1}
  #amt-blog .amt-section__head{flex-direction:column;align-items:flex-start;gap:16px}
  #amt-blog .amt-cta__actions .amt-button{width:100%}
}
/* --- */
#amt-blog [data-amt-grid]{position:relative;min-height:200px;transition:opacity .18s ease}
#amt-blog [data-amt-grid].is-loading{opacity:.35;pointer-events:none}
#amt-blog [data-amt-grid].is-loading::after{content:"";position:absolute;top:80px;left:50%;transform:translateX(-50%);width:34px;height:34px;border-radius:50%;border:3px solid rgba(255,115,0,.25);border-top-color:var(--amt-orange);animation:amtSpin .8s linear infinite}
@keyframes amtSpin{to{transform:translateX(-50%) rotate(360deg)}}
#amt-blog .amt-article,#amt-blog .amt-empty{animation:amtFade .32s ease both}
@keyframes amtFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
/* --- Страница статьи и кейса (single-news.php / single-cases.php) --- */
/* Вариант «б»: шрифт сайта Montserrat, заголовки легче, чем в ленте блога (как в макете). */
#amt-blog.amt-single .amt-title--section{font-weight:600;letter-spacing:-.02em}
#amt-blog .amt-shero{padding:56px 0 48px}
/* Крошки: тонкие, серые, со стрелками вместо синих черточек темы */
#amt-blog .amt-crumbs{margin-bottom:34px;font-size:13px;font-weight:400;line-height:1.5;color:rgba(23,23,23,.45);text-transform:none;letter-spacing:0}
#amt-blog .amt-crumbs a{color:inherit;transition:color .2s}
#amt-blog .amt-crumbs a:hover{color:var(--amt-orange)}
#amt-blog .amt-crumbs svg{display:none}
#amt-blog .amt-crumbs span > span ~ span::before{content:"→";display:inline-block;margin:0 10px;font-size:14px}
#amt-blog .amt-crumbs .breadcrumb_last,#amt-blog .amt-crumbs *{color:inherit!important;font-weight:400!important;text-transform:none!important;letter-spacing:0!important;font-size:13px}
#amt-blog .amt-shero__grid{max-width:1180px}
#amt-blog .amt-shero .amt-label{display:inline-block;margin-bottom:16px}
#amt-blog .amt-shero__title{margin:0 0 26px;font-size:clamp(34px,4.6vw,72px);line-height:1.04;font-weight:500;letter-spacing:-.035em;color:var(--amt-ink);max-width:1180px}
#amt-blog .amt-shero__lead{margin:0 0 30px;max-width:900px;font-size:clamp(17px,1.5vw,22px);line-height:1.5;color:#343432}
#amt-blog .amt-shero__meta{display:flex;flex-wrap:wrap;gap:14px 34px;align-items:center}
#amt-blog .amt-author{display:flex;gap:12px;align-items:center}
#amt-blog .amt-author img{width:48px;height:48px;border-radius:50%;object-fit:cover}
#amt-blog .amt-author b{display:block;font-size:14px;font-weight:600}
#amt-blog .amt-author span{display:block;font-size:12px;color:var(--amt-muted)}
#amt-blog .amt-shero__dates{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:13px;color:var(--amt-muted)}

#amt-blog .amt-facts{margin-top:42px;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0;border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);background:#fff;overflow:hidden}
#amt-blog .amt-fact{padding:18px 20px;border-right:1px solid var(--amt-line);border-bottom:1px solid var(--amt-line);margin:0 -1px -1px 0}
#amt-blog .amt-fact span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--amt-muted);margin-bottom:6px}
#amt-blog .amt-fact b{display:block;font-size:15px;font-weight:600;line-height:1.35;color:var(--amt-ink)}
#amt-blog .amt-fact a{color:var(--amt-orange)}

#amt-blog .amt-sbody{padding-top:56px}
#amt-blog .amt-sbody__grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:64px;align-items:stretch}
#amt-blog.amt-single--wide .amt-sbody__grid{grid-template-columns:minmax(0,1fr)}
#amt-blog.amt-single--wide .amt-sidebar{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;align-items:start}
#amt-blog.amt-single--wide .amt-sticky{position:static}

/* Типографика текста. :where() — нулевая специфичность, чтобы не ломать статьи со своей вёрсткой. */
#amt-blog .amt-prose{max-width:780px;font-size:17px;line-height:1.7;color:#2b2b2b;min-width:0}
#amt-blog.amt-single--wide .amt-prose{max-width:none}
#amt-blog .amt-prose :where(p){margin:0 0 1.1em}
#amt-blog .amt-prose :where(p:empty){display:none}
#amt-blog .amt-prose :where(h2){margin:1.9em 0 .6em;font-size:clamp(24px,2.2vw,30px);line-height:1.2;font-weight:600;letter-spacing:-.015em;color:var(--amt-ink);scroll-margin-top:110px}
#amt-blog .amt-prose :where(h3){margin:1.5em 0 .5em;font-size:21px;line-height:1.3;font-weight:600;color:var(--amt-ink)}
#amt-blog .amt-prose :where(h4){margin:1.3em 0 .4em;font-size:18px;font-weight:600}
#amt-blog .amt-prose :where(ul,ol){margin:0 0 1.2em;padding-left:1.3em}
#amt-blog .amt-prose :where(li){margin-bottom:.4em}
#amt-blog .amt-prose :where(li)::marker{color:var(--amt-orange)}
#amt-blog .amt-prose :where(a){color:var(--amt-orange);text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
#amt-blog .amt-prose :where(img){max-width:100%;height:auto;border-radius:var(--amt-radius-md)}
#amt-blog .amt-prose :where(figure){margin:1.6em 0}
#amt-blog .amt-prose :where(figcaption){font-size:13px;color:var(--amt-muted);margin-top:8px}
#amt-blog .amt-prose :where(blockquote){margin:1.6em 0;padding:18px 24px;border-left:3px solid var(--amt-orange);background:var(--amt-orange-soft);border-radius:0 var(--amt-radius-md) var(--amt-radius-md) 0}
#amt-blog .amt-prose :where(table){width:100%;border-collapse:collapse;font-size:15px;margin:0 0 1.4em}
#amt-blog .amt-prose :where(th,td){padding:10px 12px;border-bottom:1px solid var(--amt-line);text-align:left;vertical-align:top}
#amt-blog .amt-prose :where(th){background:var(--amt-surface);font-weight:600}
#amt-blog .amt-prose :where(iframe,video){max-width:100%;border-radius:var(--amt-radius-md)}
#amt-blog .amt-prose :where(.boldgrid-section .container,.container){width:auto;max-width:none;padding-left:0;padding-right:0}
#amt-blog .amt-prose :where(.row){margin-left:0;margin-right:0}

#amt-blog .amt-toc--mobile{display:none}
#amt-blog .amt-toc ol{margin:0;padding:0;list-style:none;counter-reset:toc}
#amt-blog .amt-toc li{counter-increment:toc;margin:0;border-top:1px solid var(--amt-line)}
#amt-blog .amt-toc li a{display:flex;gap:10px;padding:9px 0;font-size:13.5px;line-height:1.4;color:var(--amt-text);text-decoration:none}
#amt-blog .amt-toc li a::before{content:counter(toc,decimal-leading-zero);color:var(--amt-orange);font-weight:600;font-size:12px;padding-top:1px}
#amt-blog .amt-toc li a:hover,#amt-blog .amt-toc li a.is-active{color:var(--amt-ink)}
#amt-blog .amt-toc li a.is-active{font-weight:600}

#amt-blog .amt-inline-cta{margin-top:2.4em;padding:24px 26px;display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:var(--amt-surface);font-size:15px;line-height:1.5}
#amt-blog .amt-inline-cta b{display:block;font-size:18px;margin-bottom:4px;color:var(--amt-ink)}
#amt-blog .amt-inline-cta span{color:var(--amt-text)}
#amt-blog .amt-inline-cta .amt-button{text-decoration:none;color:#fff}

#amt-blog .amt-sidebar{display:flex;flex-direction:column;gap:24px;min-width:0}
#amt-blog .amt-sblock{padding:22px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff}
#amt-blog .amt-sblock .amt-eyebrow{margin-bottom:14px}
#amt-blog .amt-sticky{position:sticky;top:110px;display:flex;flex-direction:column;gap:24px}
#amt-blog .amt-product{display:grid;grid-template-columns:84px 1fr;gap:4px 14px;align-items:center;padding:10px 0;border-top:1px solid var(--amt-line)}
#amt-blog .amt-product:first-of-type{border-top:0}
#amt-blog .amt-product__img{grid-row:span 2;width:84px;height:84px;border-radius:14px;background:var(--amt-surface);display:flex;align-items:center;justify-content:center;overflow:hidden}
#amt-blog .amt-product__img img{max-width:90%;max-height:90%;width:auto;height:auto;object-fit:contain}
#amt-blog .amt-product__title{font-size:14px;font-weight:600;line-height:1.35;color:var(--amt-ink)}
#amt-blog .amt-product__more{font-size:12px;color:var(--amt-orange)}
#amt-blog .amt-product:hover .amt-product__title{color:var(--amt-orange)}
#amt-blog .amt-ask p{margin:0 0 12px;font-size:14px;color:var(--amt-text)}
#amt-blog .amt-ask__phone{display:block;margin-bottom:14px;font-size:18px;font-weight:600}
#amt-blog .amt-ask .amt-button{width:100%;justify-content:center}
/* «Популярное» выводит функция темы random_news() со своими стилями — подгоняем рамку и заголовок */
#amt-blog .amt-popular .vp-sidebar-block{margin:0;padding:0;border:0}
#amt-blog .amt-popular .vp-sidebar-title{margin:0 0 14px!important;font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;color:var(--amt-ink)}

#amt-blog .amt-sform{border-top:1px solid var(--amt-line)}
#amt-blog .amt-sform .amt-lead a{color:var(--amt-orange)}

/* Форма Bitrix24 (стили перенесены из старого single-news.php) */
.custom-contactblock hr.contact_hr,.custom-contactblock hr.contact_hr~*{display:none}
.custom-contactblock .b24-form-field-company-name{display:none}
.custom-contactblock .b24-form-field.b24-form-field-email.b24-form-control-string{display:none}
.custom-contactblock label.b24-form-control-container{display:flex;align-items:flex-start;justify-content:flex-start;gap:10px}
.custom-contactblock .b24-form-control-agreement input{height:auto!important}
.custom-contactblock .b24-form-control-agreement .b24-form-control-required{display:none}
.custom-contactblock .b24-form-header-padding{display:none}
.custom-contactblock .b24-form-sign{display:none}
.custom-contactblock .b24-form-content.b24-form-padding-side{border:none!important}
.custom-contactblock .b24-form-wrapper.b24-form-border-bottom{border:none}
@media screen and (min-width:1024px){.b24-form-content.b24-form-padding-side>form>div{justify-content:space-between}}

@media(max-width:1100px){
  #amt-blog .amt-sbody__grid{grid-template-columns:minmax(0,1fr)}
  #amt-blog .amt-sticky{position:static}
  #amt-blog .amt-sidebar{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));align-items:start}
  #amt-blog .amt-sidebar .amt-toc:not(.amt-toc--mobile){display:none}
  #amt-blog .amt-toc--mobile{display:block;margin-bottom:28px;padding:16px 20px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);background:var(--amt-surface)}
  #amt-blog .amt-toc--mobile summary{cursor:pointer;font-weight:600;font-size:15px}
  #amt-blog .amt-toc--mobile ol{margin-top:12px}
}
@media(max-width:760px){
  #amt-blog .amt-shero{padding:36px 0 32px}
  #amt-blog .amt-prose{font-size:16px}
  #amt-blog .amt-sidebar{grid-template-columns:1fr}
  #amt-blog .amt-facts{grid-template-columns:1fr 1fr}
  #amt-blog .amt-fact{padding:14px 14px}
  #amt-blog .amt-inline-cta .amt-button{width:100%;justify-content:center}
}
/* Шапка сайта фиксированная: отступ сверху как в блоге (у видеообзоров тема его обнуляет) */
body.single-news,body.single-cases,body.single-video_reviews{padding-top:12vh!important}

/* --- Карточка товара (woocommerce/single-product.php) --- */
body.single-product{padding-top:12vh!important}
body.single-product #amt-blog{width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important}
html:has(body.single-product),body.single-product{overflow-x:clip!important}

#amt-blog .amt-phero{padding:40px 0 56px}
#amt-blog .amt-phero__grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:56px;align-items:start}
#amt-blog .amt-gallery{min-width:0}
#amt-blog .amt-gallery .main-swiper{width:100%;aspect-ratio:4/3;height:auto;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;overflow:hidden;margin:0}
#amt-blog .amt-gallery .main-swiper .swiper-slide{display:flex;align-items:center;justify-content:center;border:0;border-radius:0;background:#fff}
#amt-blog .amt-gallery .slide-image{width:100%;height:100%!important;object-fit:contain;padding:24px}
#amt-blog .amt-gallery .thumbs-swiper{margin-top:12px}
#amt-blog .amt-gallery .thumbs-swiper .swiper-slide{aspect-ratio:1/1;height:auto;border:1px solid var(--amt-line);border-radius:14px;overflow:hidden;opacity:.6;cursor:pointer;background:#fff;transition:opacity .2s,border-color .2s}
#amt-blog .amt-gallery .thumbs-swiper .swiper-slide-thumb-active,#amt-blog .amt-gallery .thumbs-swiper .swiper-slide:hover{opacity:1;border-color:var(--amt-orange)}
#amt-blog .amt-gallery .thumb-image{width:100%;height:100%!important;object-fit:contain;padding:6px;max-height:none;border-radius:0}

#amt-blog .amt-pinfo{min-width:0}
#amt-blog .amt-pinfo .amt-label{display:inline-block;margin-bottom:14px}
#amt-blog .amt-pinfo__title{font-size:clamp(30px,3.2vw,48px)!important;margin-bottom:24px!important}
#amt-blog .amt-pkpis{margin:0 0 22px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);overflow:hidden;background:#fff}
#amt-blog .amt-pkpis>div{padding:14px 16px;border-right:1px solid var(--amt-line);border-bottom:1px solid var(--amt-line);margin:0 -1px -1px 0}
#amt-blog .amt-pkpis dt{font-size:11px;font-weight:500;letter-spacing:.04em;text-transform:uppercase;color:var(--amt-muted);margin-bottom:4px;line-height:1.35}
#amt-blog .amt-pkpis dd{margin:0;font-size:17px;font-weight:600;color:var(--amt-ink);line-height:1.3}
#amt-blog .amt-pshort{font-size:15px;line-height:1.6;color:var(--amt-text);margin-bottom:22px}
#amt-blog .amt-pshort p{margin:0 0 .6em}
#amt-blog .amt-pshort a{color:var(--amt-orange)}
#amt-blog .amt-pbuy{padding-top:20px;border-top:1px solid var(--amt-line)}
#amt-blog .amt-pprice{font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--amt-ink);margin-bottom:14px}
#amt-blog .amt-pbuttons{display:flex;flex-wrap:wrap;gap:10px}
#amt-blog .amt-pbuttons .amt-button{cursor:pointer;font-family:inherit;margin:0;width:auto}

/* Якорное меню: липкое под шапкой сайта */
#amt-blog .amt-pnav{position:sticky;top:var(--amt-pnav-top,70px);z-index:20;background:rgba(255,255,255,.96);backdrop-filter:blur(10px)}
#amt-blog .amt-psec{scroll-margin-top:140px}
#amt-blog .amt-psec .amt-section__head{margin-bottom:28px}

/* Таблица характеристик */
#amt-blog .amt-pspecs{border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;overflow:auto}
#amt-blog .amt-pspecs table{width:100%;border-collapse:collapse;font-size:15px}
#amt-blog .amt-pspecs th,#amt-blog .amt-pspecs td{padding:14px 22px;border-bottom:1px solid var(--amt-line);text-align:left;vertical-align:top;line-height:1.45}
#amt-blog .amt-pspecs tr:last-child th,#amt-blog .amt-pspecs tr:last-child td{border-bottom:0}
#amt-blog .amt-pspecs th{width:46%;font-weight:500;color:var(--amt-text)}
#amt-blog .amt-pspecs td{font-weight:600;color:var(--amt-ink)}

#amt-blog .amt-pvideo{border-radius:var(--amt-radius-lg);overflow:hidden;background:#0b0d12;max-width:1000px}
#amt-blog .amt-pvideo video{display:block;width:100%;aspect-ratio:16/9;background:#0b0d12}
#amt-blog .amt-pdoc{display:inline-flex;align-items:center;gap:16px;padding:18px 22px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);background:#fff;max-width:640px}
#amt-blog .amt-pdoc:hover{border-color:var(--amt-orange)}
#amt-blog .amt-pdoc__ext{flex:0 0 auto;padding:8px 10px;border-radius:10px;background:var(--amt-orange-soft);color:var(--amt-orange);font-size:12px;font-weight:700}
#amt-blog .amt-pdoc__name{font-weight:600;color:var(--amt-ink)}
#amt-blog .amt-pdoc__more{margin-left:auto;font-size:13px;color:var(--amt-orange);white-space:nowrap}

#amt-blog .amt-pmodels{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
#amt-blog .amt-pmodel{display:flex;flex-direction:column;gap:10px;padding:12px 12px 18px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff;transition:transform .2s,border-color .2s}
#amt-blog .amt-pmodel:hover{transform:translateY(-2px);border-color:#c8c8c2}
#amt-blog .amt-pmodel__img{aspect-ratio:4/3;border-radius:16px;background:var(--amt-surface);display:flex;align-items:center;justify-content:center;overflow:hidden}
#amt-blog .amt-pmodel__img img{max-width:88%;max-height:88%;width:auto;height:auto;object-fit:contain}
#amt-blog .amt-pmodel__title{padding:0 6px;font-size:16px;font-weight:600;line-height:1.3;color:var(--amt-ink)}
#amt-blog .amt-pmodel__more{padding:0 6px;font-size:13px;color:var(--amt-orange)}

/* Блоки темы внутри новой карточки */
#amt-blog .amt-ppartners{padding:64px 0 24px}
#amt-blog .amt-ppartners .partners-section{padding:0;margin:0}
#amt-blog .amt-pcatvideo .product-video-block{display:flex;gap:40px;align-items:center;flex-wrap:wrap}
#amt-blog .amt-pcatvideo video{width:100%;border-radius:var(--amt-radius-lg)}
#amt-blog .amt-pcatvideo .video-section,#amt-blog .amt-pcatvideo .text-section{min-width:280px}
#amt-blog .amt-pnews{padding-top:64px}
#amt-form{padding:64px 0 0;scroll-margin-top:110px}

/* ===== Компоненты описания amt-pd-* (стандарт описаний) ===== */
#amt-blog .amt-pd{max-width:960px}
#amt-blog .amt-pd .amt-pd-lead{font-size:clamp(18px,1.6vw,22px);line-height:1.55;color:#2b2b2b;margin:0 0 28px;max-width:900px}
#amt-blog .amt-pd .amt-pd-kpis{margin:0 0 36px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid var(--amt-line)}
#amt-blog .amt-pd .amt-pd-kpis>div{padding:18px 18px 0 0}
#amt-blog .amt-pd .amt-pd-kpis>div+div{padding-left:18px;border-left:1px solid var(--amt-line)}
#amt-blog .amt-pd .amt-pd-kpis dt{order:2;font-size:13px;color:var(--amt-muted);font-weight:400}
#amt-blog .amt-pd .amt-pd-kpis dd{margin:0 0 4px;font-size:22px;font-weight:600;color:var(--amt-ink)}
#amt-blog .amt-pd .amt-pd-kpis>div{display:flex;flex-direction:column}
#amt-blog .amt-pd .amt-pd-kpis dd{order:1}
#amt-blog .amt-pd .amt-pd-uses{list-style:none;padding:0;margin:0 0 1.4em;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
#amt-blog .amt-pd .amt-pd-uses li{margin:0;padding:16px 18px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-md);background:#fff;font-size:15px;line-height:1.55}
#amt-blog .amt-pd .amt-pd-uses li::marker{content:none}
#amt-blog .amt-pd .amt-pd-uses b{display:block;color:var(--amt-ink);margin-bottom:4px}
#amt-blog .amt-pd .amt-pd-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0 0 1.6em;counter-reset:pd}
#amt-blog .amt-pd .amt-pd-cards>div{counter-increment:pd;padding:20px 20px 16px;border:1px solid var(--amt-line);border-radius:var(--amt-radius-lg);background:#fff}
#amt-blog .amt-pd .amt-pd-cards>div::before{content:counter(pd,decimal-leading-zero);display:inline-flex;width:34px;height:34px;align-items:center;justify-content:center;border-radius:50%;background:var(--amt-orange-soft);color:var(--amt-orange);font-size:12px;font-weight:700;margin-bottom:12px}
#amt-blog .amt-pd .amt-pd-cards h3{margin:0 0 8px;font-size:18px}
#amt-blog .amt-pd .amt-pd-cards p{margin:0;font-size:15px;line-height:1.55;color:var(--amt-text)}
#amt-blog .amt-pd .amt-pd-faq details{border-top:1px solid var(--amt-line);padding:16px 0}
#amt-blog .amt-pd .amt-pd-faq details:last-child{border-bottom:1px solid var(--amt-line)}
#amt-blog .amt-pd .amt-pd-faq summary{cursor:pointer;font-weight:600;font-size:17px;color:var(--amt-ink);list-style:none;display:flex;justify-content:space-between;gap:16px}
#amt-blog .amt-pd .amt-pd-faq summary::-webkit-details-marker{display:none}
#amt-blog .amt-pd .amt-pd-faq summary::after{content:"+";color:var(--amt-orange);font-size:22px;line-height:1}
#amt-blog .amt-pd .amt-pd-faq details[open] summary::after{content:"–"}
#amt-blog .amt-pd .amt-pd-faq details p{margin:10px 0 0;font-size:15px}
#amt-blog .amt-pd .amt-pd-cta{margin-top:2em;padding:24px 26px;display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap;border-radius:var(--amt-radius-lg);background:var(--amt-ink);color:#fff}
#amt-blog .amt-pd .amt-pd-cta p{margin:0;color:#e9e9e9;font-size:16px;max-width:620px}
#amt-blog .amt-pd .amt-pd-cta a{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border-radius:999px;background:var(--amt-orange);color:#fff;text-decoration:none;font-weight:600;font-size:14px}

@media(max-width:1100px){
  #amt-blog .amt-phero__grid{grid-template-columns:1fr;gap:28px}
  #amt-blog .amt-pmodels{grid-template-columns:repeat(2,minmax(0,1fr))}
  #amt-blog .amt-pd .amt-pd-cards{grid-template-columns:repeat(2,minmax(0,1fr))}
  #amt-blog .amt-pd .amt-pd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  #amt-blog .amt-pd .amt-pd-kpis>div:nth-child(3){padding-left:0;border-left:0}
}
@media(max-width:760px){
  #amt-blog .amt-phero{padding:24px 0 36px}
  #amt-blog .amt-pkpis dd{font-size:15px}
  #amt-blog .amt-pbuttons .amt-button{flex:1 1 calc(50% - 5px);justify-content:center;padding-left:12px;padding-right:12px}
  #amt-blog .amt-pbuttons .amt-button--primary{flex-basis:100%}
  #amt-blog .amt-pspecs th,#amt-blog .amt-pspecs td{display:block;width:auto;padding:4px 16px}
  #amt-blog .amt-pspecs th{padding-top:14px;border-bottom:0;font-size:13px}
  #amt-blog .amt-pspecs td{padding-bottom:14px}
  #amt-blog .amt-pmodels{grid-template-columns:1fr 1fr;gap:10px}
  #amt-blog .amt-pmodel__title{font-size:14px}
  #amt-blog .amt-pd .amt-pd-uses,#amt-blog .amt-pd .amt-pd-cards{grid-template-columns:1fr}
  #amt-blog .amt-pdoc{display:flex}
}
/* Запасные стили галереи, если стили Swiper не загрузились (CDN недоступен): слайды не встают столбиком */
#amt-blog .amt-gallery .swiper{position:relative;overflow:hidden}
#amt-blog .amt-gallery .swiper-wrapper{display:flex;box-sizing:content-box}
#amt-blog .amt-gallery .main-swiper .swiper-slide{flex-shrink:0;width:100%;height:100%}
#amt-blog .amt-gallery .thumbs-swiper .swiper-slide{flex-shrink:0;width:calc(25% - 6px);margin-right:8px}
</style>
<script>
jQuery(function ($) {
	if (typeof Swiper === 'undefined') return;
	var thumbs = document.querySelector('#amt-blog .thumbs-swiper') ? new Swiper('#amt-blog .thumbs-swiper', {
		spaceBetween: 8, slidesPerView: 4, freeMode: true, watchSlidesProgress: true,
		breakpoints: { 320: { slidesPerView: 4, spaceBetween: 6 }, 768: { slidesPerView: 5, spaceBetween: 8 } }
	}) : null;
	new Swiper('#amt-blog .main-swiper', {
		spaceBetween: 0, speed: 400, keyboard: { enabled: true }, grabCursor: true,
		observer: true, observeParents: true, thumbs: thumbs ? { swiper: thumbs } : undefined
	});
});
(function () {
	/* Якорное меню: подсветка текущего раздела */
	var links = document.querySelectorAll('#amt-blog .amt-pnav a');
	if (links.length && 'IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) {
				if (!e.isIntersecting) return;
				links.forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('href') === '#' + e.target.id); });
			});
		}, { rootMargin: '-140px 0px -60% 0px' });
		links.forEach(function (a) { var s = document.getElementById(a.getAttribute('href').slice(1)); if (s) io.observe(s); });
	}
	/* Старые описания со своей вёрсткой: на телефоне сетки и ряды перестраиваем в одну колонку */
	function oneColumn() {
		if (window.innerWidth > 760) return;
		document.querySelectorAll('#amt-blog .amt-pd *').forEach(function (el) {
			var cs = getComputedStyle(el);
			if (cs.display === 'grid' || cs.display === 'inline-grid') { el.style.gridTemplateColumns = 'minmax(0,1fr)'; el.style.columnGap = '0'; }
			if ((cs.display === 'flex' || cs.display === 'inline-flex') && cs.flexDirection.indexOf('row') === 0) { el.style.flexWrap = 'wrap'; }
			if (el.children.length) { for (var i = 0; i < el.children.length; i++) { el.children[i].style.minWidth = '0'; } }
		});
	}
	oneColumn();
	window.addEventListener('resize', oneColumn);
})();
</script>
<?php
get_footer();
