<?php
/*
Plugin Name: Per Product Currency For WooCommerce
Description: Allows setting a custom currency per product in WooCommerce, including affiliate products and homepage loops.
Version: 1.4
Author: Koderoo
Author URI: https://koderoo.dev
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce
Text Domain: per-product-currency-for-woocommerce
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Per_Product_Currency {

    public function __construct() {
        add_action( 'woocommerce_product_options_pricing', [ $this, 'add_currency_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_currency_field' ] );
        add_filter( 'woocommerce_currency', [ $this, 'set_product_currency' ] );
        add_filter( 'woocommerce_get_price_html', [ $this, 'override_price_html' ], 10, 2 );
        add_filter( 'woocommerce_cart_item_price', [ $this, 'adjust_cart_price_display' ], 10, 3 );
        add_action( 'wp', [ $this, 'ensure_global_currency_filter' ] );
    }

    public function add_currency_field() {
        wp_nonce_field( 'save_product_currency_nonce', 'product_currency_nonce' );
        woocommerce_wp_text_input( [
            'id'          => '_product_currency',
            'label'       => __( 'Product Currency', 'per-product-currency-for-woocommerce' ),
            'placeholder' => get_woocommerce_currency(),
            'desc_tip'    => 'true',
            'description' => __( 'Enter the currency code (e.g., USD, EUR, GBP) for this product. Leave blank to use the default store currency.', 'per-product-currency-for-woocommerce' ),
        ] );
    }

    public function save_currency_field( $post_id ) {
        if ( isset( $_POST['product_currency_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['product_currency_nonce'] ) );
            if ( wp_verify_nonce( $nonce, 'save_product_currency_nonce' ) ) {
                if ( isset( $_POST['_product_currency'] ) ) {
                    $product_currency = sanitize_text_field( wp_unslash( $_POST['_product_currency'] ) );
                    if ( $product_currency ) {
                        update_post_meta( $post_id, '_product_currency', $product_currency );
                    } else {
                        delete_post_meta( $post_id, '_product_currency' );
                    }
                }
            }
        }
    }

    public function set_product_currency( $currency ) {
        if ( is_product() ) {
            global $post;
            $product_currency = get_post_meta( $post->ID, '_product_currency', true );
            if ( $product_currency ) {
                return $product_currency;
            }
        }
        return $currency;
    }

    public function override_price_html( $price_html, $product ) {
        $product_currency = get_post_meta( $product->get_id(), '_product_currency', true );

        if ( $product_currency && $product_currency !== get_woocommerce_currency() ) {
            $currency_symbol = get_woocommerce_currency_symbol( $product_currency );

            $price_html = str_replace(
                get_woocommerce_currency_symbol(),
                $currency_symbol,
                $price_html
            );
        }

        return $price_html;
    }

    public function adjust_cart_price_display( $price, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];
        $product_currency = get_post_meta( $product->get_id(), '_product_currency', true );

        if ( $product_currency && $product_currency !== get_woocommerce_currency() ) {
            $currency_symbol = get_woocommerce_currency_symbol( $product_currency );
            return $currency_symbol . ' ' . $price;
        }
        return $price;
    }

    public function ensure_global_currency_filter() {
        add_filter( 'woocommerce_product_get_price', [ $this, 'override_product_price' ], 10, 2 );
        add_filter( 'woocommerce_product_get_regular_price', [ $this, 'override_product_price' ], 10, 2 );
        add_filter( 'woocommerce_product_get_sale_price', [ $this, 'override_product_price' ], 10, 2 );
    }

    public function override_product_price( $price, $product ) {
        $product_currency = get_post_meta( $product->get_id(), '_product_currency', true );
        if ( $product_currency && $product_currency !== get_woocommerce_currency() ) {
            return $price;
        }
        return $price;
    }
}

new Per_Product_Currency();
