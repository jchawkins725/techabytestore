<?php
/**
 * Plugin Name: WooCommerce - User Saved Coupon
 * Description: Provides coupon codes for specific users and product categories in WooCommerce.
 * Version:     1.5
 * Author:      Realce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the coupon-save form and handle submission.
//  */
// function usc_save_coupon_shortcode() {
//     if ( ! is_user_logged_in() ) {
//         return '<p>You must be logged in to save a coupon code.</p>';
//     }

//     $user_id = get_current_user_id();
//     $saved   = get_user_meta( $user_id, 'saved_coupon_code', true );
//     $output  = '';

//     // Handle form submission
//     if ( isset( $_POST['usc_coupon_nonce'] ) && wp_verify_nonce( $_POST['usc_coupon_nonce'], 'usc_save_code' ) ) {
//         $code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );
//         if ( ! empty( $code ) ) {
//             update_user_meta( $user_id, 'saved_coupon_code', $code );
//             $saved  = $code;
//             $output .= '<p class="usc-success">Coupon code saved!</p>';
//         } else {
//             delete_user_meta( $user_id, 'saved_coupon_code' );
//             $saved   = '';
//             $output .= '<p class="usc-success">Coupon code cleared.</p>';
//         }
//     }

//     // Form
//     $output .= '<form method="post" class="usc-save-coupon-form">';
//     $output .= wp_nonce_field( 'usc_save_code', 'usc_coupon_nonce', true, false );
//     $output .= '<p>';
//     $output .= '<label for="coupon_code">Your Coupon Code:</label><br>';
//     $output .= '<input type="text" name="coupon_code" id="coupon_code" value="' . esc_attr( $saved ) . '" />';
//     $output .= '</p>';
//     $output .= '<p><button type="submit">Save Code</button></p>';
//     $output .= '</form>';

//     return $output;
// }
// add_shortcode( 'save_coupon', 'usc_save_coupon_shortcode' );

/**
 * Render the coupon-save form and handle submission—with validation.
 */

require_once plugin_dir_path( file: __FILE__ ) . 'includes/adjust-shop-price-html.php';
require_once plugin_dir_path( file: __FILE__ ) . 'includes/get-discounted-price-helper.php';  // Helper function to get discounted price
require_once plugin_dir_path( file: __FILE__ ) . 'includes/force-apply-or-remove-coupon-on-cart.php';  // Race condition handler to apply/remove coupon on cart page
require_once plugin_dir_path( file: __FILE__ ) . 'includes/call-for-pricing-handler.php';  // If product has no price, show "Call for pricing" message
require_once plugin_dir_path( file: __FILE__ ) . 'includes/add-plugin-coupon-field-to-wc-coupons.php';  // Add a checkbox field to the coupon edit page in the WooCommerce admin area

// function usc_save_coupon_shortcode() {
//     if ( ! is_user_logged_in() ) {
//         return '<p>You must be logged in to save a coupon code.</p>';
//     }

//     $user_id = get_current_user_id();
//     $saved   = get_user_meta( $user_id, 'saved_coupon_code', true );
//     $output  = '';

//     // Handle form submission
//     if ( isset( $_POST['usc_coupon_nonce'] ) && wp_verify_nonce( $_POST['usc_coupon_nonce'], 'usc_save_code' ) ) {
//         $code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );

//         if ( '' === $code ) {
//             // Empty = clear coupon
//             delete_user_meta( $user_id, 'saved_coupon_code' );
//             // wp_redirect( esc_url( $_SERVER['HTTP_REFERER'] ) );
//             echo '<script>window.location.reload();</script>';

//             exit;
//         } else {
//             // Validate that coupon exists
//             $coupon = new WC_Coupon( $code );
//             if ( $coupon->get_id() ) {
//                 // It’s real—save it
//                 update_user_meta( $user_id, 'saved_coupon_code', $code );
//                 echo '<script>window.location.reload();</script>';
//                 // wp_redirect( location: esc_url( $_SERVER['HTTP_REFERER'] ) );
//                  exit;
//             } else {
//                 // Invalid—don’t save, show error
//                 $output .= '<p class="usc-error">Invalid coupon code. Please try again.</p>';
//             }
//         }
//     }

//     // Print notices + form
//     $output .= '<form method="post" class="usc-save-coupon-form">';
//     $output .= wp_nonce_field( 'usc_save_code', 'usc_coupon_nonce', true, false );
//     $output .= '<p>';
//     $output .= '<label for="coupon_code">Your Coupon Code:</label><br>';
//     $output .= '<input type="text" name="coupon_code" id="coupon_code" value="' . esc_attr( $saved ) . '" />';
//     $output .= '</p>';
//     $output .= '<p><button type="submit">Save Code</button></p>';
//     $output .= '</form>';

