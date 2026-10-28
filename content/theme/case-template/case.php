<?php
/**
 * Template Name: Кейсы (дизайн блога)
 *
 * Страница /teknologii/ (page 1731): кейсы AM.TECH в дизайне страницы «Блог» (news.php).
 * Кейсы выводятся из типа записей `cases`, вкладки — термины `case_category`.
 * Фильтр, поиск и «Показать ещё» работают без отдельного REST-маршрута:
 * скрипт запрашивает эту же страницу с ?amt_partial=1 и получает только сетку карточек.
 * Без JS вкладки и пагинация работают обычными ссылками (?amt_term=…, ?amt_pg=…).
 *
 * Стили — копия стилей #amt-blog со страницы «Блог», собраны в этот файл (build.py).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AMT_CASES_PER_PAGE' ) ) {
	define( 'AMT_CASES_PER_PAGE', 12 );
}

/* Короткие подписи вкладок: полные названия терминов слишком длинные для строки фильтра. */
if ( ! function_exists( 'amt_cases_short_label' ) ) {
	function amt_cases_short_label( $term ) {
		$map = array(
			'tehnologii-3d-skanirovaniya'           => '3D-сканирование',
			'metallicheskaya-3d-pechat'             => '3D-печать металлом',
			'3d-pechat-metallami-po-tehnologii-slm' => 'SLM',
			'3d-pechat-metallami-po-tehnologii-ebm' => 'EBM',
			'3d-pechat-metallami-po-tehnologii-mbj' => 'MBJ',
			'tehnologii-sla-cdlp'                   => 'SLA и керамика (CDLP)',
		);
		return isset( $map[ $term->slug ] ) ? $map[ $term->slug ] : $term->name;
	}
}

/* Самый глубокий термин case_category у записи — для подписи на карточке. */
if ( ! function_exists( 'amt_cases_post_term' ) ) {
	function amt_cases_post_term( $post_id ) {
		$terms = get_the_terms( $post_id, 'case_category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		$best = $terms[0];
		foreach ( $terms as $t ) {
			if ( count( get_ancestors( $t->term_id, 'case_category', 'taxonomy' ) ) > count( get_ancestors( $best->term_id, 'case_category', 'taxonomy' ) ) ) {
				$best = $t;
			}
		}
		return $best;
	}
}

/* Анонс: ручная цитата или начало текста без служебного «Коротко:». */
if ( ! function_exists( 'amt_cases_excerpt' ) ) {
	function amt_cases_excerpt( $post_id, $words = 24 ) {
		$post = get_post( $post_id );
		if ( has_excerpt( $post_id ) ) {
			$text = $post->post_excerpt;
		} else {
			/* Первый содержательный абзац: таблица фактов и подписи в анонс не попадают. */
			$text = '';
			if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', strip_shortcodes( $post->post_content ), $m ) ) {
				foreach ( $m[1] as $para ) {
					$para = trim( wp_strip_all_tags( $para ) );
					if ( mb_strlen( preg_replace( '/^\s*Коротко\s*[:.]\s*/u', '', $para ) ) > 80 ) {
						$text = $para;
						break;
					}
				}
			}
			if ( '' === $text ) {
				$text = strip_shortcodes( $post->post_content );
			}
		}
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/^\s*Коротко\s*[:.]\s*/u', '', $text );
		$text = mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
		return wp_trim_words( $text, $words, '…' );
	}
}

if ( ! function_exists( 'amt_cases_read_time' ) ) {
	function amt_cases_read_time( $post_id ) {
		$words = count( preg_split( '/\s+/u', wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), -1, PREG_SPLIT_NO_EMPTY ) );
		return max( 1, (int) ceil( $words / 180 ) ) . ' мин';
	}
}

if ( ! function_exists( 'amt_cases_plural' ) ) {
	function amt_cases_plural( $n ) {
		$n10  = $n % 10;
		$n100 = $n % 100;
		if ( 1 === $n10 && 11 !== $n100 ) {
			return $n . ' кейс';
		}
		if ( $n10 >= 2 && $n10 <= 4 && ( $n100 < 12 || $n100 > 14 ) ) {
			return $n . ' кейса';
		}
		return $n . ' кейсов';
	}
}

