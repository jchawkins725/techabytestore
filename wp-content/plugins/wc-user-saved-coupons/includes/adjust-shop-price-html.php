<?php 

/**
 * Auto-apply the logged-in user’s saved coupon when cart contains matching category products.
 */
function usc_auto_apply_user_saved_coupon( $cart ) {
    // Only run on front-end and when cart is loaded
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    if ( ! WC()->cart || ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $code    = get_user_meta( $user_id, 'saved_coupon_code', true );
    if ( ! $code ) {
        return;
    }

    // Make sure the coupon actually exists
    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_id() ) {
        return;
    }

    // Get the category IDs this coupon is restricted to
    $coupon_cat_ids = $coupon->get_product_categories(); // array of term IDs
    if ( empty( $coupon_cat_ids ) ) {
        // no category restriction on this coupon → skip auto-apply
        return;
    }

    // Check cart items for any matching category
    $has_applicable = false;
    foreach ( $cart->get_cart() as $item ) {
        $prod_id   = $item['product_id'];
        $prod_cats = wp_get_post_terms( $prod_id, 'product_cat', [ 'fields' => 'ids' ] );
        if ( array_intersect( $coupon_cat_ids, $prod_cats ) ) {
            $has_applicable = true;
            break;
        }
    }

    // Apply or remove
    if ( $has_applicable ) {
        if ( ! WC()->cart->has_discount( $code ) ) {
            WC()->cart->apply_coupon( $code );
            // optional: wc_add_notice( "Applied your coupon “{$code}”", 'success' );
        }
    } else {
        if ( WC()->cart->has_discount( $code ) ) {
            WC()->cart->remove_coupon( $code );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'usc_auto_apply_user_saved_coupon', 10 );

// /**
//  * Auto-apply the logged-in user’s saved coupon when cart contains matching category products.
//  */
// function usc_auto_apply_user_saved_coupon( $cart ) {
//     // Only run on front-end and when cart is loaded
//     if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
//         return;
//     }
//     if ( ! WC()->cart || ! is_user_logged_in() ) {
//         return;
//     }

//     $user_id = get_current_user_id();
//     $code    = get_user_meta( $user_id, 'saved_coupon_code', true );
//     if ( ! $code ) {
//         return;
//     }

//     // Make sure the coupon actually exists
//     $coupon = new WC_Coupon( $code );
//     if ( ! $coupon->get_id() ) {
//         return;
//     }

//     // Get the category IDs this coupon is restricted to
//     $coupon_cat_ids = $coupon->get_product_categories(); // array of term IDs
//     if ( empty( $coupon_cat_ids ) ) {
//         // no category restriction on this coupon → skip auto-apply
//         return;
//     }

//     // Check cart items for any matching category
//     $has_applicable = false;
//     foreach ( $cart->get_cart() as $item ) {
//         $prod_id   = $item['product_id'];
//         $prod_cats = wp_get_post_terms( $prod_id, 'product_cat', [ 'fields' => 'ids' ] );
//         if ( array_intersect( $coupon_cat_ids, $prod_cats ) ) {
//             $has_applicable = true;
//             break;
//         }
//     }

//     // Apply or remove coupon based on eligibility
//     if ( $has_applicable ) {
//         if ( ! WC()->cart->has_discount( $code ) ) {
//             WC()->cart->apply_coupon( $code );
//             // optional: wc_add_notice( "Applied your coupon “{$code}”", 'success' );
//         }
//     } else {
//         if ( WC()->cart->has_discount( $code ) ) {
//             WC()->cart->remove_coupon( $code );
//         }
//     }
// }
// add_action( 'woocommerce_before_calculate_totals', 'usc_auto_apply_user_saved_coupon', 10 );



/**
 * Replace the displayed price with the discounted price if user’s saved coupon applies.
 *
 * @param  string      $price_html Original HTML from WC.
 * @param  WC_Product  $product    The product object.
 * @return string                   Modified HTML.
 */
function usc_discounted_price_html( $price_html, $product ) {
    if ( ! is_user_logged_in() ) {
        return $price_html;
    }
    $code = get_user_meta( get_current_user_id(), 'saved_coupon_code', true );
    if ( ! $code ) {
        return $price_html;
    }
    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_id() ) {
        return $price_html;
    }
    $coupon_cat_ids = $coupon->get_product_categories();
    if ( empty( $coupon_cat_ids ) ) {
        return $price_html;
    }
    $prod_cats = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
    if ( ! array_intersect( $coupon_cat_ids, $prod_cats ) ) {
        return $price_html;
    }
    $new_price = usc_get_discounted_price( $product, $coupon );
    if ( false === $new_price ) {
        return $price_html;
    }
    $orig_html = wc_price( $product->get_price() );
    $new_html  = wc_price( $new_price );

    $orignial_price_text = get_option( 'usc_original_price_text', __( 'Advertised Price:', 'your-textdomain' ) );
    $discounted_price_text = get_option( 'usc_discounted_price_text', __( 'Your Price:', 'your-textdomain' ) );

    $original_text_strike = get_option( 'usc_strike_thru_original_price_text', __( true, 'your-textdomain' ) );
    $original_price_html = sprintf( '%s %s', $orignial_price_text, $orig_html );  // Original price HTML, strike-through
    $discounted_price_html = sprintf( '<ins>%s %s</ins>', $discounted_price_text, $new_html );

    if ( $original_text_strike ) {
        $original_price_html = '<del>' . $original_price_html . '</del>';
    } else {
        $original_price_html = '<span>' . $original_price_html . '</span>';
    }

    return $original_price_html . ' <br/> ' . $discounted_price_html;
}
add_filter( 'woocommerce_get_price_html', 'usc_discounted_price_html', 10, 2 );

/**
 * Apply the saved‐coupon discount to cart item prices.
 */

// add_action( 'woocommerce_before_calculate_totals', 'usc_apply_saved_coupon_discount', 10, 1 );
add_action( 'woocommerce_before_calculate_totals', 'usc_apply_saved_coupon_discount', 10, 1 );
function usc_apply_saved_coupon_discount( $cart ) {
    // 1) Only run once per request
    static $discount_applied = false;
    if ( $discount_applied ) {
        return;
    }
    $discount_applied = true;

    // 2) Bail in admin or if not logged in
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    // 3) Grab your saved coupon code
    $user_id = get_current_user_id();
    $code    = get_user_meta( $user_id, 'saved_coupon_code', true );
    if ( ! $code ) {
        return;
    }
    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_amount() ) {
        return;
    }

    // 4) Only apply to products in the coupon’s categories
    $eligible_cats = $coupon->get_product_categories();
    if ( empty( $eligible_cats ) ) {
        return;
    }

    // 5) Loop your cart items
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = $cart_item['data'];

        // category check
        $prod_cats = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
        if ( ! array_intersect( $eligible_cats, $prod_cats ) ) {
            continue;
        }

        // 6) Determine the “base” price: sale if set, otherwise regular
        $sale_price = $product->get_sale_price();
        $orig_price = floatval( $sale_price ? $sale_price : $product->get_regular_price() );

        // 7) Calculate your discount
        if ( $coupon->is_type( 'percent' ) ) {
            $new_price = round( $orig_price * ( 1 - ( $coupon->get_amount() / 100 ) ), wc_get_price_decimals() );
        } else {
            $new_price = max( $orig_price - $coupon->get_amount(), 0 );
        }

        // 8) Store both values on the cart item for later (templates, emails…)
        $cart->cart_contents[ $cart_item_key ]['usc_original_sale_price'] = $orig_price;
        $cart->cart_contents[ $cart_item_key ]['usc_discounted_price']    = $new_price;

        // 9) Override the price that Woo displays/charges
        $product->set_regular_price( $orig_price );  // Replace regular price with sale price
        $product->set_price( $new_price );
    }
}