//     return $output;
// }
// add_shortcode( 'save_coupon', 'usc_save_coupon_shortcode' );


//
// 1) Handle submission early, via template_redirect
//
add_action( 'template_redirect', 'usc_handle_coupon_submission' );
function usc_handle_coupon_submission() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Bail if our nonce isn't present or valid
    if ( empty( $_POST['usc_coupon_nonce'] ) || 
         ! wp_verify_nonce( wp_unslash( $_POST['usc_coupon_nonce'] ), 'usc_save_code' ) ) {
        return;
    }

    $user_id = get_current_user_id();
    $code    = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );
    // Figure out where to send them back
	// Get the My Account page permalink
	$myaccount_page_id  = wc_get_page_id( 'myaccount' );             // grabs the ID of your My Account page
	$myaccount_permalink = get_permalink( $myaccount_page_id );     // full URL, including /staging/6541/ if you’re on staging

	// Build the saved-coupons endpoint URL off of that
	$redirect_url = wc_get_endpoint_url(
		'saved-coupons',   // your custom endpoint slug
		'',                // no extra value appended
		$myaccount_permalink
	);
    if ( '' === $code ) {
        // Clearing the saved coupon
        delete_user_meta( $user_id, 'saved_coupon_code' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // Validate coupon
    $coupon = new WC_Coupon( $code );
    if ( $coupon->get_id() ) {
        update_user_meta( $user_id, 'saved_coupon_code', $code );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // Invalid code → tack on a query var so we can show an error
    $redirect_url = add_query_arg( 'usc_coupon_error', '1', $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}

//
// 2) Shortcode just prints the form (and any error message) — no redirects or JS hacks here
//
add_shortcode( 'save_coupon', 'usc_save_coupon_shortcode' );
function usc_save_coupon_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to save a coupon code.</p>';
    }

    $user_id = get_current_user_id();
    $saved   = get_user_meta( $user_id, 'saved_coupon_code', true );
    $output  = '';

    // Show error if we got ?usc_coupon_error=1
    if ( isset( $_GET['usc_coupon_error'] ) ) {
        $output .= '<p class="usc-error">Invalid coupon code. Please try again.</p>';
    }

    // Build the form
    $action = esc_url( $_SERVER['REQUEST_URI'] );
    $output .= '<form method="post" action="' . $action . '" class="usc-save-coupon-form">';
    $output .= wp_nonce_field( 'usc_save_code', 'usc_coupon_nonce', true, false );
    $output .= '<p><label for="coupon_code">Your Coupon Code:</label><br>';
    $output .= '<input type="text" name="coupon_code" id="coupon_code" value="' . esc_attr( $saved ) . '" /></p>';
    $output .= '<p><button type="submit">Save Code</button></p>';
    $output .= '</form>';

    return $output;
}

/**
 * Display the saved coupon code and its restricted product categories.
 */
function usc_show_saved_coupon_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    $user_id = get_current_user_id();
    $code    = get_user_meta( $user_id, 'saved_coupon_code', true );

    if ( empty( $code ) ) {
        return '<p>No coupon code saved.</p>';
    }

    // Validate coupon
    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_id() ) {
        return '<p>Saved coupon code is invalid.</p>';
    }

    // Get restricted category IDs
    $cat_ids = $coupon->get_product_categories(); // array of term IDs

    // Turn them into names
    $cat_names = [];
    if ( ! empty( $cat_ids ) ) {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'include'    => $cat_ids,
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $terms ) ) {
            $cat_names = wp_list_pluck( $terms, 'name' );
        }
    }

    // Build output
    $output  = '<p>Your saved coupon: <strong>' . esc_html( $code ) . '</strong></p>';
    if ( ! empty( $cat_names ) ) {
        $output .= '<p>Product Category: <strong>' . esc_html( implode( ', ', $cat_names ) ) . '</strong></p>';
    } else {
        $output .= '<p><em>This coupon applies to all categories.</em></p>';
    }

    return $output;
}
add_shortcode( 'show_saved_coupon', 'usc_show_saved_coupon_shortcode' );



// /**
//  * Display the saved coupon code and its restricted product categories.
//  */
// function usc_show_saved_coupon_shortcode() {
//     if ( ! is_user_logged_in() ) {
//         return '';
//     }

//     $user_id = get_current_user_id();
//     $code    = get_user_meta( $user_id, 'saved_coupon_code', true );

