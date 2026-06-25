(function(){
    /**
     * Register both filters against whichever registry is available.
     */
    function initFilters() {
        const blocks = window.wc?.blocksCheckout;
        const mini   = window.wc?.blocksCart;

        const cb = ( registryFn ) => {
            registryFn( 'ubc-couponed-pricing', {
                // 1) Cross out the true sale price (or MSRP if no sale)
                saleBadgePriceFormat: ( defaultValue, extensions, args ) => {
                    const ctx = args?.context;
                    if ( ctx !== 'cart' && ctx !== 'summary' ) {
                        return defaultValue;
                    }
                    // <price/> here becomes args.cartItem.prices.sale_price
                    return `Advertised Price: <price/>`;
                },
                // 2) Show the final price with your coupon applied
                cartItemPrice: ( defaultValue, extensions, args ) => {
                    const ctx = args?.context;
                    if ( ctx !== 'cart' && ctx !== 'summary' ) {
                        return defaultValue;
                    }
                    // <price/> here becomes args.cartItem.prices.price
                    return `Your Price: <price/>`;
                }
            } );
        };

        if ( blocks?.registerCheckoutFilters ) {
            cb( blocks.registerCheckoutFilters );
        }
        if ( mini?.registerCartFilters ) {
            cb( mini.registerCartFilters );
        }
    }

    // Poll until the Blocks API is ready
    function ready() {
        if ( window.wc?.blocksCheckout?.registerCheckoutFilters 
          && window.wc?.blocksCart?.registerCartFilters ) {
            initFilters();
        } else {
            setTimeout( ready, 200 );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', ready );
    } else {
        ready();
    }
})();