if ( ! function_exists( 'amt_cases_bg' ) ) {
	function amt_cases_bg( $post_id, $size = 'large' ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		return $url ? 'background-image:url(' . esc_url( $url ) . ');background-size:cover;background-position:center;' : '';
	}
}

if ( ! function_exists( 'amt_cases_card' ) ) {
	function amt_cases_card( $post_id ) {
		$term  = amt_cases_post_term( $post_id );
		$label = $term ? amt_cases_short_label( $term ) : 'Кейс';
		?>
		<a class="amt-article" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
			<div class="amt-media" style="<?php echo esc_attr( amt_cases_bg( $post_id ) ); ?>"><span class="amt-media__label"><?php echo esc_html( $label ); ?></span></div>
			<div class="amt-article__body">
				<div class="amt-label"><?php echo esc_html( $label ); ?></div>
				<h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
				<p><?php echo esc_html( amt_cases_excerpt( $post_id ) ); ?></p>
				<div class="amt-meta"><span><?php echo esc_html( get_the_date( 'd.m.Y', $post_id ) ); ?></span><span><?php echo esc_html( amt_cases_read_time( $post_id ) ); ?></span><span>Читать →</span></div>
			</div>
		</a>
		<?php
	}
}

/* Запрос сетки: термин, поиск, страница. Без фильтра исключаем записи из блока «Главное». */
if ( ! function_exists( 'amt_cases_query' ) ) {
	function amt_cases_query( $term_slug, $search, $paged, $exclude ) {
		$args = array(
			'post_type'           => 'cases',
			'post_status'         => 'publish',
			'posts_per_page'      => AMT_CASES_PER_PAGE,
			'paged'               => max( 1, (int) $paged ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);
		if ( $term_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'case_category',
					'field'    => 'slug',
					'terms'    => $term_slug,
				),
			);
		}
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( ! $term_slug && ! $search && $exclude ) {
			$args['post__not_in'] = $exclude;
		}
		return new WP_Query( $args );
	}
}

if ( ! function_exists( 'amt_cases_grid' ) ) {
	function amt_cases_grid( $q, $base_url, $term_slug, $search ) {
		$paged = max( 1, (int) $q->get( 'paged' ) );
		if ( ! $q->have_posts() ) {
			echo '<div class="amt-empty">Кейсов по этому запросу пока нет. Посмотрите <a href="' . esc_url( $base_url ) . '#amt-cases-list">все кейсы</a> или <a href="/kontakty/">напишите инженеру</a>.</div>';
			return;
		}
		echo '<div class="amt-articles" data-amt-found="' . (int) $q->found_posts . '" data-amt-pages="' . (int) $q->max_num_pages . '" data-amt-paged="' . $paged . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			amt_cases_card( get_the_ID() );
		}
		wp_reset_postdata();
		echo '</div>';
		if ( $q->max_num_pages > $paged ) {
			$next = add_query_arg(
				array_filter(
					array(
						'amt_term' => $term_slug,
						'amt_s'    => $search,
						'amt_pg'   => $paged + 1,
					)
				),
				$base_url
			);
			echo '<div class="amt-loadmore"><a class="amt-button amt-button--secondary" href="' . esc_url( $next ) . '#amt-cases-list" data-amt-more="' . ( $paged + 1 ) . '">Показать ещё <span>↓</span></a></div>';
		}
	}
}

$amt_base   = get_permalink();
$amt_term   = isset( $_GET['amt_term'] ) ? sanitize_title( wp_unslash( $_GET['amt_term'] ) ) : '';
$amt_search = isset( $_GET['amt_s'] ) ? sanitize_text_field( wp_unslash( $_GET['amt_s'] ) ) : '';
$amt_paged  = isset( $_GET['amt_pg'] ) ? max( 1, (int) $_GET['amt_pg'] ) : 1;
if ( $amt_term && ! term_exists( $amt_term, 'case_category' ) ) {
	$amt_term = '';
}

