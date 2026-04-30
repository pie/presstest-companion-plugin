import React, { useEffect, useState } from 'react';
import useOnChangeEffect from '../hooks/useOnChangeEffect';
import axios from 'axios';
import { tests } from '../data/tests';
import Spinner from './Spinner';
import Select from 'react-select';

const settings = window.presstest_companion;

const BROWSERS = [
	{ value: 'chromium', label: 'Chrome' },
	{ value: 'firefox',  label: 'Firefox' },
	{ value: 'webkit',   label: 'Safari' },
];

function Testing() {
	const [availableTests, updateTests]         = useState( tests );
	const [selectedTests, updateSelectedTests]  = useState( [] );
	const [selectedBrowser, setSelectedBrowser] = useState( settings.user_settings?.selected_browser ?? '' );
	const [isTesting, setTestingStatus]         = useState( false );
	const [message, updateMessage]              = useState( { type: '', message: '' } );

	// Keep selectedTests in sync with the checkbox state.
	useEffect( () => {
		updateSelectedTests(
			availableTests
				.filter( t => t.checked )
				.map( t => t.value )
		);
	}, [availableTests] );

	/**
	 * Persists selected_tests and selected_browser to user meta via the WP REST API.
	 */
	const saveUserMeta = async () => {
		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		try {
			await axiosInstance.post(
				window.wpApiSettings.root + 'wp/v2/users/me',
				{
					meta: {
						_presstest_settings: {
							selected_tests:   selectedTests,
							selected_browser: selectedBrowser,
						},
					},
				}
			);
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

	/**
	 * Sends a proxied test run request through the WordPress REST API.
	 * The Presstest API key is resolved server-side and never exposed here.
	 *
	 * @param {object} e Event.
	 */
	const runTests = async ( e ) => {
		e.preventDefault();
		updateMessage( {} );

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
					url:     settings.site_url,
					tests:   selectedTests.join( ',' ),
					browser: selectedBrowser,
				}
			);

			if ( 200 !== response.status ) {
				throw new Error( response.data?.message ?? 'Unexpected error.' );
			}

			setTestingStatus( false );
			updateMessage( { type: 'success', message: settings.tests_run_message } );
		} catch ( error ) {
			setTestingStatus( false );
			const msg = error.response?.data?.message ?? error.message;
			updateMessage( { type: 'error', message: msg + ' See console for more information.' } );
		}
	};

	// Auto-save user preferences whenever they change (skips the initial mount).
	useOnChangeEffect( () => {
		saveUserMeta();
	}, [selectedTests, selectedBrowser] );

	const selectedBrowserOption = BROWSERS.find( b => b.value === selectedBrowser ) ?? null;

	return (
		<div className='content-wrap'>
			{ '' !== message.message && (
				<div className={'message-wrap message-wrap--' + ( 'error' === message.type ? 'error' : 'success' )}>
					<p>{message.message}</p>
					<i className='close dashicons dashicons-dismiss' onClick={() => updateMessage( { type: '', message: '' } )}></i>
				</div>
			)}
			<p className='presstest-site-url'>
				Running tests against: <strong>{settings.site_url}</strong>
			</p>
			<form>
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
					<label htmlFor='browsers' className='fieldset-instruction'>Select browser:</label>
					<Select
						value={selectedBrowserOption}
						onChange={option => setSelectedBrowser( option?.value ?? '' )}
						inputId='browsers'
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
