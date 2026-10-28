<?php
/**
 * Архив новостей (/news/) и рубрики новостей (/news-category/…) в дизайне блога.
 * Один файл ставится как archive-news.php и taxonomy-news_category.php.
 * Записи — из основного запроса WordPress, поэтому страницы /news/page/N/ остаются обычными ссылками
 * и доступны поисковикам. Вкладки ведут на настоящие адреса рубрик.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*AMT_COMMON*/

$amt_is_term = is_tax( 'news_category' );
$amt_qo      = get_queried_object();
$amt_blog    = home_url( '/blog/' );
$amt_all     = get_post_type_archive_link( 'news' );
$amt_paged   = max( 1, (int) get_query_var( 'paged' ) );

if ( $amt_is_term ) {
	$amt_h1   = single_term_title( '', false );
	$amt_lead = wp_trim_words( wp_strip_all_tags( term_description() ), 45, '…' );
} else {
	$amt_h1   = 'Все материалы';
	$amt_lead = 'Новости, статьи и технологические разборы AM.TECH: 3D-печать металлом и полимерами, 3D-сканирование, выставки и события компании.';
}

$amt_terms = get_terms(
	array(
		'taxonomy'   => 'news_category',
		'hide_empty' => true,
		'parent'     => 0,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
if ( is_wp_error( $amt_terms ) ) {
	$amt_terms = array();
}

get_header();
get_template_part( 'header-nav' );
?>
<div id="amt-blog" class="amt-single amt-archive">

	<section class="amt-hero amt-shero">
		<div class="amt-container">
			<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
				<nav class="amt-crumbs" aria-label="Хлебные крошки"><?php yoast_breadcrumb( '<span>', '</span>' ); ?></nav>
			<?php endif; ?>
			<div class="amt-shero__grid">
				<a class="amt-label" href="<?php echo esc_url( $amt_blog ); ?>">Блог AM.TECH</a>
				<h1 class="amt-shero__title"><?php echo esc_html( $amt_h1 ); ?><?php echo $amt_paged > 1 ? ' — страница ' . (int) $amt_paged : ''; ?></h1>
				<?php if ( $amt_lead && 1 === $amt_paged ) : ?>
					<p class="amt-shero__lead"><?php echo esc_html( $amt_lead ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="amt-topics">
		<div class="amt-container amt-topics__row">
			<a class="amt-topic<?php echo $amt_is_term ? '' : ' is-active'; ?>" href="<?php echo esc_url( $amt_all ); ?>">Все материалы</a>
			<?php foreach ( $amt_terms as $t ) : ?>
				<a class="amt-topic<?php echo ( $amt_is_term && $amt_qo && (int) $amt_qo->term_id === (int) $t->term_id ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<section class="amt-section">
		<div class="amt-container">
			<?php if ( have_posts() ) : ?>
				<div class="amt-articles">
					<?php
					while ( have_posts() ) :
						the_post();
						$pid   = get_the_ID();
						$term  = amt_single_term( $pid, 'news_category' );
						$label = $term ? $term->name : 'Блог';
						$img   = get_the_post_thumbnail_url( $pid, 'medium_large' );
						?>
						<a class="amt-article" href="<?php the_permalink(); ?>">
							<div class="amt-media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ');background-size:cover;background-position:center;"' : ''; ?>><span class="amt-media__label"><?php echo esc_html( $label ); ?></span></div>
							<div class="amt-article__body">
								<div class="amt-label"><?php echo esc_html( $label ); ?></div>
								<h3><?php the_title(); ?></h3>
								<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22, '…' ) ); ?></p>
								<div class="amt-meta"><span><?php echo esc_html( get_the_date( 'd.m.Y' ) ); ?></span><span><?php echo esc_html( str_replace( ' чтения', '', amt_single_read_time( get_post_field( 'post_content', $pid ) ) ) ); ?></span><span>Читать →</span></div>
							</div>
						</a>
					<?php endwhile; ?>
				</div>

				<?php
				$amt_links = paginate_links(
					array(
						'type'      => 'array',
						'mid_size'  => 1,
						'end_size'  => 1,
						'prev_text' => '←',
						'next_text' => '→',
					)
				);
				if ( $amt_links ) :
					?>
					<nav class="amt-pagination" aria-label="Страницы"><?php echo implode( '', $amt_links ); // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
				<?php endif; ?>
			<?php else : ?>
				<div class="amt-empty">Материалов пока нет. <a href="<?php echo esc_url( $amt_blog ); ?>">Вернуться в блог</a>.</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="amt-section amt-section--dark amt-cta">
		<div class="amt-container amt-cta__grid">
			<div>
				<div class="amt-eyebrow">Инженерная консультация</div>
				<h2 class="amt-title amt-title--section">Начнём с вашей<br>производственной <em>задачи</em></h2>
				<p class="amt-lead">Пришлите чертёж, 3D-модель или описание процесса. Инженеры AM.TECH предложат маршрут решения и состав следующего шага.</p>
				<div class="amt-contacts"><a href="tel:+74951091191">+7 (495) 109‑11‑91</a><a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a></div>
			</div>
			<div class="amt-cta__actions">
				<a class="amt-button amt-button--primary" href="/kontakty/">Написать инженеру <span>→</span></a>
				<a class="amt-button amt-button--secondary" href="/katalog/">Каталог оборудования</a>
			</div>
		</div>
	</section>
</div>

<style id="amt-single-styles">
/*AMT_CSS*/
</style>
<?php
get_footer();
