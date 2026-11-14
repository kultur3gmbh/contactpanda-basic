<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'Panda_Number_Format', false ) ) {
 final class Panda_Number_Format { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Shared Panda-family API with a class_exists guard; the Panda prefix is intentional and stable for companion plugins.
  public static function settings( $prices = false ) {
   global $wp_locale;
   $data = array( 'locale' => str_replace( '_', '-', preg_replace('/_(formal|informal)$/', '', determine_locale()) ), 'decimal' => $wp_locale->number_format['decimal_point'] ?? '.', 'thousands' => $wp_locale->number_format['thousands_sep'] ?? ',' );
   if ( $prices && function_exists( 'wc_get_price_decimal_separator' ) ) $data = array_merge( $data, array( 'decimal' => wc_get_price_decimal_separator(), 'thousands' => wc_get_price_thousand_separator(), 'decimals' => wc_get_price_decimals(), 'currency' => get_woocommerce_currency() ) );
   return $data;
  }
 }
}
