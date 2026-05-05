import React, { useEffect, useState } from 'react';
import axios from 'axios';

const settings = window.presstest_companion;

function Settings() {
	const [apiKey, setApiKey]   = useState( '' );
	const [loading, setLoading] = useState( true );
	const [saving, setSaving]   = useState( false );
	const [message, setMessage] = useState( { type: '', text: '' } );

	useEffect( () => {
		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		axiosInstance
			.get( window.wpApiSettings.root + 'wp/v2/settings' )
			.then( res => {
				setApiKey( res.data.presstest_companion_api_key ?? '' );
				setLoading( false );
			} )
			.catch( err => {
				console.error( err );
				setLoading( false );
			} );
	}, [] );

	const saveSettings = async ( e ) => {
		e.preventDefault();
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
				{ presstest_companion_api_key: apiKey }
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
