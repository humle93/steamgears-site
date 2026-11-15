<?php
/**
 * Plugin Name: Steamgears Guide Enhance
 * Description: HowTo-JSON-LD udledt af guidens egne synlige trin plus én fast produkt-callout med levende DKK-priser. Overskriver ikke Rank Maths øvrige schema.
 * Version: 1.0.0
 * Author: Steamgears
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guides der får HowTo-schema. Kun posts hvor trinnene kan læses ud af indholdet.
 */
const SGAP_GUIDE_HOWTO_POSTS = array( 8, 10, 757, 1357, 1359, 1361, 1394 );

/**
 * Guide → publicerede produkter guiden faktisk bruger.
 * Tomme/udeladte poster får ingen callout: der findes intet publiceret produkt at pege på.
 */
const SGAP_GUIDE_CALLOUT_PRODUCTS = array(
	8    => array( 941 ),
	757  => array( 941 ),
	1357 => array( 965, 966, 967 ),
	1359 => array( 940, 941 ),
	1361 => array( 940, 941 ),
);

/**
 * Guides hvor trinnene står som løse <li>-punkter under én bestemt metodeoverskrift
 * (ikke en sammenhængende <ol>), fx goggle-guidens can-lid-metode.
 */
const SGAP_GUIDE_STEP_ANCHOR = array(
	10 => '/can[\s\-–]*lid\s*build/i',
);

/**
 * Er posten en af de guides modulet rører?
 */
function sgap_guide_is_target( $post_id ) {
	return in_array( (int) $post_id, SGAP_GUIDE_HOWTO_POSTS, true );
}

/**
 * Rens tekst fra HTML og korte entity-rester.
 */
function sgap_guide_plain( $html, $limit = 400 ) {
	$text = wp_strip_all_tags( (string) $html, true );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
	}
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	if ( mb_strlen( $text ) > $limit ) {
		$text = mb_substr( $text, 0, $limit - 1 ) . '…';
	}
	return $text;
}

/**
 * Kort trinnavn: første sætning, højst 90 tegn.
 */
function sgap_guide_step_name( $text ) {
	$text = sgap_guide_plain( $text, 200 );
	if ( preg_match( '/^(.{8,90}?[\.\:;])(\s|$)/u', $text, $m ) ) {
		$text = $m[1];
	}
	$text = rtrim( $text, " .:;" );
	if ( mb_strlen( $text ) > 90 ) {
		$short = mb_substr( $text, 0, 90 );
		$space = mb_strrpos( $short, ' ' );
		$text  = rtrim( false !== $space ? mb_substr( $short, 0, $space ) : $short, " ,.;:" );
	}
	return $text;
}

/**
 * Udled trin af guidens eget indhold — samme trin som læseren ser.
 *
 * A: overskrifter af formen "Step 3: …" (hver sin sektion).
 * B: den eller de <ol>-lister der står umiddelbart efter en "Step-by-step"-overskrift.
 *
 * @return array Liste af ['name' => …, 'text' => …].
 */
