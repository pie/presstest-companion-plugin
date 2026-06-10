import React, { useState, useEffect } from 'react';

/**
 * Strips ANSI terminal colour codes from a string.
 *
 * @param {string} str Raw string that may contain ANSI escape sequences.
 * @returns {string}
 */
const stripAnsi = str => str.replace( /\x1B\[[0-9;]*m/g, '' );
import axios from 'axios';
import {
	Accordion,
	AccordionItem,
	AccordionItemHeading,
	AccordionItemButton,
	AccordionItemPanel,
} from 'react-accessible-accordion';
import '../../../../node_modules/react-accessible-accordion/dist/fancy-example.css';

/**
 * Recursively collect all specs from a suite (handles nested describe blocks).
 *
 * @param {object} suite Playwright suite node.
 * @returns {Array}
 */
function collectSpecs( suite ) {
	const specs = suite.specs ? [ ...suite.specs ] : [];
	if ( suite.suites ) {
		suite.suites.forEach( child => specs.push( ...collectSpecs( child ) ) );
	}
	return specs;
}

/**
 * Derive the CSS status class for a single spec.
 *
 * @param {object} spec Playwright spec node.
 * @returns {string} 'passed' | 'failed' | 'pending'
 */
function specStatus( spec ) {
	if ( ! spec.ok ) {
		return 'failed';
	}
	const hasSkipped = ( spec.tests ?? [] ).some( t => 'skipped' === t.status );
	return hasSkipped ? 'pending' : 'passed';
}

/**
 * Derive the CSS status class for an entire report.
 *
 * @param {object} report Parsed Playwright JSON report.
 * @returns {string} 'passed' | 'failed' | 'pending'
 */
function reportStatus( report ) {
	if ( ( report.stats?.unexpected ?? 0 ) > 0 ) {
		return 'failed';
	}
	if ( ( report.stats?.skipped ?? 0 ) > 0 ) {
		return 'pending';
	}
	return 'passed';
}

/**
 * Displays all test reports with accordion drill-down and pagination.
 *
 * Fetches from the REST API on mount, paginates locally at 10 per page,
 * and allows individual reports to be deleted.
 *
 * @returns {JSX.Element}
 */
function Results() {
	const [allResults, setAllResults]         = useState( [] );
	const [currentResults, setCurrentResults] = useState( [] );
	const [loading, setLoading]               = useState( true );
	const [currentPage, setCurrentPage]       = useState( 1 );
	const [totalPages, setTotalPages]         = useState( 1 );
	const [deleting, setDeleting]             = useState( null );

	useEffect( () => {
		setLoading( true );

		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		/**
		 * Fetches all test reports from the REST API and initialises pagination state.
		 */
		const fetchResults = async () => {
			try {
				const response = await axiosInstance.get( window.wpApiSettings.root + 'presstest-companion/v1/reports' );
				setAllResults( response.data );
				setTotalPages( Math.ceil( response.data.length / 10 ) );
				setCurrentResults( response.data.slice( 0, 10 ) );
				setLoading( false );
			} catch ( error ) {
				console.error( error );
				setLoading( false );
			}
		};

		fetchResults();
	}, [] );

	// Update the visible slice when the user pages.
	useEffect( () => {
		setCurrentResults( allResults.slice( ( currentPage - 1 ) * 10, currentPage * 10 ) );
	}, [currentPage, allResults] );

	/**
	 * Delete a report by ID and remove it from local state.
	 *
	 * @param {number} id Report ID.
	 */
	async function handleDelete( id ) {
		setDeleting( id );

		const axiosInstance = axios.create();
		axiosInstance.interceptors.request.use( config => {
			config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
			return config;
		} );

		try {
			await axiosInstance.delete( window.wpApiSettings.root + 'presstest-companion/v1/reports/' + id );
			const updated = allResults.filter( r => r.id !== id );
			setAllResults( updated );
			setTotalPages( Math.ceil( updated.length / 10 ) );
			setCurrentPage( p => Math.min( p, Math.ceil( updated.length / 10 ) || 1 ) );
		} catch ( error ) {
			console.error( error );
		}

		setDeleting( null );
	}

	/**
	 * Render the accordion panel for a single spec (individual test).
	 *
	 * @param {object} spec  Playwright spec node.
	 * @param {number} index List key.
	 * @returns {JSX.Element}
	 */
	function renderSpec( spec, index ) {
		const status  = specStatus( spec );
		const result  = spec.tests?.[0]?.results?.[0] ?? null;
		const error   = result?.error ?? null;

		return (
			<Accordion key={index} allowZeroExpanded allowMultipleExpanded>
				<AccordionItem className={status}>
					<AccordionItemHeading>
						<AccordionItemButton>
							{spec.title} &mdash; {status}
						</AccordionItemButton>
					</AccordionItemHeading>
					<AccordionItemPanel>
						{error?.message && (
							<p><b>Message: </b>{stripAnsi( error.message )}</p>
						)}
						{error?.stack && (
							<p><b>Stack: </b><pre>{stripAnsi( error.stack )}</pre></p>
						)}
						{result?.duration !== undefined && (
							<p><b>Duration: </b>{result.duration}ms</p>
						)}
					</AccordionItemPanel>
				</AccordionItem>
			</Accordion>
		);
	}

	/**
	 * Render a file-level suite with all its specs.
	 *
	 * @param {object} suite Playwright suite node.
	 * @param {number} index List key.
	 * @returns {JSX.Element|null}
	 */
	function renderSuite( suite, index ) {
		const specs = collectSpecs( suite );

		if ( 0 === specs.length ) {
			return null;
		}

		const status = specs.some( s => ! s.ok ) ? 'failed' : 'passed';

		return (
			<Accordion key={index} allowZeroExpanded allowMultipleExpanded>
				<AccordionItem className={status}>
					<AccordionItemHeading>
						<AccordionItemButton>
							{suite.title} ({specs.length})
						</AccordionItemButton>
					</AccordionItemHeading>
					<AccordionItemPanel>
						{specs.map( ( spec, i ) => renderSpec( spec, i ) )}
					</AccordionItemPanel>
				</AccordionItem>
			</Accordion>
		);
	}

	/**
	 * Render the top-level accordion item for a database report row.
	 *
	 * @param {object} row Database row with domain, browser, date, report fields.
	 * @returns {JSX.Element}
	 */
	function renderReport( row ) {
		let report;
		try {
			report = JSON.parse( row.report );
		} catch ( e ) {
			return (
				<AccordionItem key={row.id} className='failed'>
					<AccordionItemHeading>
						<AccordionItemButton>
							{row.domain} ({row.browser}) &mdash; {row.date} &mdash; corrupt report
						</AccordionItemButton>
					</AccordionItemHeading>
					<AccordionItemPanel>
						<p>This report could not be parsed and should be deleted.</p>
						<button
							className='button delete-report'
							disabled={deleting === row.id}
							onClick={() => handleDelete( row.id )}
						>
							{deleting === row.id ? 'Deleting…' : 'Delete report'}
						</button>
					</AccordionItemPanel>
				</AccordionItem>
			);
		}

		const stats  = report.stats ?? {};
		const total  = ( stats.expected ?? 0 ) + ( stats.unexpected ?? 0 ) + ( stats.skipped ?? 0 ) + ( stats.flaky ?? 0 );
		const status = reportStatus( report );

		return (
			<AccordionItem key={row.id} className={status}>
				<AccordionItemHeading>
					<AccordionItemButton>
						{row.domain} ({row.browser}) &mdash; {row.date}
					</AccordionItemButton>
				</AccordionItemHeading>
				<AccordionItemPanel>
					<button
						className='button delete-report'
						disabled={deleting === row.id}
						onClick={() => handleDelete( row.id )}
					>
						{deleting === row.id ? 'Deleting…' : 'Delete report'}
					</button>
					<ul>
						<li>Total: {total}</li>
						{stats.expected > 0 && <li>Passed: {stats.expected}</li>}
						{stats.unexpected > 0 && <li>Failed: {stats.unexpected}</li>}
						{stats.skipped > 0 && <li>Skipped: {stats.skipped}</li>}
						{stats.flaky > 0 && <li>Flaky: {stats.flaky}</li>}
					</ul>
					{( report.suites ?? [] ).map( ( suite, i ) => renderSuite( suite, i ) )}
				</AccordionItemPanel>
			</AccordionItem>
		);
	}

	return (
		<div>
			{loading ? (
				<p>Loading...</p>
			) : (
				<div>
					<Accordion allowZeroExpanded allowMultipleExpanded>
						{currentResults.map( row => renderReport( row ) )}
					</Accordion>
					<div>
						{currentPage > 1 && (
							<button onClick={() => setCurrentPage( p => p - 1 )}>Previous</button>
						)}
						{currentPage < totalPages && (
							<button onClick={() => setCurrentPage( p => p + 1 )}>Next</button>
						)}
					</div>
				</div>
			)}
		</div>
	);
}

export default Results;
