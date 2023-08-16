export const browsers = [
    {
        'name': 'Chrome',
        'value': 'chromium',
        'checked': user_has_selected_browser( 'chromium' ),
    },
    {
        'name': 'Firefox',
        'value': 'firefox',
        'checked': user_has_selected_browser( 'firefox' ),
    },
    {
        'name': 'Safari',
        'value': 'webkit',
        'checked': user_has_selected_browser( 'webkit' ),
    }
]

/**
 * Checks if the given browser is selected by the user based on their user metadata
 * 
 * @param {string} browser 
 * @returns 
 */
function user_has_selected_browser( browser ) {
    if ( window.my_account_app.user_settings.selected_browsers === undefined ) {
        return false;
    }
    return window.my_account_app.user_settings.selected_browsers.includes( browser );
}