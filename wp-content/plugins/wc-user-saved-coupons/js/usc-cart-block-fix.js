document.addEventListener( 'DOMContentLoaded', () => {
    const { registerCheckoutFilters } = window.wc?.blocksCheckout ?? {};
    if ( ! registerCheckoutFilters ) {
        return;
    }

    registerCheckoutFilters( 'usc-custom-pricing', {
        cartItemPrice: ( defaultValue, extensions, args ) => {
            // only in the Cart context
            if ( args?.context !== 'cart' ) {
                return defaultValue;
            }

            // pick the true sale price, or fall back to MSRP
            const saleOrMsrp = args.cartItem.prices.sale_price
                || args.cartItem.prices.regular_price;

            // <price/> will be replaced by the *discounted* price (sale + coupon)
            return `<del>Advertised Price: ${ saleOrMsrp }</del><br/><ins>Your Price: <price/></ins>`;
        },
    } );
} );
