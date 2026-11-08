<?php
/**
 * Plugin Name: Steamgears — butikkens H1
 * Description: Giver /shop/ en kommerciel H1 i stedet for det bare »Shop«, så den følger samme beslutning som forsiden (S2.4). Kun shop-arkivet røres.
 * Version: 1.0.0
 * Author: Steamgears
 * License: GPL-2.0-or-later
 *
 * Baggrund (docs/roadmap.md S2.4, målt 8/11 2026): forsiden fik i 2/11 shop-rammen som H1
 * (»Steampunk Gear & Costumes – Shop the Looks«), men /shop/ stod stadig med det bare
 * »Shop« fra blokkens `core/query-title`. Butikken er den side der skal sælge, så dens
 * eneste H1 skal sige hvad der kan købes — ikke gentage menupunktet.
 *
 * Filteret rører kun shop-arkivet: `is_shop()` eller produkt-arkivet. Kategori-, tag- og
 * søgeresultattitler er urørte, og i admin sker der intet. Teksten kan ændres uden at
 * rette filen:
 *
 *   add_filter( 'sgap_shop_h1', fn() => 'Shop steampunk gear' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Den H1 butikken skal vise.
 *
 * @return string
 */
function sgap_shop_h1_text(): string {
	return (string) apply_filters( 'sgap_shop_h1', 'Shop Steampunk Gear & Costumes' );
}

/**
 * Er vi på shop-arkivet?
 *
 * @return bool
 */
function sgap_shop_h1_is_shop(): bool {
	if ( is_admin() ) {
		return false;
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}
	return function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' );
}

/**
 * Bytter teksten i `core/query-title` på shop-arkivet. Alt andet returneres uændret, og
 * hvis markeringen ikke kan genkendes, returneres blokken som den kom.
 *
 * @param string $content Rendered block.
 * @param array  $block   Block data.
 * @return string
 */
function sgap_shop_h1_block( $content, $block ): string {
	if ( ! is_string( $content ) || ! is_array( $block ) ) {
		return $content;
	}
	if ( ( $block['blockName'] ?? '' ) !== 'core/query-title' || ! sgap_shop_h1_is_shop() ) {
		return $content;
	}
	$replaced = preg_replace(
		'/(<h1\b[^>]*>).*?(<\/h1>)/is',
		'$1' . esc_html( sgap_shop_h1_text() ) . '$2',
		$content,
		1
	);
	return is_string( $replaced ) ? $replaced : $content;
}

add_filter( 'render_block', 'sgap_shop_h1_block', 10, 2 );