/* Блок «Главное»: четыре последних кейса. */
$amt_featured = get_posts(
	array(
		'post_type'        => 'cases',
		'post_status'      => 'publish',
		'numberposts'      => 4,
		'fields'           => 'ids',
		'suppress_filters' => false,
	)
);

/* Частичный ответ для фильтра и «Показать ещё». */
if ( isset( $_GET['amt_partial'] ) ) {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	amt_cases_grid( amt_cases_query( $amt_term, $amt_search, $amt_paged, $amt_featured ), $amt_base, $amt_term, $amt_search );
	exit;
}

/* Термины для вкладок и направлений: родитель, затем его дочерние; пустые скрываем. */
$amt_parents = get_terms(
	array(
		'taxonomy'   => 'case_category',
		'parent'     => 0,
		'hide_empty' => false,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
$amt_tabs    = array();
$amt_dirs    = array();
$amt_total   = (int) wp_count_posts( 'cases' )->publish;
if ( ! is_wp_error( $amt_parents ) ) {
	foreach ( $amt_parents as $p ) {
		$children = get_terms(
			array(
				'taxonomy'   => 'case_category',
				'parent'     => $p->term_id,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		$children = is_wp_error( $children ) ? array() : $children;
		$count    = (int) $p->count;
		foreach ( $children as $c ) {
			$count += (int) $c->count;
		}
		if ( ! $count ) {
			continue;
		}
		$amt_tabs[] = $p;
		foreach ( $children as $c ) {
			$amt_tabs[] = $c;
		}
		$amt_dirs[] = array(
			'term'  => $p,
			'count' => $count,
		);
	}
	usort(
		$amt_dirs,
		function ( $a, $b ) {
			return $b['count'] - $a['count'];
		}
	);
}

$amt_q = amt_cases_query( $amt_term, $amt_search, $amt_paged, $amt_featured );

/* Текст страницы из редактора выводим, только если страница не собрана в Elementor
   (старый виджет-лента кейсов дублировал бы сетку). */
$amt_page_text = '';
if ( 'builder' !== get_post_meta( get_the_ID(), '_elementor_edit_mode', true ) ) {
	$amt_page_text = apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) );
}

get_header();
?>
<div id="amt-blog" class="amt-cases-page">

	<section class="amt-hero">
		<div class="amt-container">
			<div class="amt-hero__grid">
				<div>
					<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
						<div class="amt-eyebrow" style="margin-bottom:12px"><?php yoast_breadcrumb( '<span style="display:inline-flex;gap:8px;color:#7b8288;text-transform:uppercase;font-size:11px">', '</span>' ); ?></div>
					<?php endif; ?>
					<h1 class="amt-title">Кейсы применения технологий 3D-печати и 3D-сканирования</h1>
					<p class="amt-lead">Задачи заказчиков, которые инженеры AM.TECH решили на своём оборудовании: 3D-печать металлом (SLM, EBM, MBJ), фотополимерами (SLA) и керамикой (CDLP), промышленное 3D-сканирование, реверс-инжиниринг и контроль геометрии.</p>
					<div class="amt-hero__actions">
						<a class="amt-button amt-button--primary" href="#amt-cases-list">Смотреть кейсы <span>→</span></a>
						<a class="amt-button amt-button--secondary" href="/uslugi/">Услуги AM.TECH</a>
					</div>
				</div>
				<div class="amt-hero__aside">
					<div class="amt-eyebrow" style="color:var(--amt-orange)">Практика</div>
					<p>В каждом кейсе — исходная задача, оборудование и материал, ход работ и результат. Похожая задача? Пришлите модель или описание — подберём технологию и посчитаем стоимость.</p>
				</div>
			</div>
			<div class="amt-trust">
				<div class="amt-trust__item"><b><?php echo esc_html( amt_cases_plural( $amt_total ) ); ?></b><span>Задачи из машиностроения, энергетики, авиации, медицины и реставрации.</span></div>
				<div class="amt-trust__item"><b>Своё оборудование</b><span>Работы выполнены на 3D-принтерах и 3D-сканерах AM.TECH.</span></div>
				<div class="amt-trust__item"><b>Полный цикл</b><span>От сканирования и модели до печати, постобработки и контроля.</span></div>
			</div>
		</div>
	</section>

	<div class="amt-topics" data-amt-nav>
		<div class="amt-container amt-topics__row">
			<a class="amt-topic<?php echo $amt_term ? '' : ' is-active'; ?>" href="<?php echo esc_url( $amt_base ); ?>#amt-cases-list" data-amt-topic="">Все кейсы</a>
			<?php foreach ( $amt_tabs as $t ) : ?>
				<a class="amt-topic<?php echo $t->parent ? ' amt-topic--child' : ''; ?><?php echo $amt_term === $t->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'amt_term', $t->slug, $amt_base ) ); ?>#amt-cases-list" data-amt-topic="<?php echo esc_attr( $t->slug ); ?>" title="<?php echo esc_attr( $t->name ); ?>"><?php echo esc_html( amt_cases_short_label( $t ) ); ?></a>
			<?php endforeach; ?>
			<form class="amt-search" role="search" action="<?php echo esc_url( $amt_base ); ?>#amt-cases-list" method="get">
				<input type="search" name="amt_s" placeholder="Поиск по кейсам" value="<?php echo esc_attr( $amt_search ); ?>" data-amt-search>
			</form>
		</div>
	</div>

	<?php if ( count( $amt_featured ) >= 4 ) : ?>
		<?php $amt_main = array_shift( $amt_featured ); ?>
		<section class="amt-section">
			<div class="amt-container">
				<div class="amt-section__head">
					<div class="amt-section__copy">
						<div class="amt-eyebrow">Новые кейсы</div>
						<h2 class="amt-title amt-title--section">Последние<br>выполненные задачи</h2>
					</div>
				</div>
				<div class="amt-featured">
					<?php $amt_mt = amt_cases_post_term( $amt_main ); ?>
					<a class="amt-featured__main" href="<?php echo esc_url( get_permalink( $amt_main ) ); ?>">
						<div class="amt-media" style="<?php echo esc_attr( amt_cases_bg( $amt_main ) ); ?>"><span class="amt-media__label"><?php echo esc_html( $amt_mt ? amt_cases_short_label( $amt_mt ) : 'Кейс' ); ?></span></div>
						<div class="amt-card__body">
							<div class="amt-label"><?php echo esc_html( $amt_mt ? $amt_mt->name : 'Кейс' ); ?></div>
							<h3><?php echo esc_html( get_the_title( $amt_main ) ); ?></h3>
							<p><?php echo esc_html( amt_cases_excerpt( $amt_main, 40 ) ); ?></p>
							<div class="amt-meta"><span><?php echo esc_html( get_the_date( 'd.m.Y', $amt_main ) ); ?></span><span><?php echo esc_html( amt_cases_read_time( $amt_main ) ); ?></span><span>Читать →</span></div>
						</div>
					</a>
					<div class="amt-featured__side">
						<?php foreach ( $amt_featured as $fid ) : ?>
							<?php $ft = amt_cases_post_term( $fid ); ?>
							<a class="amt-mini" href="<?php echo esc_url( get_permalink( $fid ) ); ?>">
								<div class="amt-media" style="<?php echo esc_attr( amt_cases_bg( $fid, 'medium_large' ) ); ?>"><span class="amt-media__label"><?php echo esc_html( $ft ? amt_cases_short_label( $ft ) : 'Кейс' ); ?></span></div>
								<div class="amt-card__body">
									<div class="amt-label"><?php echo esc_html( $ft ? amt_cases_short_label( $ft ) : 'Кейс' ); ?></div>
									<h3><?php echo esc_html( get_the_title( $fid ) ); ?></h3>
									<div class="amt-meta"><span><?php echo esc_html( get_the_date( 'd.m.Y', $fid ) ); ?></span><span><?php echo esc_html( amt_cases_read_time( $fid ) ); ?></span></div>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $amt_dirs ) : ?>
		<section class="amt-section amt-section--soft">
			<div class="amt-container">
				<div class="amt-section__head">
					<div class="amt-section__copy">
						<div class="amt-eyebrow">Технологии</div>
						<h2 class="amt-title amt-title--section">Кейсы по направлениям</h2>
						<p class="amt-lead">Выберите технологию, чтобы увидеть задачи, которые AM.TECH решала с её помощью.</p>
					</div>
				</div>
				<div class="amt-directions">
					<?php foreach ( $amt_dirs as $i => $d ) : ?>
						<a class="amt-direction" href="<?php echo esc_url( add_query_arg( 'amt_term', $d['term']->slug, $amt_base ) ); ?>#amt-cases-list" data-amt-topic="<?php echo esc_attr( $d['term']->slug ); ?>">
							<div class="amt-direction__index"><span><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span> / <?php echo esc_html( amt_cases_plural( $d['count'] ) ); ?></div>
							<div>
								<h3><?php echo esc_html( $d['term']->name ); ?></h3>
								<?php if ( $d['term']->description ) : ?>
									<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $d['term']->description ), 32, '…' ) ); ?></p>
								<?php endif; ?>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="amt-section" id="amt-cases-list">
		<div class="amt-container">
			<div class="amt-section__head">
				<div class="amt-section__copy">
					<div class="amt-eyebrow">Все кейсы</div>
					<h2 class="amt-title amt-title--section">Примеры работ AM.TECH</h2>
				</div>
			</div>
			<div data-amt-grid>
				<?php amt_cases_grid( $amt_q, $amt_base, $amt_term, $amt_search ); ?>
			</div>
		</div>
	</section>

	<?php if ( trim( wp_strip_all_tags( $amt_page_text ) ) ) : ?>
		<section class="amt-section amt-section--soft">
			<div class="amt-container amt-pagetext"><?php echo $amt_page_text; // phpcs:ignore WordPress.Security.EscapeOutput -- контент страницы ?></div>
		</section>
	<?php endif; ?>

	<section class="amt-section amt-section--dark amt-cta">
		<div class="amt-container amt-cta__grid">
			<div>
				<div class="amt-eyebrow">Инженерная консультация</div>
				<h2 class="amt-title amt-title--section">Решим вашу<br>производственную <em>задачу</em></h2>
				<p class="amt-lead">Пришлите чертёж, 3D-модель или описание детали. Инженеры AM.TECH подберут технологию, рассчитают стоимость и срок.</p>
				<div class="amt-contacts"><a href="tel:+74951091191">+7 (495) 109‑11‑91</a><a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a></div>
			</div>
			<div class="amt-cta__actions">
				<a class="amt-button amt-button--primary" href="/kontakty/">Написать инженеру <span>→</span></a>
				<a class="amt-button amt-button--secondary" href="/uslugi/">Услуги AM.TECH</a>
				<a class="amt-button amt-button--secondary" href="/katalog/">Каталог оборудования</a>
			</div>
		</div>
	</section>
