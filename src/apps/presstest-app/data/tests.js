/**
 * Available test suites.
 *
 * Single source of truth for suite slugs and display names used across
 * the Testing and Settings components.
 */
export const TEST_SUITES = [
	{ name: 'WordPress Core',    value: 'wordpress-core' },
	{ name: 'WooCommerce Core',  value: 'woocommerce-core' },
	{ name: 'Ascott Analytical', value: 'ascott-analytical' },
	{ name: 'PDS Printing',      value: 'pds-printing' },
	{ name: 'V&Me',      		 value: 'vandme' },
];

/**
 * TEST_SUITES mapped to react-select option format { value, label }.
 */
export const TEST_OPTIONS = TEST_SUITES.map( t => ( { value: t.value, label: t.name } ) );

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
