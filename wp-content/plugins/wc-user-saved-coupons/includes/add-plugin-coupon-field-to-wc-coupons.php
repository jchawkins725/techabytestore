<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Add a checkbox field to the coupon edit page in the WooCommerce admin area.
// This field will allow the admin to mark a coupon as one that should be auto-managed by the WC User-Saved Coupon plugin.

add_action( 'woocommerce_coupon_options', function( $coupon_id ) {
    woocommerce_wp_checkbox( array(
        'id'          => 'is_plugin_coupon',
        'label'       => __( 'WC User-Saved Coupon Plugin Coupon', 'your-textdomain' ),
        'desc_tip'    => true,
        'description' => __( 'Check if this coupon should be auto-managed by the WC User-Saved Coupon plugin.', 'your-textdomain' ),
    ) );
} );

// Save the checkbox field value.
add_action( 'woocommerce_coupon_options_save', function( $coupon_id, $coupon ) {
    $plugin_coupon = isset( $_POST['is_plugin_coupon'] ) ? 'yes' : 'no';
    update_post_meta( $coupon_id, 'is_plugin_coupon', $plugin_coupon );
}, 10, 2 );
