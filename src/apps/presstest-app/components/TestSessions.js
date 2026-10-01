import React, { useEffect, useState } from 'react';
import axios from 'axios';

/**
 * Creates an axios instance that authenticates with the WP REST nonce.
 *
 * @returns {import('axios').AxiosInstance}
 */
const restClient = () => {
	const client = axios.create( { baseURL: window.wpApiSettings.root + 'presstest-companion/v1/' } );
	client.interceptors.request.use( config => {
		config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
		return config;
	} );
	return client;
};

/**
 * Formats a session's cleanup summary, e.g. "2 user, 1 wc_order".
 *
 * @param {object|null} summary Cleanup summary from the API.
 * @returns {string}
 */
const describeSummary = summary => {
	if ( null === summary ) {
		return '—';
	}

	const deleted = Object.entries( summary.deleted ?? {} ).map( ( [ type, count ] ) => `${count} ${type}` );
	const failed  = ( summary.failed ?? [] ).length;
	const kept    = Object.values( summary.kept ?? {} ).reduce( ( total, count ) => total + count, 0 );
	const parts   = 0 < deleted.length ? deleted : [ 'nothing created' ];

	return parts.join( ', ' ) + ( 0 < kept ? ` (${kept} kept, still in use)` : '' ) + ( 0 < failed ? ` (${failed} failed)` : '' );
};

/**
 * Lists recent test sessions and lets an administrator remove all remaining
 * test data immediately.
 *
 * @returns {JSX.Element}
 */
function TestSessions() {
	const [sessions, setSessions] = useState( [] );
	const [loading, setLoading]   = useState( true );
	const [purging, setPurging]   = useState( false );
	const [message, setMessage]   = useState( '' );

	/**
	 * Loads recent sessions from the REST API.
	 */
	const load = async () => {
		try {
			const response = await restClient().get( 'sessions' );
			setSessions( response.data );
		} catch ( error ) {
			console.error( error );
		}
		setLoading( false );
	};

	useEffect( () => {
		load();
	}, [] );

	/**
	 * Ends every unfinished session and removes its data.
	 */
	const purge = async () => {
		setPurging( true );
		setMessage( '' );

		try {
			const response = await restClient().post( 'sessions/purge' );
			setMessage( `Removed test data from ${response.data.purged} session(s).` );
			await load();
		} catch ( error ) {
			setMessage( 'Purge failed. See console for more information.' );
			console.error( error );
		}

		setPurging( false );
	};

	const unfinished = sessions.filter( session => [ 'active', 'cleaning' ].includes( session.status ) );

	return (
		<div className='test-sessions'>
			<h3>Test sessions</h3>
			{ loading ? (
				<p>Loading&hellip;</p>
			) : (
				<>
					<p>
						{ 0 === unfinished.length
							? 'No test data is currently held on this site.'
							: `${unfinished.length} session(s) currently hold test data. It is removed automatically when each run finishes, or within an hour if a run is interrupted.` }
					</p>
					<button
						type='button'
						className='button'
						disabled={purging || 0 === unfinished.length}
						onClick={purge}
					>
						{ purging ? 'Removing…' : 'Remove all test data now' }
					</button>
					{ '' !== message && <p className='field-description'>{message}</p> }
					{ 0 < sessions.length && (
						<table className='widefat striped test-sessions__table'>
							<thead>
								<tr>
									<th>Run</th>
									<th>Started (UTC)</th>
									<th>Status</th>
									<th>Cleaned up</th>
								</tr>
							</thead>
							<tbody>
								{ sessions.map( session => (
									<tr key={session.id}>
										<td>{ '' !== session.label ? session.label : `#${session.id}` }</td>
										<td>{session.created_at}</td>
										<td>{session.status}</td>
										<td>{ [ 'active', 'cleaning' ].includes( session.status ) ? `${session.object_count} object(s) held` : describeSummary( session.summary ) }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</>
			) }
		</div>
	);
}

export default TestSessions;
