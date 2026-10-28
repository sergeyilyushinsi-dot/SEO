<?php
/**
 * Отдельная статья (news) и отдельный кейс (cases) в дизайне блога.
 * Один и тот же файл ставится как single-news.php и single-cases.php.
 *
 * Сохранено из старых шаблонов: шапка сайта (header-nav), крошки Yoast, автор, главное фото
 * (get_news_main_image), «Популярное» (random_news), «Оборудование по теме» (ACF «товары_в_этой_записи»),
 * форма связи (Elementor-шаблон 3699).
 * Новое: содержание по H2, блок цифр для кейса, «Читайте также» / «Похожие кейсы», разметка Article.
 * Если в поле ACF ничего нет, «Оборудование по теме» собирается из ссылок на товары в тексте записи.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'amt_single_read_time' ) ) {
	function amt_single_read_time( $html ) {
		$words = count( preg_split( '/\s+/u', wp_strip_all_tags( $html ), -1, PREG_SPLIT_NO_EMPTY ) );
		return max( 1, (int) ceil( $words / 180 ) ) . ' мин чтения';
	}
}

/* Главная рубрика записи: самый глубокий термин таксономии. */
if ( ! function_exists( 'amt_single_term' ) ) {
	function amt_single_term( $post_id, $tax ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		$best = $terms[0];
		foreach ( $terms as $t ) {
			if ( count( get_ancestors( $t->term_id, $tax, 'taxonomy' ) ) > count( get_ancestors( $best->term_id, $tax, 'taxonomy' ) ) ) {
				$best = $t;
			}
		}
		return $best;
	}
}

