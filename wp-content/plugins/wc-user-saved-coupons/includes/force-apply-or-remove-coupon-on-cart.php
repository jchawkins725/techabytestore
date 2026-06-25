<?php


/**
 * Force-apply (or remove) the saved coupon on the cart page.
 *
 * If a logged-in user has a saved coupon in their meta, this function
 * forces it to be applied. Otherwise, if the coupon has been revoked (i.e. no
 * saved coupon exists) and the coupon is still applied, it is removed.
 */
function usc_force_apply_coupon_on_cart() {
    if ( ! is_cart() || ! is_user_logged_in() || ! WC()->cart ) {
        return;
    }

    error_log( ' -- On cart page, checking for saved coupon -- ' );

    $user_id = get_current_user_id();
    $code    = get_user_meta( $user_id, 'saved_coupon_code', true );

    if ( $code ) {
        // Coupon exists in user meta, so force-apply if not already applied.
        $coupon = new WC_Coupon( $code );
        if ( $coupon->get_id() && ! WC()->cart->has_discount( $code ) ) {
            WC()->cart->apply_coupon( $code );
            
            wc_add_notice( "Your saved coupon “{$code}” has been applied.", 'success' );
        }
    } else {
        // No coupon saved in user meta.
        // Remove any auto-applied coupon(s) from the cart that are managed by our plugin.
        $applied_coupons = WC()->cart->get_applied_coupons();
        if ( ! empty( $applied_coupons ) ) {
            foreach ( $applied_coupons as $applied_coupon ) {
            $coupon = new WC_Coupon( $applied_coupon );
            if ( 'yes' === get_post_meta( $coupon->get_id(), 'is_plugin_coupon', true ) ) {
                WC()->cart->remove_coupon( $applied_coupon );
                wc_add_notice( "A coupon was removed because it is no longer valid.", 'notice' );
            }
            }
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'usc_force_apply_coupon_on_cart' );