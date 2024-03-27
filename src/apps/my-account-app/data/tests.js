export const tests = [
    {
        'name': 'Wordpress Core',
        'value': 'wordpress-core',
        'checked': user_has_selected_test( 'wordpress-core' ),
    },
    {
        'name': 'Nature Studio',
        'value': 'nature-studio',
        'checked': user_has_selected_test( 'nature-studio' ),
    },
    {
        'name': 'WooCommerce Core',
        'value': 'woocommerce-core',
        'checked': user_has_selected_test( 'woocommerce-core' ),
    }
]

/**
 * Checks if the given test is selected by the user based on their user metadata
 * 
 * @param {string} test 
 * @returns 
 */
function user_has_selected_test( test ) {
    if ( window.my_account_app.user_settings.selected_tests === undefined ) {
        return false;
    }
    return window.my_account_app.user_settings.selected_tests.includes( test );
}