/* Таблица «Коротко» кейса (первая таблица из двух колонок) → блок цифр. Таблица из текста убирается. */
if ( ! function_exists( 'amt_single_extract_facts' ) ) {
	function amt_single_extract_facts( &$html ) {
		if ( ! preg_match( '#(?:<div[^>]*overflow[^>]*>\s*)?<table[^>]*>(.*?)</table>(?:\s*</div>)?#is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}
		/* Только если таблица стоит в начале текста — это «паспорт» кейса, а не таблица из середины. */
		if ( $m[0][1] > 3000 ) {
			return array();
		}
		preg_match_all( '#<tr[^>]*>\s*<t[dh][^>]*>(.*?)</t[dh]>\s*<t[dh][^>]*>(.*?)</t[dh]>\s*</tr>#is', $m[1][0], $rows, PREG_SET_ORDER );
		$facts = array();
		foreach ( $rows as $r ) {
			$label = trim( wp_strip_all_tags( $r[1] ) );
			$value = trim( wp_kses( $r[2], array( 'a' => array( 'href' => array() ) ) ) );
			if ( '' !== $label && '' !== trim( wp_strip_all_tags( $value ) ) ) {
				$facts[] = array( $label, $value );
			}
		}
		if ( count( $facts ) < 3 ) {
			return array();
		}
		$html = substr_replace( $html, '', $m[0][1], strlen( $m[0][0] ) );
		return $facts;
	}
}

/* id для H2 и список для «Содержания». */
if ( ! function_exists( 'amt_single_toc' ) ) {
	function amt_single_toc( &$html ) {
		$toc  = array();
		$used = array();
		$html = preg_replace_callback(
			'#<h2([^>]*)>(.*?)</h2>#is',
			function ( $m ) use ( &$toc, &$used ) {
				$text = trim( wp_strip_all_tags( $m[2] ) );
				if ( '' === $text ) {
					return $m[0];
				}
				if ( preg_match( '/\sid=["\']([^"\']+)["\']/', $m[1], $idm ) ) {
					$id = $idm[1];
					$attrs = $m[1];
				} else {
					$id = sanitize_title( $text );
					if ( '' === $id ) {
						$id = 'razdel';
					}
					$base = $id;
					$n    = 2;
					while ( isset( $used[ $id ] ) ) {
						$id = $base . '-' . $n++;
					}
					$attrs = $m[1] . ' id="' . esc_attr( $id ) . '"';
				}
				$used[ $id ] = true;
				$toc[]       = array( $id, $text );
				return '<h2' . $attrs . '>' . $m[2] . '</h2>';
			},
			$html
		);
		return $toc;
	}
}

/* «Оборудование по теме»: поле ACF, иначе — товары, на которые ссылается текст. */
if ( ! function_exists( 'amt_single_products' ) ) {
	function amt_single_products( $post_id, $html ) {
		$ids = array();
		if ( function_exists( 'get_field' ) ) {
			$field = get_field( 'товары_в_этой_записи', $post_id );
			if ( $field ) {
				foreach ( (array) $field as $p ) {
					$ids[] = is_object( $p ) ? $p->ID : (int) $p;
				}
			}
		}
		if ( ! $ids && preg_match_all( '~href=["\'](?:https?://(?:www\.)?am-tech\.ru)?/product/([^/"\'?#]+)/?["\'?#]~i', $html, $m ) ) {
			foreach ( array_unique( $m[1] ) as $slug ) {
				$p = get_page_by_path( sanitize_title( urldecode( $slug ) ), OBJECT, 'product' );
				if ( $p && 'publish' === $p->post_status ) {
					$ids[] = $p->ID;
				}
			}
		}
		/* Ссылок на товары нет, но есть ссылки на разделы каталога — берём товары первого из них. */
		if ( ! $ids && preg_match_all( '~href=["\'](?:https?://(?:www\.)?am-tech\.ru)?/katalog/(?:[^"\'?#]+/)?([^/"\'?#]+)/?["\'?#]~i', $html, $m ) ) {
			foreach ( array_unique( $m[1] ) as $slug ) {
				$cat = get_term_by( 'slug', sanitize_title( urldecode( $slug ) ), 'product_cat' );
				if ( ! $cat ) {
					continue;
				}
				$ids = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 3,
						'fields'         => 'ids',
						'orderby'        => 'menu_order date',
						'order'          => 'ASC',
						'tax_query'      => array(
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $cat->term_id,
							),
						),
					)
				);
				if ( $ids ) {
					break;
				}
			}
		}
		return array_slice( array_unique( array_filter( $ids ) ), 0, 3 );
	}
}

if ( ! function_exists( 'amt_single_card' ) ) {
	function amt_single_card( $post_id, $tax ) {
		$term  = amt_single_term( $post_id, $tax );
		$label = $term ? $term->name : '';
		$img   = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		?>
		<a class="amt-article" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
			<div class="amt-media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ');background-size:cover;background-position:center;"' : ''; ?>><?php if ( $label ) : ?><span class="amt-media__label"><?php echo esc_html( $label ); ?></span><?php endif; ?></div>
			<div class="amt-article__body">
				<?php if ( $label ) : ?><div class="amt-label"><?php echo esc_html( $label ); ?></div><?php endif; ?>
				<h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
				<div class="amt-meta"><span><?php echo esc_html( get_the_date( 'd.m.Y', $post_id ) ); ?></span><span>Читать →</span></div>
			</div>
		</a>
		<?php
	}
}