</div>

<style id="amt-cases-styles">
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
body.page-template-case .elementor,
body.page-template-case .elementor-section,
body.page-template-case .elementor-container,
body.page-template-case .elementor-column,
body.page-template-case .elementor-widget-wrap,
body.page-template-case .elementor-widget,
body.page-template-case .elementor-widget-container,
body.page-template-case .news_page,
body.page-template-case .content-wrapper,
body.page-template-case .contents,
body.page-template-case .content{max-width:none!important;padding-left:0!important;padding-right:0!important;overflow:visible!important;width:auto!important}
body.page-template-case #amt-blog{width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important}
html:has(body.page-template-case),body.page-template-case{overflow-x:clip!important;max-width:none!important}

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
/* --- Дополнения для страницы кейсов (case.php) --- */
#amt-blog .amt-topic--child{font-size:.92em;opacity:.85}
#amt-blog .amt-topic--child::before{content:"— ";opacity:.5}
#amt-blog form.amt-search{margin:0}
#amt-blog .amt-media:not([style*="background-image"]){background:linear-gradient(135deg,#f1f1ee,#e4e4df)}
#amt-blog .amt-empty a{color:var(--amt-orange);text-decoration:underline}
#amt-blog .amt-contacts a{color:inherit}
#amt-blog .amt-cta__actions{flex-wrap:wrap}
#amt-blog .amt-pagetext{max-width:900px;color:var(--amt-text)}
#amt-blog .amt-pagetext h2,#amt-blog .amt-pagetext h3{color:var(--amt-ink)}
#amt-blog .amt-pagetext a{color:var(--amt-orange)}
#amt-blog .amt-pagetext p:empty{display:none}
</style>