function sgap_guide_steps( $post ) {
	$content = (string) $post->post_content;
	$steps   = array();

	// A: "Step N: Titel" som H2/H3.
	if ( preg_match_all( '#<h([23])[^>]*>\s*Step\s*\d+\s*[:–\-—]\s*(.+?)</h\1>#isu', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m as $match ) {
			$heading = sgap_guide_plain( $match[2][0], 120 );
			$after   = substr( $content, $match[0][1] + strlen( $match[0][0] ) );
			$text    = '';
			if ( preg_match( '#^\s*(?:</?[a-z]+[^>]*>\s*)*<p[^>]*>(.*?)</p>#is', $after, $p ) ) {
				$text = sgap_guide_plain( $p[1] );
			}
			$steps[] = array(
				'name' => $heading,
				'text' => $text !== '' ? $text : $heading,
			);
		}
	}

	// B: nummererede lister under en step-by-step-overskrift.
	if ( ! $steps && preg_match_all( '#<h([23])[^>]*>(.*?)</h\1>#isu', $content, $heads, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		foreach ( $heads as $head ) {
			$title = sgap_guide_plain( $head[2][0], 120 );
			if ( ! preg_match( '/step[\s\-–]*by[\s\-–]*step|how to build .*step by step/i', $title ) ) {
				continue;
			}
			$offset = $head[0][1] + strlen( $head[0][0] );
			$tail   = substr( $content, $offset );
			if ( ! preg_match( '#<ol[^>]*>(.*?)</ol>#isu', $tail, $list ) ) {
				continue;
			}
			if ( preg_match_all( '#<li[^>]*>(.*?)</li>#isu', $list[1], $items ) ) {
				foreach ( $items[1] as $item ) {
					$text = sgap_guide_plain( $item );
					if ( $text === '' ) {
						continue;
					}
					$steps[] = array(
						'name' => sgap_guide_step_name( $item ),
						'text' => $text,
					);
				}
			}
		}
	}


	// C: én metodeoverskrift med løse <li>-punkter (hver sin <ol>).
	if ( ! $steps && ! empty( SGAP_GUIDE_STEP_ANCHOR[ $post->ID ] ) && preg_match_all( '#<h([23])[^>]*>(.*?)</h\1>#isu', $content, $heads, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		foreach ( $heads as $head ) {
			$title = sgap_guide_plain( $head[2][0], 160 );
			if ( ! preg_match( SGAP_GUIDE_STEP_ANCHOR[ $post->ID ], $title ) ) {
				continue;
			}
			$start   = $head[0][1] + strlen( $head[0][0] );
			$segment = substr( $content, $start );
			$next    = preg_match( '#<h2#i', $segment, $nh, PREG_OFFSET_CAPTURE );
			if ( $next ) {
				$segment = substr( $segment, 0, $nh[0][1] );
			}
			if ( preg_match_all( '#<li[^>]*>(.*?)</li>#isu', $segment, $items ) ) {
				foreach ( $items[1] as $item ) {
					$text = sgap_guide_plain( $item );
					if ( '' === $text ) {
						continue;
					}
					$steps[] = array(
						'name' => sgap_guide_step_name( $item ),
						'text' => $text,
					);
				}
			}
			break;
		}
	}

	return $steps;
}

/**
 * Fjern guidens egen inline HowTo, så der kun står ét HowTo på siden.
 * Andre schema-blokke (BlogPosting, FAQPage, Product) røres ikke.
 */
function sgap_guide_strip_inline_howto( $content ) {
	if ( ! is_singular( 'post' ) || ! sgap_guide_is_target( get_queried_object_id() ) ) {
		return $content;
	}
	$post = get_post( get_queried_object_id() );
	if ( ! $post || count( sgap_guide_steps( $post ) ) < 2 ) {
		return $content;
	}

	return preg_replace_callback(
		'#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#isu',
		static function ( $m ) {
			return ( stripos( $m[1], '"HowTo"' ) !== false ) ? '' : $m[0];
		},
		$content
	);
}
add_filter( 'the_content', 'sgap_guide_strip_inline_howto', 4 );

/**
 * Print HowTo som sit eget script — Rank Maths graph og FAQ/Article røres ikke.
 * Egen blok frem for et filter, fordi Rank Math ikke selv udsender HowTo her;
 * så er der præcis ét HowTo på siden, uanset plugin-rækkefølge.
 */
function sgap_guide_print_howto() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post = get_post( get_queried_object_id() );
	if ( ! $post || ! sgap_guide_is_target( $post->ID ) || 'publish' !== $post->post_status ) {
		return;
	}
	$steps = sgap_guide_steps( $post );
	if ( count( $steps ) < 2 ) {
		return;
	}

	$description = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
	$howto       = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'HowTo',
		'@id'              => get_permalink( $post->ID ) . '#howto',
		'name'             => get_the_title( $post->ID ),
		'description'      => sgap_guide_plain( $description, 300 ),
		'mainEntityOfPage' => array( '@id' => get_permalink( $post->ID ) ),
		'step'             => array(),
	);

	foreach ( $steps as $index => $step ) {
		$howto['step'][] = array(
			'@type'    => 'HowToStep',
			'position' => $index + 1,
			'name'     => $step['name'],
			'text'     => $step['text'],
		);
	}

	echo "<script type=\"application/ld+json\" class=\"sgap-howto\">" . wp_json_encode( $howto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'sgap_guide_print_howto', 20 );

/**
 * Byg callouten. Kun publicerede produkter; priser kommer fra WooCommerce, aldrig fra teksten.
 */
function sgap_guide_callout_html( $post_id ) {
	$post_id = (int) $post_id;
	if ( empty( SGAP_GUIDE_CALLOUT_PRODUCTS[ $post_id ] ) || ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$items = '';
	foreach ( SGAP_GUIDE_CALLOUT_PRODUCTS[ $post_id ] as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			continue;
		}
		$items .= sprintf(
			'<li class="sgap-guide-callout__item"><a class="sgap-guide-callout__media" href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a><a class="sgap-guide-callout__name" href="%1$s">%3$s</a><span class="sgap-guide-callout__price">%4$s</span></li>',
			esc_url( $product->get_permalink() ),
			$product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ),
			esc_html( $product->get_name() ),
			wp_kses_post( $product->get_price_html() )
		);
	}

	if ( '' === $items ) {
		return '';
	}

	return '<aside class="sgap-guide-callout" aria-labelledby="sgap-guide-callout-title">'
		. '<h2 class="sgap-guide-callout__title" id="sgap-guide-callout-title">Gear used in this guide</h2>'
		. '<p class="sgap-guide-callout__intro">The pieces this guide is built around. Prices in DKK, shipped from Denmark.</p>'
		. '<ul class="sgap-guide-callout__grid">' . $items . '</ul>'
		. '<p class="sgap-guide-callout__more"><a href="' . esc_url( home_url( '/shop/' ) ) . '">See the whole workshop →</a></p>'
		. '</aside>';
}