get_header();
get_template_part( 'header-nav' );

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$amt_id      = get_the_ID();
		$amt_is_case = ( 'cases' === get_post_type() );
		$amt_tax     = $amt_is_case ? 'case_category' : 'news_category';
		$amt_term    = amt_single_term( $amt_id, $amt_tax );
		$amt_html    = apply_filters( 'the_content', get_the_content() );
		$amt_html    = str_replace( ']]>', ']]&gt;', $amt_html );

		/* Статьи со своей вёрсткой (своё содержание, колонки, стили) выводим в полную ширину, без нашего содержания. */
		$amt_own_layout = (bool) preg_match( '/amt-wp-(layout|sidebar)|class="[^"]*\btoc\b|<style/i', $amt_html );

		$amt_facts = $amt_is_case ? amt_single_extract_facts( $amt_html ) : array();
		$amt_toc   = $amt_own_layout ? array() : amt_single_toc( $amt_html );
		if ( count( $amt_toc ) < 3 ) {
			$amt_toc = array();
		}
		$amt_products = amt_single_products( $amt_id, $amt_html );

		$amt_img = function_exists( 'get_news_main_image' ) ? get_news_main_image() : '';
		if ( ! $amt_img ) {
			$amt_img = get_the_post_thumbnail_url( $amt_id, 'large' );
		}

		$amt_author_id   = (int) get_the_author_meta( 'ID' );
		$amt_author_desc = get_the_author_meta( 'description' );
		$amt_published   = get_the_date( 'd.m.Y' );
		$amt_modified    = get_the_modified_date( 'd.m.Y' );
		$amt_show_mod    = ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) ) > 7 * DAY_IN_SECONDS;

		$amt_list_url = $amt_is_case ? home_url( '/teknologii/' ) : home_url( '/blog/' );
		?>
