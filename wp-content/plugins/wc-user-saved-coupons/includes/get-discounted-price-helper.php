<?php


/**
 * Helper function to calculate the discounted price.
 *
 * @param WC_Product  $product The product object.
 * @param WC_Coupon   $coupon  The coupon object.
 * @return float|false         New price, or false if coupon type unsupported.
 */
function usc_get_discounted_price( $product, $coupon ) {
    error_log( 'usc_get_discounted_price helper function triggered' );
    $orig_price = floatval( $product->get_price() );
    $amount     = floatval( $coupon->get_amount() );
    $type       = $coupon->get_discount_type();
    if ( 'percent' === $type ) {
        return $orig_price * ( 1 - $amount / 100 );
    } elseif ( 'fixed_product' === $type ) {
        return max( 0, $orig_price - $amount );
    }
    return false;
}