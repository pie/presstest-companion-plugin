import React, { useState } from 'react';
import axios from 'axios';
import validator from 'validator'
import Spinner from './Spinner';

function Settings() {
    // Handlers for the domain in the input field
    const [domain, updateDomain]              = useState( '' );
    // Handlers for the selected domain in the select field
    const [selectedDomain, setSelectedDomain] = useState( window.my_account_app.selected_domain );
    // Domains loaded and updated as domain options for the select field
    const [domains, updateDomains]            = useState( window.my_account_app.domains );
    // Are we saving the settings?
    const [isSaving, setSavingStatus]         = useState( false );
    // Notification error/message
    const [message, updateMessage]            = useState( '' );
    
    /**
     * Takes the URL from the input, checks if it already exists in the select options and adds it if it doesn't
     * 
     * @param {object} e event
     */
    function addDomain( e ) {
        e.preventDefault();
        updateMessage( '' );
        if( ! isValidUrl( domain ) ) {
            updateMessage( window.my_account_app.domain_invalid_message );
        } else if ( domains.indexOf( domain ) > -1 ) {
            updateMessage( window.my_account_app.domain_already_exists_message );
        } else {
            updateDomains( domains => [...domains, domain] );
            updateDomain( '' );
            updateMessage( window.my_account_app.domain_added_message );
        }
    };

    /**
     * Removes the currently selected URL from the domains dropdown
     * 
     * @param {object} e event 
     */
    function removeDomain( e ) {
        e.preventDefault();
        updateMessage( '' );
        if ( '' == selectedDomain ) {
            // No domain selected
            updateMessage( window.my_account_app.domain_not_selected_message );
        } else {
            updateDomains( domains => {
                const index = domains.indexOf( selectedDomain );
                if ( index !== -1 ) {
                    domains.splice( index, 1 );
                }
                return domains;
            } );
            updateMessage( window.my_account_app.domain_removed_message );
            setSelectedDomain('');
        }
    };

    /**
     * Send request to update usermeta with the provided domains
     * (saves the domains list as well as the currently selected domain)
     * 
     * @param {object} e event 
     */
    const saveSettings = async ( e ) => {
        e.preventDefault();
        updateMessage( '' );
        setSavingStatus( true );

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
                        '_selected_domain' : selectedDomain,
                        '_domains': domains
                    }
                }
            );
        
            // Response not 200
            if ( 200 != response.status ) {
                console.log( response );
                throw new Error( 'Failed to update user testing settings.' );
            }

            // Updated metadata successfully
            setSavingStatus( false );
            updateMessage( window.my_account_app.settings_saved_message );

        } catch ( error ) {
            // Error
            setSavingStatus( false );
            updateMessage( window.my_account_app.settings_error_message_intro + error.message + '.  ' + window.my_account_app.settings_generic_error_outro );
        }
    };

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

    return (
        <div className='content-wrap'>
            { !! message && <div className='message-wrap'>
                <p>{message}</p><span onClick={e => updateMessage('')}>X</span>
            </div> }
            <form onSubmit={saveSettings}>
                <fieldset>
                    <label>
                        <input type="url" name="new-domain" onChange={e => updateDomain(e.target.value)} value={domain} />
                        Add New Domain
                    </label>
                    <button id="add-domain" onClick={e => addDomain( e )} disabled={isSaving}>Add</button>
                </fieldset>
                <fieldset>
                    <label>Select domain to test:
                        <select name="domains" value={selectedDomain} onChange={e => setSelectedDomain(e.target.value)}>
                            <option value="">Select a Domain...</option>
                            {domains.map( currentDomain => (
                                <option key={currentDomain} value={currentDomain}>
                                    {currentDomain}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button id="remove-domain" onClick={e => removeDomain( e )} disabled={isSaving}>Remove</button>
                </fieldset>
                {isSaving ? <Spinner /> : <input type="submit" value="Save" disabled={isSaving} /> }
            </form>
        </div>
    );
};
export default Settings;