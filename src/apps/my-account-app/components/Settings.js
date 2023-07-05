import React, { useState } from 'react';
import axios from 'axios';
import Spinner from './Spinner';

function Settings() {
    // Handlers for the domain in th input field
    const [domain, updateDomain]              = useState('');
    // Handlers for the selected domain in the select field
    const [selectedDomain, setSelectedDomain] = useState('');
    // Domains loaded and updated as domain options for the select field
    const [domains, updateDomains]            = useState( window.my_account_app.domains );
    // Are we saving the settings?
    const [isSaving, setSavingStatus]     = useState( false );
    
    function addDomain( e ) {
        // @todo validate domain is URL and check it doesn't already exist
        e.preventDefault();
        updateDomains( domains => [...domains, domain] );
    };

    function removeDomain( e ) {
        e.preventDefault();
        updateDomains( domains => {
            const index = domains.indexOf( selectedDomain );
            if ( index !== -1 ) {
                domains.splice( index, 1 );
            }
            return domains;
        } );
        setSelectedDomain('');
    };

    // Send 
    const saveSettings = async ( e ) => {
        e.preventDefault();
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
            const response = await axiosInstance.post(
                window.wpApiSettings.root + 'wp/v2/users/me', {
                    'meta' : {
                        '_selected_domain' : selectedDomain,
                        '_domains': domains
                    }
                }
            );
        
            if ( ! response.ok ) {
                setSavingStatus( false );
                throw new Error( 'Failed to update user testing settings.' );
            }

            console.log( 'User testing settings updated successfully.' );
            setSavingStatus( false );

        } catch ( error ) {
            console.error( error.message );
            setSavingStatus( false );
        }
    };
    return (
        <div className='content-wrap'>
            <form onSubmit={saveSettings}>
                <fieldset>
                    <label>
                        <input type="text" name="new-domain" onChange={e => updateDomain(e.target.value)} value={domain} />
                        Add New Domain
                    </label>
                    <button id="add-domain" onClick={e => addDomain( e )} disabled={isSaving}>Add</button>
                </fieldset>
                <fieldset>
                    <label>Select domain to test:
                        <select name="domains" value={selectedDomain} onChange={e => setSelectedDomain(e.target.value)}>
                            <option value="null">Select a Domain...</option>
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