add_filter( 'woocommerce_cart_item_price', 'usc_discounted_cart_item_price_html', 10, 3 );
function usc_discounted_cart_item_price_html( $price_html, $cart_item, $cart_item_key ) {

    error_log( '-- usc_discounted_cart_item_price_html: Cart item price HTML: ' . $price_html );
    if ( empty( $cart_item['usc_discounted_price'] ) ) {
        return $price_html;
    }

    $orig_sale  = $cart_item['usc_original_sale_price'];
    error_log( 'Original sale price: ' . $orig_sale );

    
    $disc_price = $cart_item['usc_discounted_price'];

    return sprintf(
        '<del>Advertised Price: %1$s</del><br/><ins>Your Price: %2$s</ins>',
        wc_price( $orig_sale ),
        wc_price( $disc_price )
    );
}

// In your UBC plugin main file (e.g. ubc-plugin.php)

// add_action( 'wp_enqueue_scripts', 'ubc_enqueue_couponed_pricing_js' );
// function ubc_enqueue_couponed_pricing_js() {
//     // only if WooCommerce Blocks is active
//     if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
//         return;
//     }

//     wp_enqueue_script(
//         'ubc-couponed-pricing',
//         plugins_url( 'js/ubc-couponed-pricing.js', __FILE__ ),
//         [ 'wc-blocks-checkout', 'wc-blocks-cart' ],
//         '1.0',
//         true
//     );
// }

