import React, { useEffect, useState } from 'react';
import useOnChangeEffect from '../hooks/useOnChangeEffect';
import axios from 'axios';
import { tests } from '../data/tests';
import validator from 'validator';
import Spinner from './Spinner';
import Select from 'react-select';

const settings              = window.presstest_companion;
const defaultSelectedDomain  = settings.user_settings.selected_domain  ?? '';
const defaultDomains         = settings.user_settings.domains          ?? [];
const defaultSelectedBrowser = settings.user_settings.selected_browser ?? '';

const BROWSERS = [
	{ value: 'chromium', label: 'Chrome' },
	{ value: 'firefox',  label: 'Firefox' },
	{ value: 'webkit',   label: 'Safari' },
];

function Testing() {
	const [domain, updateDomain]                = useState( '' );
	const [selectedDomain, setSelectedDomain]   = useState( defaultSelectedDomain );
	const [domains, updateDomains]              = useState( defaultDomains );
	const [availableTests, updateTests]         = useState( tests );
	const [selectedTests, updateSelectedTests]  = useState( [] );
	const [selectedBrowser, setSelectedBrowser] = useState( defaultSelectedBrowser );
	const [isTesting, setTestingStatus]         = useState( false );
	const [message, updateMessage]              = useState( { type: 'success', message: '' } );

	/**
	 * Validates the URL in the input field and appends it to the domains list.
	 *
	 * @param {object} e Event.
	 */
	function addDomain( e ) {
		e.preventDefault();
		updateMessage( {} );

		if ( ! validator.isURL( domain ) ) {
			updateMessage( { type: 'error', message: settings.domain_invalid_message } );
		} else if ( -1 !== domains.indexOf( domain ) ) {
			updateMessage( { type: 'error', message: settings.domain_already_exists_message } );
		} else {
			updateDomains( prev => [ ...prev, domain ] );
			updateDomain( '' );
			updateMessage( { type: 'message', message: settings.domain_added_message } );
		}
	}

	/**
	 * Removes the currently selected domain from the list.
	 *
	 * @param {object} e Event.
	 */
	function removeDomain( e ) {
		e.preventDefault();
		updateMessage( {} );

		if ( '' === selectedDomain ) {
			updateMessage( { type: 'error', message: settings.domain_not_selected_message } );
			return;
		}

		updateDomains( prev => prev.filter( d => d !== selectedDomain ) );
		updateMessage( { type: 'message', message: settings.domain_removed_message } );
		setSelectedDomain( '' );
	}

	/**
	 * Saves current settings to user meta via the WP REST API.
	 */
	const saveSettings = async () => {
		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		try {
			const response = await axiosInstance.post(
				window.wpApiSettings.root + 'wp/v2/users/me',
				{
					meta: {
						_presstest_settings: {
							domains,
							selected_domain:  selectedDomain,
							selected_tests:   selectedTests,
							selected_browser: selectedBrowser,
						},
					},
				}
			);

			if ( 200 !== response.status ) {
				throw new Error( 'Failed to save settings.' );
			}
		} catch ( error ) {
			console.error( error.message );
		}
	};

	/**
	 * Toggles the checked state of a test suite checkbox.
	 *
	 * @param {number} index Index of the test in the availableTests array.
	 */
	function handleTestUpdate( index ) {
		updateTests( prev => {
			const updated  = [ ...prev ];
			updated[index] = { ...updated[index], checked: ! updated[index].checked };
			return updated;
		} );
	}

	// Keep selectedTests in sync with the checkbox state.
	useEffect( () => {
		updateSelectedTests(
			availableTests
				.filter( t => t.checked )
				.map( t => t.value )
		);
	}, [availableTests] );

	/**
	 * Sends a proxied test run request through the WordPress REST API.
	 * The Presstest API key is resolved server-side and never exposed here.
	 *
	 * @param {object} e Event.
	 */
	const runTests = async ( e ) => {
		e.preventDefault();
		updateMessage( {} );

		if ( '' === selectedDomain ) {
			updateMessage( { type: 'error', message: settings.domain_not_selected_message } );
			return;
		}

		if ( 0 === selectedTests.length ) {
			updateMessage( { type: 'error', message: 'Please select at least one test suite.' } );
			return;
		}

		setTestingStatus( true );

		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		try {
			const response = await axiosInstance.post(
				window.wpApiSettings.root + 'presstest-companion/v1/run',
				{
					url:     selectedDomain,
					tests:   selectedTests.join( ',' ),
					browser: selectedBrowser,
				}
			);

			if ( 200 !== response.status ) {
				throw new Error( response.data?.message ?? 'Unexpected error.' );
			}

			setTestingStatus( false );
			updateMessage( { type: 'message', message: settings.tests_run_message } );
		} catch ( error ) {
			setTestingStatus( false );
			const msg = error.response?.data?.message ?? error.message;
			updateMessage( { type: 'error', message: msg + ': See console for more information.' } );
		}
	};

	// Auto-save settings whenever relevant state changes (skips the initial mount).
	useOnChangeEffect( () => {
		saveSettings();
	}, [domains, selectedDomain, selectedTests, selectedBrowser] );

	const domainOptions         = domains.map( d => ( { value: d, label: d } ) );
	const selectedDomainOption  = domainOptions.find( o => o.value === selectedDomain ) ?? null;
	const selectedBrowserOption = BROWSERS.find( b => b.value === selectedBrowser ) ?? null;

	return (
		<div className='content-wrap'>
			{ !! message.message && (
				<div className={'message-wrap woocommerce-' + message.type}>
					<p>{message.message}</p>
					<i className='close dashicons dashicons-dismiss' onClick={() => updateMessage( {} )}></i>
				</div>
			)}
			<form>
				<fieldset>
					<label htmlFor='new-domain' className='fieldset-instruction'>Add new Domain:</label>
					<input
						type='url'
						id='new-domain'
						className='input-text'
						onChange={e => updateDomain( e.target.value )}
						value={domain}
					/>
					<button id='add-domain' className='button primary' onClick={e => addDomain( e )} disabled={isTesting}>Add</button>
				</fieldset>
				<fieldset>
					<label htmlFor='domains' className='fieldset-instruction'>Select domain to test:</label>
					<Select
						value={selectedDomainOption}
						onChange={option => setSelectedDomain( option?.value ?? '' )}
						name='domains'
						options={domainOptions}
						menuPortalTarget={document.body}
					/>
					<button id='remove-domain' className='button primary' onClick={e => removeDomain( e )} disabled={isTesting}>Remove</button>
				</fieldset>
				<fieldset>
					<label className='fieldset-instruction'>Select tests to run:</label>
					{availableTests.map( ( { name, value, checked }, index ) => (
						<label key={value}>
							<input
								type='checkbox'
								name={value}
								value={value}
								onChange={() => handleTestUpdate( index )}
								checked={checked}
							/>
							{name}
						</label>
					))}
				</fieldset>
				<fieldset>
					<label htmlFor='browsers' className='fieldset-instruction'>Select browser to run tests in:</label>
					<Select
						value={selectedBrowserOption}
						onChange={option => setSelectedBrowser( option?.value ?? '' )}
						name='browsers'
						options={BROWSERS}
						menuPortalTarget={document.body}
					/>
				</fieldset>
				{isTesting
					? <Spinner />
					: <input type='submit' value='Run Tests' className='button primary' onClick={e => runTests( e )} />
				}
			</form>
		</div>
	);
}

export default Testing;