<script>
(function () {
	var root = document.getElementById('amt-blog');
	if (!root) return;
	var grid = root.querySelector('[data-amt-grid]');
	var nav = root.querySelector('[data-amt-nav]');
	var list = document.getElementById('amt-cases-list');
	var base = <?php echo wp_json_encode( $amt_base ); ?>;
	var state = { term: <?php echo wp_json_encode( $amt_term ); ?>, s: <?php echo wp_json_encode( $amt_search ); ?>, pg: <?php echo (int) $amt_paged; ?> };

	function url(partial) {
		var u = new URL(base, window.location.href);
		if (state.term) u.searchParams.set('amt_term', state.term);
		if (state.s) u.searchParams.set('amt_s', state.s);
		if (state.pg > 1) u.searchParams.set('amt_pg', state.pg);
		if (partial) u.searchParams.set('amt_partial', '1');
		return u.toString();
	}
	function scrollToList() {
		var y = list.getBoundingClientRect().top + window.pageYOffset - 90;
		window.scrollTo({ top: y, behavior: 'smooth' });
	}
	function setActive() {
		nav.querySelectorAll('.amt-topic').forEach(function (el) {
			el.classList.toggle('is-active', (el.dataset.amtTopic || '') === state.term);
		});
	}
	function load(append) {
		grid.classList.add('is-loading');
		return fetch(url(true), { credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
			.then(function (html) {
				grid.classList.remove('is-loading');
				if (!append) { grid.innerHTML = html; return; }
				var tmp = document.createElement('div');
				tmp.innerHTML = html;
				var cards = tmp.querySelector('.amt-articles');
				var target = grid.querySelector('.amt-articles');
				var more = grid.querySelector('.amt-loadmore');
				if (more) more.remove();
				if (cards) while (cards.firstChild) target.appendChild(cards.firstChild);
				var nextMore = tmp.querySelector('.amt-loadmore');
				if (nextMore) grid.appendChild(nextMore);
			})
			.catch(function () {
				grid.classList.remove('is-loading');
				window.location.href = url(false) + '#amt-cases-list';
			});
	}
	function pickTopic(slug) {
		state.term = slug; state.pg = 1;
		setActive(); load(false); scrollToList();
		history.replaceState(null, '', url(false));
	}

	nav.addEventListener('click', function (e) {
		var a = e.target.closest('.amt-topic');
		if (!a) return;
		e.preventDefault();
		pickTopic(a.dataset.amtTopic || '');
	});
	root.querySelectorAll('.amt-direction[data-amt-topic]').forEach(function (a) {
		a.addEventListener('click', function (e) { e.preventDefault(); pickTopic(a.dataset.amtTopic); });
	});
	grid.addEventListener('click', function (e) {
		var a = e.target.closest('[data-amt-more]');
		if (!a) return;
		e.preventDefault();
		state.pg = parseInt(a.dataset.amtMore, 10) || state.pg + 1;
		load(true);
		history.replaceState(null, '', url(false));
	});
	var form = nav.querySelector('.amt-search');
	var inp = nav.querySelector('[data-amt-search]');
	if (form) form.addEventListener('submit', function (e) { e.preventDefault(); });
	if (inp) {
		var t;
		inp.addEventListener('input', function () {
			clearTimeout(t);
			var v = this.value.trim();
			t = setTimeout(function () {
				state.s = v; state.pg = 1;
				load(false);
				history.replaceState(null, '', url(false));
			}, 300);
		});
	}
})();
</script>
<?php
get_footer();
