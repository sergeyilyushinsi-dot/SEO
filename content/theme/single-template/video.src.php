<?php
/**
 * Отдельный видеообзор (video_reviews) в дизайне блога — single-video_reviews.php.
 *
 * Сохранено из старого шаблона: шапка сайта (header-nav), крошки Yoast, автор, дата, плеер, описание
 * и «Рекомендованные видео» (display_video_post_page), «Из блога» ([random_blogs]),
 * «Оборудование по теме» (ACF «товары_в_этой_записи»).
 * Новое: светлая шапка как у статей, «Вопрос инженеру», форма связи (Elementor 3699), разметка VideoObject.
 * Если поле ACF пустое, «Оборудование по теме» собирается из ссылок на товары и разделы каталога в описании.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*AMT_COMMON*/

get_header();
get_template_part( 'header-nav' );

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$amt_id          = get_the_ID();
		$amt_term        = amt_single_term( $amt_id, 'video_category' );
		$amt_author_id   = (int) get_the_author_meta( 'ID' );
		$amt_author_desc = get_the_author_meta( 'description' );

		/* Плеер, описание и «Рекомендованные видео» выводит функция темы — забираем её вывод целиком. */
		ob_start();
		if ( function_exists( 'display_video_post_page' ) ) {
			display_video_post_page( $amt_id );
		} else {
			the_content();
		}
		$amt_video_html = ob_get_clean();

		$amt_products = amt_single_products( $amt_id, $amt_video_html );

		/* Адрес ролика для разметки VideoObject: mp4 из <source>/<video> или iframe (Rutube, VK, YouTube). */
		$amt_video_src = '';
		$amt_embed_src = '';
		if ( preg_match( '~<(?:source|video)[^>]+src=["\']([^"\']+\.(?:mp4|webm|mov)[^"\']*)["\']~i', $amt_video_html, $vm ) ) {
			$amt_video_src = html_entity_decode( $vm[1] );
		}
		if ( preg_match( '~<iframe[^>]+src=["\']([^"\']+)["\']~i', $amt_video_html, $vm ) ) {
			$amt_embed_src = html_entity_decode( $vm[1] );
		}
		?>
<div id="amt-blog" class="amt-single amt-single--video">

	<section class="amt-hero amt-shero">
		<div class="amt-container">
			<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
				<nav class="amt-crumbs" aria-label="Хлебные крошки"><?php yoast_breadcrumb( '<span>', '</span>' ); ?></nav>
			<?php endif; ?>
			<div class="amt-shero__grid">
				<div>
					<?php if ( $amt_term ) : ?>
						<a class="amt-label" href="<?php echo esc_url( get_term_link( $amt_term ) ); ?>"><?php echo esc_html( 'Видео · ' . $amt_term->name ); ?></a>
					<?php endif; ?>
					<h1 class="amt-shero__title"><?php the_title(); ?></h1>
					<div class="amt-shero__meta">
						<div class="amt-author">
							<?php echo get_avatar( $amt_author_id, 48 ); ?>
							<div>
								<b><?php the_author(); ?></b>
								<?php if ( $amt_author_desc ) : ?><span><?php echo esc_html( $amt_author_desc ); ?></span><?php endif; ?>
							</div>
						</div>
						<div class="amt-shero__dates"><span><?php echo esc_html( get_the_date( 'd.m.Y' ) ); ?></span></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="amt-section amt-sbody">
		<div class="amt-container amt-sbody__grid">
			<article class="amt-prose amt-video">
				<?php echo $amt_video_html; // phpcs:ignore WordPress.Security.EscapeOutput -- вывод функции темы ?>
			</article>

			<aside class="amt-sidebar">
				<?php if ( $amt_products ) : ?>
					<div class="amt-sblock">
						<div class="amt-eyebrow">Оборудование по теме</div>
						<?php foreach ( $amt_products as $pid ) : ?>
							<a class="amt-product" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">
								<span class="amt-product__img"><?php echo get_the_post_thumbnail( $pid, 'medium', array( 'loading' => 'lazy' ) ); ?></span>
								<span class="amt-product__title"><?php echo esc_html( get_the_title( $pid ) ); ?></span>
								<span class="amt-product__more">Подробнее →</span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="amt-sblock amt-popular"><?php echo do_shortcode( '[random_blogs count="2" exclude_post="' . (int) $amt_id . '"]' ); ?></div>

				<div class="amt-sticky">
					<div class="amt-sblock amt-ask">
						<div class="amt-eyebrow" style="color:var(--amt-orange)">Вопрос инженеру</div>
						<p>Покажем оборудование вживую на демодне или подберём решение под вашу деталь.</p>
						<a class="amt-ask__phone" href="tel:+74951091191">+7 (495) 109‑11‑91</a>
						<a class="amt-button amt-button--primary" href="#amt-form">Задать вопрос <span>→</span></a>
					</div>
				</div>
			</aside>
		</div>
	</section>

	<section class="amt-section amt-sform" id="amt-form">
		<div class="amt-container">
			<div class="custom-contactblock">
				<?php
				if ( class_exists( '\Elementor\Plugin' ) ) {
					echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( 3699 ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</div>
	</section>
</div>

<?php
		/* Разметка видео: Yoast выводит только WebPage. Без миниатюры VideoObject не выводим — поисковики его не примут. */
		$amt_thumb = get_the_post_thumbnail_url( $amt_id, 'large' );
		if ( $amt_thumb && ( $amt_video_src || $amt_embed_src ) ) {
			$amt_desc = has_excerpt() ? get_the_excerpt() : '';
			if ( '' === $amt_desc && preg_match( '~<p[^>]*>(.*?)</p>~is', $amt_video_html, $pm ) ) {
				$amt_desc = wp_strip_all_tags( $pm[1] );
			}
			$amt_ld = array(
				'@context'     => 'https://schema.org',
				'@type'        => 'VideoObject',
				'name'         => wp_strip_all_tags( get_the_title() ),
				'description'  => $amt_desc ? wp_trim_words( $amt_desc, 40, '…' ) : wp_strip_all_tags( get_the_title() ),
				'thumbnailUrl' => $amt_thumb,
				'uploadDate'   => get_the_date( 'c' ),
				'publisher'    => array(
					'@type' => 'Organization',
					'name'  => 'AM.TECH',
					'url'   => home_url( '/' ),
				),
			);
			if ( $amt_video_src ) {
				$amt_ld['contentUrl'] = $amt_video_src;
			}
			if ( $amt_embed_src ) {
				$amt_ld['embedUrl'] = $amt_embed_src;
			}
			echo '<script type="application/ld+json">' . wp_json_encode( $amt_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		}
		?>
<?php
	endwhile;
endif;
?>

<style id="amt-single-styles">
/*AMT_CSS*/
</style>
<?php
get_footer();
