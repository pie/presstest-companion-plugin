export const tests = [
	{
		name:    'WordPress Core',
		value:   'wordpress-core',
		checked: user_has_selected_test( 'wordpress-core' ),
	},
	{
		name:    'WooCommerce Core',
		value:   'woocommerce-core',
		checked: user_has_selected_test( 'woocommerce-core' ),
	},
	{
		name:    'Ascott Analytical',
		value:   'ascott-analytical',
		checked: user_has_selected_test( 'ascott-analytical' ),
	},
];

/**
 * Returns true if the user previously selected the given test suite.
 *
 * @param {string} test Suite directory name.
 * @returns {boolean}
 */
function user_has_selected_test( test ) {
	const selected = window.presstest_companion?.user_settings?.selected_tests;
	return Array.isArray( selected ) && selected.includes( test );
}