<div id="amt-blog" class="amt-single<?php echo $amt_is_case ? ' amt-single--case' : ''; ?><?php echo $amt_own_layout ? ' amt-single--wide' : ''; ?>">

	<section class="amt-hero amt-shero">
		<div class="amt-container">
			<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
				<div class="amt-eyebrow amt-shero__crumbs"><?php yoast_breadcrumb( '<span style="display:inline-flex;flex-wrap:wrap;gap:8px;color:#7b8288;text-transform:uppercase;font-size:11px">', '</span>' ); ?></div>
			<?php endif; ?>
			<div class="amt-shero__grid<?php echo $amt_img ? '' : ' amt-shero__grid--noimg'; ?>">
				<div>
					<?php if ( $amt_term ) : ?>
						<a class="amt-label" href="<?php echo esc_url( get_term_link( $amt_term ) ); ?>"><?php echo esc_html( ( $amt_is_case ? 'Кейс · ' : '' ) . $amt_term->name ); ?></a>
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
						<div class="amt-shero__dates">
							<span><?php echo esc_html( $amt_published ); ?></span>
							<?php if ( $amt_show_mod ) : ?><span>Обновлено <?php echo esc_html( $amt_modified ); ?></span><?php endif; ?>
							<span><?php echo esc_html( amt_single_read_time( $amt_html ) ); ?></span>
						</div>
					</div>
				</div>
				<?php if ( $amt_img ) : ?>
					<div class="amt-shero__image"><img src="<?php echo esc_url( $amt_img ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" fetchpriority="high"></div>
				<?php endif; ?>
			</div>

			<?php if ( $amt_facts ) : ?>
				<div class="amt-facts">
					<?php foreach ( $amt_facts as $f ) : ?>
						<div class="amt-fact"><span><?php echo esc_html( $f[0] ); ?></span><b><?php echo wp_kses( $f[1], array( 'a' => array( 'href' => array() ) ) ); ?></b></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="amt-section amt-sbody">
		<div class="amt-container amt-sbody__grid">
			<article class="amt-prose">
				<?php if ( $amt_toc ) : ?>
					<details class="amt-toc amt-toc--mobile">
						<summary>Содержание</summary>
						<ol><?php foreach ( $amt_toc as $t ) : ?><li><a href="#<?php echo esc_attr( $t[0] ); ?>"><?php echo esc_html( $t[1] ); ?></a></li><?php endforeach; ?></ol>
					</details>
				<?php endif; ?>
				<?php echo $amt_html; // phpcs:ignore WordPress.Security.EscapeOutput -- контент записи ?>

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

				<?php if ( function_exists( 'random_news' ) ) : ?>
					<div class="amt-sblock amt-popular"><?php random_news(); ?></div>
				<?php endif; ?>

				<div class="amt-sticky">
					<?php if ( $amt_toc ) : ?>
						<nav class="amt-sblock amt-toc">
							<div class="amt-eyebrow">Содержание</div>
							<ol><?php foreach ( $amt_toc as $t ) : ?><li><a href="#<?php echo esc_attr( $t[0] ); ?>"><?php echo esc_html( $t[1] ); ?></a></li><?php endforeach; ?></ol>
						</nav>
					<?php endif; ?>
					<div class="amt-sblock amt-ask">
						<div class="amt-eyebrow" style="color:var(--amt-orange)">Вопрос инженеру</div>
						<p>Подберём оборудование или технологию под вашу деталь.</p>
						<a class="amt-ask__phone" href="tel:+74951091191">+7 (495) 109‑11‑91</a>
						<a class="amt-button amt-button--primary" href="#amt-form">Задать вопрос <span>→</span></a>
					</div>
				</div>
			</aside>
		</div>
	</section>

	<?php
	$amt_related_args = array(
		'post_type'           => get_post_type(),
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $amt_id ),
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
	);
	if ( $amt_term ) {
		$amt_related_args['tax_query'] = array(
			array(
				'taxonomy' => $amt_tax,
				'field'    => 'term_id',
				'terms'    => $amt_term->term_id,
			),
		);
	}
	$amt_related = get_posts( $amt_related_args );
	if ( count( $amt_related ) < 3 ) {
		unset( $amt_related_args['tax_query'] );
		$amt_related_args['post__not_in'] = array_merge( array( $amt_id ), $amt_related );
		$amt_related_args['posts_per_page'] = 3 - count( $amt_related );
		$amt_related = array_merge( $amt_related, get_posts( $amt_related_args ) );
	}
	?>
	<?php if ( $amt_related ) : ?>
		<section class="amt-section amt-section--soft">
			<div class="amt-container">
				<div class="amt-section__head">
					<div class="amt-section__copy">
						<div class="amt-eyebrow"><?php echo $amt_is_case ? 'Практика' : 'Читайте также'; ?></div>
						<h2 class="amt-title amt-title--section"><?php echo $amt_is_case ? 'Похожие кейсы' : 'Материалы по теме'; ?></h2>
					</div>
					<a class="amt-button amt-button--secondary" href="<?php echo esc_url( $amt_list_url ); ?>"><?php echo $amt_is_case ? 'Все кейсы' : 'Все материалы'; ?> <span>→</span></a>
				</div>
				<div class="amt-articles">
					<?php foreach ( $amt_related as $rid ) { amt_single_card( $rid, $amt_tax ); } ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

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
		/* Разметка статьи: Yoast выводит только WebPage. */
		$amt_ld = array(
			'@context'         => 'https://schema.org',
			'@type'            => $amt_is_case ? 'Article' : 'NewsArticle',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author(),
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => 'AM.TECH',
				'url'   => home_url( '/' ),
			),
			'mainEntityOfPage' => get_permalink(),
		);
		if ( $amt_author_desc ) {
			$amt_ld['author']['jobTitle'] = $amt_author_desc;
		}
		if ( $amt_img ) {
			$amt_ld['image'] = $amt_img;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $amt_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		?>
<?php
	endwhile;
endif;
?>

<script>
/* Подсветка текущего раздела в «Содержании». */
(function () {
	var links = document.querySelectorAll('#amt-blog .amt-sidebar .amt-toc a');
	if (!links.length || !('IntersectionObserver' in window)) return;
	var map = {};
	links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (!e.isIntersecting) return;
			links.forEach(function (a) { a.classList.remove('is-active'); });
			var a = map[e.target.id];
			if (a) a.classList.add('is-active');
		});
	}, { rootMargin: '-100px 0px -70% 0px' });
	Object.keys(map).forEach(function (id) { var h = document.getElementById(id); if (h) io.observe(h); });
})();
</script>
<style id="amt-single-styles">
/*AMT_CSS*/
</style>
<?php
get_footer();