/**
 * Indsæt callouten samme sted i alle guides: lige før FAQ, ellers til sidst.
 */
function sgap_guide_append_callout( $content ) {
	if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( false !== strpos( $content, 'sgap-guide-callout' ) ) {
		return $content;
	}

	$callout = sgap_guide_callout_html( get_the_ID() );
	if ( '' === $callout ) {
		return $content;
	}

	if ( preg_match( '#<h2[^>]*>(?:\s|<[^>]+>)*(?:FAQ|Frequently Asked)#iu', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		$at = $m[0][1];
		return substr( $content, 0, $at ) . $callout . substr( $content, $at );
	}

	return $content . $callout;
}
add_filter( 'the_content', 'sgap_guide_append_callout', 20 );

/**
 * Ens styling på alle guides — ingen temaafhængighed.
 */
function sgap_guide_callout_css() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	echo '<style id="sgap-guide-callout-css">'
		. '.sgap-guide-callout{margin:2.5rem 0;padding:1.25rem;border:1px solid rgba(176,141,87,.45);border-radius:.5rem;background:rgba(176,141,87,.06)}'
		. '.sgap-guide-callout__title{font-size:1.35rem;margin:0 0 .35rem}'
		. '.sgap-guide-callout__intro{margin:0 0 1rem;font-size:.95rem;opacity:.85}'
		. '.sgap-guide-callout__grid{list-style:none;margin:0;padding:0;display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}'
		. '.sgap-guide-callout__item{display:flex;flex-direction:column;gap:.35rem;text-align:center;max-width:230px;justify-self:center}'
		. '.sgap-guide-callout__media img{display:block;width:100%;height:auto;border-radius:.35rem}'
		. '.sgap-guide-callout__name{font-weight:600;text-decoration:none;line-height:1.3;overflow-wrap:anywhere}'
		. '.sgap-guide-callout__price{font-variant-numeric:tabular-nums}'
		. '.sgap-guide-callout__more{margin:1rem 0 0;font-size:.95rem}'
		. '@media (max-width:480px){.sgap-guide-callout{padding:1rem}.sgap-guide-callout__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}}'
		. '</style>' . "\n";
}
add_action( 'wp_head', 'sgap_guide_callout_css', 30 );
