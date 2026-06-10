import React, { useEffect, useState } from 'react';
import axios from 'axios';

const AVAILABLE_TESTS = [
	{ name: 'WordPress Core',   value: 'wordpress-core' },
	{ name: 'WooCommerce Core', value: 'woocommerce-core' },
];

const BROWSERS = [
	{ value: 'chromium', label: 'Chrome' },
	{ value: 'firefox',  label: 'Firefox' },
	{ value: 'webkit',   label: 'Safari' },
];

const settings = window.presstest_companion;

/**
 * Settings panel for managing the API key and scheduled test configuration.
 *
 * Reads and writes values via the WordPress /wp/v2/settings REST endpoint.
 * Cron schedule options are fetched from the plugin's own REST endpoint on mount.
 *
 * @returns {JSX.Element}
 */
function Settings() {
	const [apiKey, setApiKey]   = useState( '' );
	const [loading, setLoading] = useState( true );
	const [saving, setSaving]   = useState( false );
	const [message, setMessage] = useState( { type: '', text: '' } );

	const [cronEnabled, setCronEnabled]         = useState( false );
	const [cronSchedule, setCronSchedule]       = useState( 'daily' );
	const [cronTests, setCronTests]             = useState( [] );
	const [cronBrowser, setCronBrowser]         = useState( 'chromium' );
	const [scheduleOptions, setScheduleOptions] = useState( {} );

	useEffect( () => {
		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		axiosInstance
			.get( window.wpApiSettings.root + 'wp/v2/settings' )
			.then( async ( settingsRes ) => {
				const data = settingsRes.data;

				setApiKey( data.presstest_companion_api_key ?? '' );
				setCronEnabled( data.presstest_companion_cron_enabled ?? false );
				setCronSchedule( data.presstest_companion_cron_schedule ?? 'daily' );
				setCronBrowser( data.presstest_companion_cron_browser ?? 'chromium' );

				const rawTests = data.presstest_companion_cron_tests ?? '';
				setCronTests( rawTests.split( ',' ).map( t => t.trim() ).filter( Boolean ) );

				const [ schedulesResult ] = await Promise.allSettled( [
					axiosInstance.get( window.wpApiSettings.root + 'presstest-companion/v1/schedules' ),
				] );

				if ( 'fulfilled' === schedulesResult.status ) {
					setScheduleOptions( schedulesResult.value.data );
				} else {
					console.error( 'Failed to load schedule options:', schedulesResult.reason );
				}

				setLoading( false );
			} )
			.catch( err => {
				console.error( err );
				setLoading( false );
			} );
	}, [] );

	/**
	 * Toggles a test suite slug in the cron test selection.
	 *
	 * @param {string} value Test suite slug to toggle.
	 */
	const toggleCronTest = ( value ) => {
		setCronTests( prev =>
			prev.includes( value )
				? prev.filter( t => t !== value )
				: [ ...prev, value ]
		);
	};

	/**
	 * Validates the form and persists all settings via the WordPress REST API.
	 *
	 * Blocks saving if scheduled tests are enabled but no test suites are selected.
	 *
	 * @param {React.FormEvent} e Form submit event.
	 */
	const saveSettings = async ( e ) => {
		e.preventDefault();
		if ( cronEnabled && 0 === cronTests.length ) {
			setMessage( { type: 'error', text: 'Please select at least one test suite before enabling scheduled tests.' } );
			return;
		}

		setSaving( true );
		setMessage( { type: '', text: '' } );

		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		try {
			await axiosInstance.post(
				window.wpApiSettings.root + 'wp/v2/settings',
				{
					presstest_companion_api_key:      apiKey,
					presstest_companion_cron_enabled:  cronEnabled,
					presstest_companion_cron_schedule: cronSchedule,
					presstest_companion_cron_url:      settings.site_url,
					presstest_companion_cron_tests:    cronTests.join( ',' ),
					presstest_companion_cron_browser:  cronBrowser,
				}
			);
			setMessage( { type: 'success', text: 'Settings saved.' } );
		} catch ( err ) {
			setMessage( { type: 'error', text: 'Failed to save settings. Please try again.' } );
			console.error( err );
		}

		setSaving( false );
	};

	if ( loading ) {
		return <p>Loading&hellip;</p>;
	}

	return (
		<div className='content-wrap'>
			{ '' !== message.text && (
				<div className={'message-wrap message-wrap--' + message.type}>
					<p>{message.text}</p>
					<i className='close dashicons dashicons-dismiss' onClick={() => setMessage( { type: '', text: '' } )}></i>
				</div>
			)}
			<form onSubmit={saveSettings}>
				<fieldset>
					<label htmlFor='api-key' className='fieldset-instruction'>API Key:</label>
					<input
						type='password'
						id='api-key'
						className='input-text'
						value={apiKey}
						onChange={e => setApiKey( e.target.value )}
						autoComplete='new-password'
					/>
					<p className='field-description'>The PRESSTEST_API_KEY value from your Presstest server .env file.</p>
				</fieldset>

				<hr />

				<h3>Scheduled Tests</h3>
				<fieldset>
					<label className='fieldset-instruction checkbox-label'>
						<input
							type='checkbox'
							checked={cronEnabled}
							onChange={e => setCronEnabled( e.target.checked )}
						/>
						Enable scheduled tests
					</label>
				</fieldset>
				<fieldset>
					<label htmlFor='cron-schedule' className='fieldset-instruction'>Schedule:</label>
					<select
						id='cron-schedule'
						className='input-select'
						value={cronSchedule}
						onChange={e => setCronSchedule( e.target.value )}
					>
						{ Object.entries( scheduleOptions ).map( ( [ key, opt ] ) => (
							<option key={key} value={key}>{opt.label}</option>
						) ) }
					</select>
				</fieldset>
				<fieldset>
					<label className='fieldset-instruction'>Tests:</label>
					<div className='checkbox-group'>
						{ AVAILABLE_TESTS.map( test => (
							<label key={test.value} className='checkbox-label'>
								<input
									type='checkbox'
									value={test.value}
									checked={cronTests.includes( test.value )}
									onChange={() => toggleCronTest( test.value )}
								/>
								{test.name}
							</label>
						) ) }
					</div>
				</fieldset>
				<fieldset>
					<label htmlFor='cron-browser' className='fieldset-instruction'>Browser:</label>
					<select
						id='cron-browser'
						className='input-select'
						value={cronBrowser}
						onChange={e => setCronBrowser( e.target.value )}
					>
						{ BROWSERS.map( b => (
							<option key={b.value} value={b.value}>{b.label}</option>
						) ) }
					</select>
				</fieldset>

				<input
					type='submit'
					value={saving ? 'Saving…' : 'Save Settings'}
					className='button primary'
					disabled={saving}
				/>
			</form>

			<hr />

			<h3>Server &amp; Report Endpoint</h3>
			<p>These values are managed automatically &mdash; no action is required.</p>
			<fieldset>
				<label className='fieldset-instruction'>Server URL:</label>
				<input
					type='text'
					className='input-text'
					value={settings.server_url}
					readOnly
				/>
			</fieldset>
			<fieldset>
				<label className='fieldset-instruction'>Report URL:</label>
				<input
					type='text'
					className='input-text'
					value={settings.report_url}
					readOnly
				/>
				<p className='field-description'>This site&rsquo;s REST endpoint &mdash; the Presstest server posts results here automatically.</p>
			</fieldset>
			<fieldset>
				<label className='fieldset-instruction'>Report Token:</label>
				{ '' !== settings.report_token
					? (
						<>
							<input
								type='password'
								className='input-text'
								value={settings.report_token}
								readOnly
							/>
							<p className='field-description'>Sent with each test job and validated by this endpoint. Generated automatically on plugin activation.</p>
						</>
					)
					: <p className='field-description field-description--error'>No token found. Deactivate and reactivate the plugin to generate one.</p>
				}
			</fieldset>
		</div>
	);
}

export default Settings;
