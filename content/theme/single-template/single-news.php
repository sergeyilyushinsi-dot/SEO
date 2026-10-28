<?php
/**
 * Отдельная статья (news) и отдельный кейс (cases) в дизайне блога.
 * Один и тот же файл ставится как single-news.php и single-cases.php.
 *
 * Сохранено из старых шаблонов: шапка сайта (header-nav), крошки Yoast, автор, главное фото
 * (миниатюра записи), «Популярное» (random_news), «Оборудование по теме» (ACF «товары_в_этой_записи»),
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

		/* Фото в шапке не выводим (только заголовок). Миниатюра записи идёт в разметку Article. */
		$amt_img = get_the_post_thumbnail_url( $amt_id, 'large' );

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
				<nav class="amt-crumbs" aria-label="Хлебные крошки"><?php yoast_breadcrumb( '<span>', '</span>' ); ?></nav>
			<?php endif; ?>
			<div class="amt-shero__grid">
				<div>
					<?php if ( $amt_term ) : ?>
						<a class="amt-label" href="<?php echo esc_url( get_term_link( $amt_term ) ); ?>"><?php echo esc_html( ( $amt_is_case ? 'Кейс · ' : '' ) . $amt_term->name ); ?></a>
					<?php endif; ?>
					<h1 class="amt-shero__title"><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="amt-shero__lead"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
					<?php endif; ?>
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
body.single-news #amt-blog,body.single-cases #amt-blog,body.single-video_reviews #amt-blog{width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important}
html:has(body.single-news),html:has(body.single-cases),html:has(body.single-video_reviews),body.single-news,body.single-cases,body.single-video_reviews{overflow-x:clip!important;max-width:none!important}

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
</style>
<?php
get_footer();