// add_action( 'wp_enqueue_scripts', function() {
//     // Make sure wc-blocks-checkout is a dependency so our code runs after the blocks JS
//     wp_enqueue_script(
//         'usc-cart-block-fix',
//         plugins_url( 'js/usc-cart-block-fix.js', dirname( __FILE__ ) ),
//         [ 'wc-blocks-checkout' ],
//         '1.0',
//         true
//     );
// } );

// /**
//  * 2) Swap out the cart-item price HTML so it shows “Advertised” (sale) vs. “Your Price” (sale + coupon).
//  */
// add_filter( 'woocommerce_cart_item_price', 'usc_discounted_cart_item_price_html', 10, 3 );
// function usc_discounted_cart_item_price_html( $price_html, $cart_item, $cart_item_key ) {
//     // Only for items we actually discounted
//     if ( empty( $cart_item['usc_discounted_price'] ) ) {
//         return $price_html;
//     }

//     $orig_sale   = $cart_item['usc_original_sale_price'];
//     $disc_price  = $cart_item['usc_discounted_price'];

//     return sprintf(
//         '<del>Advertised Price: %1$s</del><br/><ins>Your Price: %2$s</ins>',
//         wc_price( $orig_sale ),
//         wc_price( $disc_price )
//     );
// }


/**
 * Replace cart item subtotal with discounted subtotal when applicable.
 *
 * @param  string  $subtotal_html Cart item subtotal HTML.
 * @param  array   $cart_item     Cart item data.
 * @param  string  $cart_item_key Cart item key.
 * @return string                 Modified subtotal HTML.
 */
function usc_discounted_cart_item_subtotal_html( $subtotal_html, $cart_item, $cart_item_key ) {
    if ( ! is_user_logged_in() ) {
        return $subtotal_html;
    }
    $code = get_user_meta( get_current_user_id(), 'saved_coupon_code', true );
    if ( ! $code ) {
        error_log( 'No saved coupon code found for user ID: ' . get_current_user_id() );
        return $subtotal_html;
    }
    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_id() ) {
        return $subtotal_html;
    }
    $coupon_cat_ids = $coupon->get_product_categories();
    if ( empty( $coupon_cat_ids ) ) {
        return $subtotal_html;
    }
    $product = $cart_item['data'];
    $prod_cats = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
    if ( ! array_intersect( $coupon_cat_ids, $prod_cats ) ) {
        return $subtotal_html;
    }
    $new_price = usc_get_discounted_price( $product, $coupon );
    if ( false === $new_price ) {
        return $subtotal_html;
    }
    $qty           = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
    $new_subtotal  = $new_price * $qty;
    $subtotal_html = '<del>Advertised Subtotal: ' . wc_price( $product->get_price() * $qty ) . '</del> <br/><ins>Your Subtotal: ' . wc_price( $new_subtotal ) . '</ins>';
    return $subtotal_html;
}
add_filter( 'woocommerce_cart_item_subtotal', 'usc_discounted_cart_item_subtotal_html', 10, 3 );


// /**
//  * Override the mini-cart line to show:
//  *   <del>Advertised Price: {sale}</del>
//  *   <ins>Your Price:       {sale+coupon}</ins>
//  */
// add_filter( 'woocommerce_widget_cart_item_quantity', 'ubc_mini_cart_price_lines', 10, 3 );
// function ubc_mini_cart_price_lines( $html, $cart_item, $cart_item_key ) {
//     $product  = $cart_item['data'];
//     $qty      = $cart_item['quantity'];

//     // 1) Figure out the “advertised” price: use the sale price if it exists, otherwise regular
//     $sale = $product->get_sale_price();
//     $orig_price = ( '' !== $sale && null !== $sale )
//         ? floatval( $sale )
//         : floatval( $product->get_regular_price() );

//     // 2) Compute the coupon discount off that sale price
//     $user_id = get_current_user_id();
//     $code    = get_user_meta( $user_id, 'saved_coupon_code', true );
//     if ( ! $code ) {
//         return $html; // no saved coupon → fall back
//     }
//     $coupon = new WC_Coupon( $code );
//     if ( ! $coupon->get_id() ) {
//         return $html;
//     }
//     $new_price = usc_get_discounted_price( $product, $coupon );
//     if ( false === $new_price ) {
//         return $html;
//     }

//     // 3) Build our two-line price HTML
//     $orig_html = wc_price( $orig_price );
//     $disc_html = wc_price( $new_price );
//     $price_html = sprintf(
//         '<del>Advertised Price: %1$s</del><br/><ins>Your Price: %2$s</ins>',
//         $orig_html,
//         $disc_html
//     );

//     // 4) Return the quantity × our new price block
//     return sprintf(
//         '<span class="quantity">%1$d &times; %2$s</span>',
//         $qty,
//         $price_html
//     );
// }
