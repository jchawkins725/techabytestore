<?php


// Show “Call For Pricing” when a product has no price set
add_filter( 'woocommerce_get_price_html', 'hr_call_for_pricing', 10, 2 );
function hr_call_for_pricing( $price_html, $product ) {
    // get_price() returns an empty string if no price is set
    if ( '' === $product->get_price() ) {

        $use_hyperlink = get_option( 'usc_use_hyperlink_for_call_for_pricing_text', false );

        // If the setting is enabled, return a hyperlink instead of plain text
        if ( $use_hyperlink ) {
            // Pull the “call for price” URL from the settings, with a fallback
            $call_url = get_option( 'usc_call_for_price_hyperlink_url', '#' );
            $call_text = get_option( 'usc_call_for_price_text', __( 'Call For Pricing', 'your-text-domain' ) );
            return '<a class="contact-for-pricing-link" href="' . esc_url( $call_url ) . '" class="call-for-price">' . esc_html( $call_text ) . '</a>';
        } else {
            // Pull the “call for price” text from the settings, with a fallback
            $call_text = get_option( 'usc_call_for_price_text', __( 'Call For Pricing', 'your-text-domain' ) );
            return '<span class="call-for-price">' . esc_html( $call_text ) . '</span>';
        }
    }
    return $price_html;
}