//     if ( empty( $code ) ) {
//         return '<p>No coupon code saved.</p>';
//     }

//     // Validate coupon
//     $coupon = new WC_Coupon( $code );
//     if ( ! $coupon->get_id() ) {
//         return '<p>Saved coupon code is invalid.</p>';
//     }

//     // Get restricted category IDs
//     $cat_ids = $coupon->get_product_categories(); // array of term IDs

//     // Turn them into names
//     $cat_names = [];
//     if ( ! empty( $cat_ids ) ) {
//         $terms = get_terms( [
//             'taxonomy'   => 'product_cat',
//             'include'    => $cat_ids,
//             'hide_empty' => false,
//         ] );
//         if ( ! is_wp_error( $terms ) ) {
//             $cat_names = wp_list_pluck( $terms, 'name' );
//         }
//     }

//     // Build output
//     $output  = '<p>Your saved coupon: <strong>' . esc_html( $code ) . '</strong></p>';
//     if ( ! empty( $cat_names ) ) {
//         $output .= '<p>Product Category: <strong>' . esc_html( implode( ', ', $cat_names ) ) . '</strong></p>';
//     } else {
//         $output .= '<p><em>This coupon applies to all categories.</em></p>';
//     }

//     return $output;
// }
// add_shortcode( 'show_saved_coupon', 'usc_show_saved_coupon_shortcode' );



/**
 * Add a “Saved Coupon Code” field to user profiles.
 */
