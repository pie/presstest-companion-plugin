import React, { useEffect, useState } from 'react';
import useOnChangeEffect from '../hooks/useOnChangeEffect';
import axios from 'axios';
import { tests } from "../data/tests";
import validator from 'validator'
import Spinner from './Spinner';
import Select from 'react-select';

const defaultSelectedDomain  = window.my_account_app.user_settings.selected_domain ? window.my_account_app.user_settings.selected_domain : '';
const defaultDomains         = window.my_account_app.user_settings.domains ? window.my_account_app.user_settings.domains : [];
const defaultSelectedBrowser = window.my_account_app.user_settings.selected_browser ? window.my_account_app.user_settings.selected_browser : '';

function Testing() {
    // Handlers for the domain in the input field
    const [domain, updateDomain]                     = useState( '' );
    // Handlers for the selected domain in the select field
    const [selectedDomain, setSelectedDomain]        = useState( defaultSelectedDomain );
    // Domains loaded and updated as domain options for the select field
    const [domains, updateDomains]                   = useState( defaultDomains );
    // Handlers for the selected test from given checkboxes
    const [availableTests, updateTests]              = useState( tests );
    // Handlers for the users selected tests
    const [selectedTests, updateSelectedTests]       = useState( [] );
    // Handlers for the users selected browser
    const [selectedBrowser, setSelectedBrowser]      = useState( defaultSelectedBrowser );
    // Handlers for the selected browser in the select field
    const [browsers]                                 = useState( [ 'chrome', 'firefox', 'safari' ] );
    // URL for the test suite (populated with useEffect hook)
    const [apiUrl, updateApiUrl]                     = useState( '' );
    // Are we running tests?
    const [isTesting, setTestingStatus]              = useState( false );
    // Notification error/message
    const [message, updateMessage]                   = useState( { 'type' : 'success', 'message' : '' } );
    
    /**
     * Takes the URL from the input, checks if it already exists in the select options and adds it if it doesn't
     * 
     * @param {object} e event
     */
    function addDomain( e ) {
        e.preventDefault();
        updateMessage( {} );
        if( ! isValidUrl( domain ) ) {
            updateMessage(  { 'type':'error', 'message':window.my_account_app.domain_invalid_message } );
        } else if ( domains.indexOf( domain ) > -1 ) {
            updateMessage( { 'type':'error', 'message':window.my_account_app.domain_already_exists_message } );
        } else {
            updateDomains( domains => [...domains, domain] );
            updateDomain( '' );
            updateMessage( { 'type':'message', 'message':window.my_account_app.domain_added_message } );
        }
    };

    /**
     * Removes the currently selected URL from the domains dropdown
     * 
     * @param {object} e event 
     */
    function removeDomain( e ) {
        e.preventDefault();
        updateMessage( {} );
        if ( '' === selectedDomain ) {
            // No domain selected
            updateMessage( { 'type':'error', 'message':window.my_account_app.domain_not_selected_message } );
        } else {
            updateDomains( domains => {
                const index = domains.indexOf( selectedDomain );
                if ( index !== -1 ) {
                    domains.splice( index, 1 );
                }
                return domains;
            } );
            updateMessage( { 'type':'message', 'message':window.my_account_app.domain_removed_message } );
            setSelectedDomain('');
        }
    };

    function updateSelectedDomain( input ) {
        setSelectedDomain( input.value );
    }

    function updateSelectedBrowser( input ) {
        setSelectedBrowser( input.value );
    }

    /**
     * Send request to update usermeta with the provided domains
     * (saves the domains list as well as the currently selected domain)
     * 
     * @param {object} e event 
     */
    const saveSettings = async () => {

        // Set up the Axios instance with interceptors
        const axiosInstance = axios.create();

        // Add the interceptors to modify the request before sending
        axiosInstance.interceptors.request.use(( config ) => {
            // Modify the request config before sending
            config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce; // Set the nonce header

            return config;
        });
    
        try {
            // me = current user
            const response = await axiosInstance.post(
                window.wpApiSettings.root + 'wp/v2/users/me', {
                    'meta' : {
                        '_presstest_settings' : {
                            'domains' : domains,
                            'selected_domain' : selectedDomain,
                            'selected_tests' : selectedTests,
                            'selected_browser' : selectedBrowser,
                        }
                    }
                }
            );
        
            // Response not 200
            if ( 200 !== response.status ) {
                throw new Error( 'error' );
            }

        } catch ( error ) {
            console.log( error.message );
        }
    };

    /**
     * Fires when user updates checkboxes to select tests
     * Sets the checked status within the availableTests array
     * 
     * @param {int} index 
     */
    function handleTestUpdate( index ) {
        updateTests( availableTests => {
            availableTests[index].checked = ! availableTests[index].checked;
            return [...availableTests];
        } );
    }

    /**
     * Updates the users selected tests based on their checkboxes
     * Fires once on load and each time availableTests is updated (when checkboxes are changed)
     */
    useEffect(() => {
        updateSelectedTests( selectedTests => {
            return availableTests.filter( function( obj ) {
                return obj.checked;
            }).map( a => a.value );
        } );
    }, [availableTests]);

    /**
     * Send request to testing server to run selected tests for the selected domain
     * 
     * @param {object} e event 
     */
    const runTests = async ( e ) => {
        e.preventDefault();
        updateMessage( {} );
        setTestingStatus( true );

        try {
            const response = await axios.get(
                apiUrl
            );
        
            // Response not 200
            if ( 200 !== response.status ) {
                throw new Error( response.status_message );
            }

            // Ran tests successfully
            setTestingStatus( false );
            updateMessage( { 'type':'message', 'message':response.data.status_message } );

        } catch ( error ) {
            // Error
            setTestingStatus( false );
            updateMessage( { 'type':'error', 'message':error.message + ': See console for more information' } );
        }
    };

    /**
     * Updates the api URL whenever the domain or test selection is updated
     * @todo move root to db and localise
     */
    useEffect(() => {
        updateApiUrl( apiUrl => {
            return 'https://212.71.232.30/TestSuite/api.php?url='+selectedDomain+'&user_id='+window.my_account_app.user_id+'&tests='+selectedTests.join( ',' )+'&browser='+selectedBrowser;
        });
    }, [selectedDomain, selectedTests, selectedBrowser]);

    /**
     * Whenever the given options are updated, update the entries in the users metadata
     */
    useOnChangeEffect( () => {
        saveSettings();
    }, [domains, selectedDomain, selectedTests, selectedBrowser])

    /**
     * Checks given URL is valid format
     * 
     * @param {string} url 
     * @returns boolean
     */
    const isValidUrl = ( url ) => {
        if ( validator.isURL( url ) ) {
            return true;
        } else {
            return false;
        }
    };

    function generateDomainsArray() {
        return domains.map( domain => (
            { 'value':domain, 'label':domain }
        ));
    };

    function generateBrowsersArray() {
        return browsers.map( browser => (
            { 'value':browser, 'label':browser }
        ));
    };

    return (
        <div className='content-wrap'>
            { !! message.message && <div className={'message-wrap woocommerce-'+message.type}>
                <p>{message.message}</p><i className='close dashicons dashicons-dismiss' onClick={e => updateMessage({})}></i>
            </div> }
            <form>
                <fieldset>
                    <label for="new-domain" class="fieldset-instruction">Add new Domain:</label>
                    <input type="url" class="input-text" name="new-domain" onChange={e => updateDomain(e.target.value)} value={domain} /> 
                    <button id="add-domain" class="button primary" onClick={e => addDomain( e )} disabled={isTesting}>Add</button>
                </fieldset>
                <fieldset>
                    <label for="domains" class="fieldset-instruction">Select domain to test:</label>
                    <Select
                        value={{ value: selectedDomain, label: selectedDomain }}
                        onChange={updateSelectedDomain}
                        name="domains"
                        options={generateDomainsArray()}
                        menuPortalTarget={document.body}
                    />
                    <button id="remove-domain" class="button primary" onClick={e => removeDomain( e )} disabled={isTesting}>Remove</button>
                </fieldset>
                <fieldset>
                    <label for="tests" class="fieldset-instruction">Select tests to run:</label>
                    { tests.map(({ name, value, checked }, index) => {
                        return (
                            <label>
                                <input type="checkbox" name={value} value={value} onChange={e => handleTestUpdate(index)} checked={checked} />
                                {name}
                            </label>
                          );
                    }) }
                </fieldset>
                <fieldset>
                    <label for="browsers" class="fieldset-instruction">Select browser to run tests in:</label>
                    <Select
                        value={{ value: selectedBrowser, label: selectedBrowser }}
                        onChange={updateSelectedBrowser}
                        name="browsers"
                        options={generateBrowsersArray()}
                        menuPortalTarget={document.body}
                    />
                </fieldset>
                {isTesting ? <Spinner /> : <input type="submit" value="Run Tests" class="button primary" onClick={e => runTests(e)} /> }
            </form>
        </div>
    );
};
export default Testing;