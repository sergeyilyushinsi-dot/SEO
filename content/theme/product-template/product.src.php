<?php
/**
 * Карточка товара в дизайне блога — woocommerce/single-product.php.
 *
 * Сохранено из старого шаблона: шапка сайта (header-nav), крошки Yoast, галерея Swiper, первые атрибуты,
 * краткое описание, кнопки «Запросить КП» / «Заказать звонок» / «Демонстрация» с формами Bitrix24
 * по категориям (разметка модальных окон та же — их открывает скрипт темы), описание, «Преимущества»
 * (_custom_advantages), характеристики (видимые атрибуты), видео («Файл видео»), документы («Документы товара»),
 * [partners_display], видеоблок категории (display_video_block), [recommended_news], footer-nav, форма Elementor 3699.
 * Новое: у принтеров кнопка «Заказать печать» ведёт к калькулятору на странице услуги, у сканеров
 * «Демонстрация» — к форме заявки; всё на одной странице с якорным меню вместо вкладок, «Модели линейки» (свои карточки вместо
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

/* Третья кнопка: у принтеров — «Заказать печать» (калькулятор на странице услуги), у остальных — «Демонстрация».
   Услуги печати есть для металла (SLM, EBM; MBJ — тоже печать металлом) и для SLA; у керамики услуги нет. */
$amt_cat_slugs = array();
foreach ( $amt_categories as $c ) {
	$amt_cat_slugs[] = $c->slug;
	foreach ( get_ancestors( $c->term_id, 'product_cat' ) as $anc_id ) {
		$anc = get_term( $anc_id, 'product_cat' );
		if ( $anc && ! is_wp_error( $anc ) ) {
			$amt_cat_slugs[] = $anc->slug;
		}
	}
}
$amt_print_url = '';
if ( array_intersect( $amt_cat_slugs, array( 'slm-3d-printeri', 'ebm-3d-print', 'mbj-3d-print', 'promyshlennye-3d-printery-po-pechati-metallom' ) ) ) {
	$amt_print_url = home_url( '/uslugi/3d-pechat-metallom/#calc' );
} elseif ( in_array( 'sla-3d-printery', $amt_cat_slugs, true ) ) {
	$amt_print_url = home_url( '/uslugi/sla-pechat/#calc' );
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
							<?php if ( $amt_print_url ) : ?>
								<a class="amt-button amt-button--secondary" href="<?php echo esc_url( $amt_print_url ); ?>" title="Расчёт стоимости печати по 3D-модели">Заказать печать</a>
							<?php else : ?>
								<a class="amt-button amt-button--secondary" href="#amt-form">Демонстрация</a>
							<?php endif; ?>
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
/*AMT_CSS*/
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