function usc_profile_coupon_field( $user ) {
    // Only show to users with edit_users capability (admins), or on your own profile
    if ( ! current_user_can( 'edit_user', $user->ID ) && get_current_user_id() !== $user->ID ) {
        return;
    }

    $saved = get_user_meta( $user->ID, 'saved_coupon_code', true );
    ?>
    <h3><?php _e( 'Saved Coupon Code', 'user-saved-coupon' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="saved_coupon_code"><?php _e( 'Coupon Code', 'user-saved-coupon' ); ?></label></th>
            <td>
                <input
                    type="text"
                    name="saved_coupon_code"
                    id="saved_coupon_code"
                    value="<?php echo esc_attr( $saved ); ?>"
                    class="regular-text"
                /><br/>
                <span class="description"><?php _e( 'Enter a coupon code to save for this user (leave blank to clear).', 'user-saved-coupon' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'usc_profile_coupon_field' );
add_action( 'edit_user_profile', 'usc_profile_coupon_field' );


/**
 * Look in our plugin’s woocommerce/cart folder before falling back to the theme.
 */
add_filter( 'woocommerce_locate_template', 'ubc_locate_woocommerce_template', 10, 3 );
function ubc_locate_woocommerce_template( $template, $template_name, $template_path ) {
    // only intercept the mini-cart template
    if ( 'cart/mini-cart.php' === $template_name ) {
        $plugin_template = plugin_dir_path( __FILE__ )
                         . 'woocommerce/'
                         . $template_name;
        if ( file_exists( $plugin_template ) ) {
            error_log( 'Using plugin template: ' . $plugin_template );
            return $plugin_template;
        } else {
            error_log( 'Missing template: ' . $plugin_template );
        }
    }
    return $template;
}
/**
 * Save the “Saved Coupon Code” when the profile is updated.
 */
function usc_save_profile_coupon_field( $user_id ) {
    // Check capability
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( isset( $_POST['saved_coupon_code'] ) ) {
        $code = sanitize_text_field( wp_unslash( $_POST['saved_coupon_code'] ) );
        if ( '' !== $code ) {
            update_user_meta( $user_id, 'saved_coupon_code', $code );
        } else {
            delete_user_meta( $user_id, 'saved_coupon_code' );
        }
    }
}
add_action( 'personal_options_update', 'usc_save_profile_coupon_field' );
add_action( 'edit_user_profile_update', 'usc_save_profile_coupon_field' );


// 1) Register the endpoint so WP knows about it
function usc_add_coupon_endpoint() {
    add_rewrite_endpoint( 'saved-coupons', EP_PAGES );
}
add_action( 'init', 'usc_add_coupon_endpoint' );

// 2) Make sure WP recognizes our new query var
function usc_coupon_query_vars( $vars ) {
    $vars[] = 'saved-coupons';
    return $vars;
}
add_filter( 'query_vars', 'usc_coupon_query_vars', 0 );

// 3) Insert our menu item into the My Account nav
function usc_coupon_menu_item( $items ) {
    // Insert after “dashboard” (first item)
    $new_items = array();
    foreach ( $items as $key => $label ) {
        $new_items[ $key ] = $label;
        if ( 'dashboard' === $key ) {
            $new_items['saved-coupons'] = __( 'My Coupons', 'user-saved-coupon' );
        }
    }
    return $new_items;
}
add_filter( 'woocommerce_account_menu_items', 'usc_coupon_menu_item' );

// 4) Display the shortcodes on that endpoint’s page
function usc_coupon_endpoint_content() {
    echo '<h3>' . esc_html__( 'Your Saved Coupon', 'user-saved-coupon' ) . '</h3>';
    echo do_shortcode( '[show_saved_coupon]' );
    echo do_shortcode( '[save_coupon]' );
}
add_action( 'woocommerce_account_saved-coupons_endpoint', 'usc_coupon_endpoint_content' );

// 5) Flush rewrite rules on plugin activation/deactivation
function usc_coupon_flush_rewrites() {
    usc_add_coupon_endpoint();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'usc_coupon_flush_rewrites' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );


/**
 * 1) Add a “Restrict to user” dropdown under Usage Restrictions.
 *
 * @param int       $post_id Coupon post ID.
 * @param WC_Coupon $coupon  Coupon object.
 */
function usc_coupon_user_restriction_field( $post_id, $coupon ) {
    // Fetch all users you want to allow here (e.g. customers/subscribers)
    $users = get_users( [ 'role__in' => [ 'customer', 'subscriber' ] ] );
    $options = [ '' => __( '— Select a user —', 'user-saved-coupon' ) ];

    foreach ( $users as $user ) {
        $options[ $user->ID ] = sprintf(
            '%s (%s)',
            esc_html( $user->display_name ),
            esc_html( $user->user_email )
        );
    }

    woocommerce_wp_select( [
        'id'      => '_user_restriction',
        'label'   => __( 'Restrict to user', 'user-saved-coupon' ),
        'options' => $options,
        'value'   => '', // we only ever append, so no prefill
        'desc_tip'=> true,
        'description' => __( 'Choose a user whose email will be added to Allowed emails.', 'user-saved-coupon' ),
    ] );
}
add_action( 'woocommerce_coupon_options_usage_restriction', 'usc_coupon_user_restriction_field', 10, 2 );  
// :contentReference[oaicite:0]{index=0}


/**
 * 2) On coupon save, grab that user’s email and append it to customer_email meta.
 *
 * @param int     $post_id Coupon post ID.
 * @param WP_Post $post    Post object.
 */
function usc_save_coupon_user_restriction( $post_id, $post ) {
    if ( isset( $_POST['_user_restriction'] ) && $user_id = intval( $_POST['_user_restriction'] ) ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            $email = sanitize_email( $user->user_email );

            // Pull existing allowed emails (meta key 'customer_email')
            $emails = get_post_meta( $post_id, 'customer_email', true );
            if ( ! is_array( $emails ) ) {
                $emails = [];
            }

            // Add & dedupe
            $emails[] = $email;
            $emails   = array_unique( array_map( 'sanitize_email', $emails ) );

            update_post_meta( $post_id, 'customer_email', $emails );
        }
    }
}
add_action( 'woocommerce_coupon_options_save', 'usc_save_coupon_user_restriction', 10, 2 );  
// :contentReference[oaicite:1]{index=1}

// Add a settings page under the Settings menu.
add_action( 'admin_menu', 'usc_add_settings_page' );
function usc_add_settings_page() {
    add_options_page(
        'User Saved Coupon Settings',   // Page title.
        'User Saved Coupon',            // Menu title.
        'manage_options',               // Capability.
        'usc-settings',                 // Menu slug.
        'usc_render_settings_page'      // Callback to render the page.
    );
}

// Register settings, sections, and fields.
add_action( 'admin_init', 'usc_register_settings' );
function usc_register_settings() {
    // Register a sample setting.
    register_setting( 'usc_settings_group', 'usc_original_price_text' );
    register_setting( 'usc_settings_group', 'usc_strike_thru_original_price_text' );
    register_setting( 'usc_settings_group', 'usc_discounted_price_text' );
    register_setting( 'usc_settings_group', 'usc_call_for_price_text' );
    register_setting( 'usc_settings_group', 'usc_call_for_price_hyperlink_url' );
    register_setting( 'usc_settings_group', 'usc_use_hyperlink_for_call_for_pricing_text' );

    // Add a settings section.
    add_settings_section(
        'usc_main_section',             // ID.
        'Main Settings',                // Title.
        'usc_section_callback',         // Callback.
        'usc-settings'                  // Page.
    );

    // Add a settings field for Discounted Price Text.
    add_settings_field(
        'usc_strike_thru_original_price_text',          // Field ID.
        'Strike-thru Original Price Text?',              // Title.
        'usc_strike_thru_original_price_text_callback', // Callback.
        'usc-settings',                       // Page.
        'usc_main_section'                    // Section.
    );

        // Add a settings field for Discounted Price Text.
        add_settings_field(
            'usc_original_price_text',          // Field ID.
            'Original Price Text',              // Title.
            'usc_original_price_text_callback', // Callback.
            'usc-settings',                       // Page.
            'usc_main_section'                    // Section.
        );

    // Add a settings field for Discounted Price Text.
    add_settings_field(
        'usc_discounted_price_text',          // Field ID.
        'Discounted Price Text',              // Title.
        'usc_discounted_price_text_callback', // Callback.
        'usc-settings',                       // Page.
        'usc_main_section'                    // Section.
    );

    // Add a settings field for Call for pricing text.
    add_settings_field(
        'usc_call_for_price_hyperlink_url',          // Field ID.
        'Call for Price URL',              // Title.
        'usc_call_for_price_hyperlink_url_callback', // Callback.
        'usc-settings',                       // Page.
        'usc_main_section'                    // Section.
    );
    

            // Add a settings field for Call for pricing text.
            add_settings_field(
                'usc_use_hyperlink_for_call_for_pricing_text',          // Field ID.
                'Use hyperlink for Call For Pricing text?',              // Title.
                'usc_use_hyperlink_for_call_for_pricing_text_callback', // Callback.
                'usc-settings',                       // Page.
                'usc_main_section'                    // Section.
            );

        // Add a settings field for Call for pricing text.
        add_settings_field(
            'usc_call_for_price_text',          // Field ID.
            'Call for Price Text',              // Title.
            'usc_call_for_price_text_callback', // Callback.
            'usc-settings',                       // Page.
            'usc_main_section'                    // Section.
        );
}

// Callback to display the section description.
function usc_section_callback() {
    echo '<p>Main settings for the User Saved Coupon plugin.</p>';
}

// Callback for the original price text field.
function usc_original_price_text_callback() {
    $value = get_option( 'usc_original_price_text', '' );
    echo '<input type="text" name="usc_original_price_text" value="' . esc_attr( $value ) . '" />';
}

// Callback for the original price text field.
function usc_call_for_price_hyperlink_url_callback() {
    $value = get_option( 'usc_call_for_price_hyperlink_url', '' );
    echo '<input type="text" name="usc_call_for_price_hyperlink_url" value="' . esc_attr( $value ) . '" />';
}

// Sanitize callback for checkboxes.
function usc_sanitize_checkbox( $input ) {
    return $input === 'checked' ? 'checked' : '';
}

// Register the setting with the sanitize callback.
register_setting( 'usc_settings_group', 'usc_strike_thru_original_price_text', 'usc_sanitize_checkbox' );


// Register the setting with the sanitize callback.
register_setting( 'usc_settings_group', 'usc_use_hyperlink_for_call_for_pricing_text', 'usc_sanitize_checkbox' );

// Callback for the strike-thru original price text checkbox.
function usc_use_hyperlink_for_call_for_pricing_text_callback() {
    $option  = get_option( 'usc_use_hyperlink_for_call_for_pricing_text', '' );
    $checked = $option === 'checked' ? 'checked' : '';
    echo '<input type="checkbox" name="usc_use_hyperlink_for_call_for_pricing_text" ' . $checked . ' value="checked" />';
}


// Callback for the strike-thru original price text checkbox.
function usc_strike_thru_original_price_text_callback() {
    $option  = get_option( 'usc_strike_thru_original_price_text', '' );
    $checked = $option === 'checked' ? 'checked' : '';
    echo '<input type="checkbox" name="usc_strike_thru_original_price_text" ' . $checked . ' value="checked" />';
}

// Callback for the discounted price text field.
function usc_discounted_price_text_callback() {
    $value = get_option( 'usc_discounted_price_text', '' );
    echo '<input type="text" name="usc_discounted_price_text" value="' . esc_attr( $value ) . '" />';
}

// Callback for the discounted price text field.
function usc_call_for_price_text_callback() {
    $value = get_option( 'usc_call_for_price_text', '' );
    echo '<input type="text" name="usc_call_for_price_text" value="' . esc_attr( $value ) . '" />';
}
// Render the settings page.
function usc_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>User Saved Coupon Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'usc_settings_group' );
            do_settings_sections( 'usc-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}