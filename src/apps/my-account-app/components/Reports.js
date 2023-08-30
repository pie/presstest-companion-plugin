import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  Accordion,
  AccordionItem,
  AccordionItemHeading,
  AccordionItemButton,
  AccordionItemPanel,
} from 'react-accessible-accordion';

// Demo styles, see 'Styles' section below for some notes on use.
import '../../../../node_modules/react-accessible-accordion/dist/fancy-example.css';

function Results() {
  const [allResults, setAllResults]         = useState( [] );
  const [currentResults, setCurrentResults] = useState( [] );
  const [loading, setLoading]               = useState( true );
  const [currentPage, setCurrentPage]       = useState( 1 );
  const [totalPages, setTotalPages]         = useState( 1 );

  useEffect(() => {
    setLoading( true );
    // Set up the Axios instance with interceptors
    const axiosInstance = axios.create();

    // Add the interceptors to modify the request before sending
    axiosInstance.interceptors.request.use(( config ) => {
      // Modify the request config before sending
      config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce; // Set the nonce header

      return config;
    });
    // Get all reports from the API
    const fetchResults = async () => {
        try {
            const response = await axiosInstance.get( window.wpApiSettings.root + 'pie-testing-platform/v1/reports' );

            console.log( response );
            setAllResults( response.data );
            setTotalPages( response.data.length / 10 );
            setCurrentResults( response.data.slice( 0, 10 ) );
            setLoading( false );
        } catch ( error ) {
            console.error( error );
            setLoading( false );
        }
    };

    fetchResults();  
  }, [] );

  // When the current page changes, update the current results
  useEffect(() => {
    setCurrentResults( allResults.slice( ( currentPage - 1 ) * 10, currentPage * 10 ) );
  }, [currentPage]);

  // Next page clicked, update the current page
  const handleNextPage = () => {
    setCurrentPage( prevPage => prevPage + 1 );
  };

  // Previous page clicked, update the current page
  const handlePrevPage = () => {
    setCurrentPage( prevPage => prevPage - 1 );
  };

  /**
   * Build the report list from the given report JSON
   * 
   * @param {string} $report_json 
   * @returns 
   */
  function get_report_list_item( $report_json ) {
    const $report = JSON.parse( $report_json.report );
    return <AccordionItem className={get_report_status( $report )}>
      <AccordionItemHeading>
        <AccordionItemButton>
          {$report_json.domain} ({$report_json.browser}) - {$report_json.date}
        </AccordionItemButton>
      </AccordionItemHeading>
      <AccordionItemPanel>
        <ul>
          <li>Total tests: {$report.stats.tests}</li>
          {$report.stats.passes > 0 && (
            <li >Passed: {$report.stats.passes}</li>
          )}
          {$report.stats.failures > 0 && (
            <li >Failed: {$report.stats.failures}</li>
          )}
          {$report.stats.pending > 0 && (
            <li >Pending: {$report.stats.pending}</li>
          )}
          { $report.results.map( ( $result ) => (
              get_result_list_item( $result )
          ))}
        </ul>
      </AccordionItemPanel>
    </AccordionItem>
  };

  /**
   * Get status for current report
   * Returns failed if ANY tests failed
   * 
   * @param {object} $report 
   * @returns 
   */
  function get_report_status( $report ) {
    if ( $report.stats.failures > 0 ) {
      return 'failed';
    } else if ( $report.stats.pending > 0 ) {
      return 'pending';
    } else {
      return 'passed';
    }
  }

  /**
   * Build suite results from the report info given
   * 
   * @param {object} $result 
   * @returns 
   */
  function get_result_list_item( $result ) {
    if ( ! $result.suites ) {
      return;
    }
    return <Accordion allowZeroExpanded allowMultipleExpanded>
      { $result.suites.map( ( $suite ) => (
        <AccordionItem className={get_suite_status( $suite )}>
          <AccordionItemHeading>
            <AccordionItemButton>
              {$suite.title} ({$suite.tests.length})
            </AccordionItemButton>
          </AccordionItemHeading>
          <AccordionItemPanel>
            { $suite.tests.map( ( $test ) => (
                get_test_result_item( $test )
            ))}
          </AccordionItemPanel>
        </AccordionItem>
      ))}
    </Accordion>
  };

  /**
   * Get status for current suite
   * Returns failed if ANY tests failed
   * 
   * @param {object} $suite 
   * @returns 
   */
  function get_suite_status( $suite ) {
    if ( $suite.failures.length > 0 ) {
      return 'failed';
    } else if ( $suite.pending.length > 0 ) {
      return 'pending';
    } else {
      return 'passed';
    }
  }

  function parse_error_message( $msg ) {
    $msg = $msg.replace( 'Error:', '' );
    if ( $msg.includes( '==========' ) ) {
      return $msg.substring( 0, $msg.indexOf( '==========' ) );
    } else {
      return $msg;
    }
  }

  function parse_error_stack( $stack ) {
    $stack = $stack.replace( 'Error:', '' );
    return $stack;
  }

  function parse_error_diff( $diff, $type = '' ) {
    if ( 'actual' === $type ) {
      return $diff.substring( 0, $diff.indexOf( '+' ) );
    }
    if ( 'expected' === $type ) {
      return '+' + $diff.substring( $diff.indexOf( '+' ) + 1 );
    }

    return $diff;
  }

  /**
   * Build result for given test
   * 
   * @param {object} $test 
   * @returns 
   */
  function get_test_result_item( $test ) {
    return <Accordion allowZeroExpanded allowMultipleExpanded>
        <AccordionItem className={$test.state}>
          <AccordionItemHeading>
            <AccordionItemButton>
              {$test.title} - {$test.state}
            </AccordionItemButton>
          </AccordionItemHeading>
          <AccordionItemPanel>
            {Boolean( $test.err.message ) ? (
              <p><b>Message: </b>{parse_error_message( $test.err.message )}</p>
            ) : ( null )}
            {Boolean( $test.err.estack ) ? (
              <p>
                <b>Error Stack: </b>
                <pre>
                  {parse_error_stack( $test.err.estack )}
                </pre>
              </p>
            ) : ( null )}
            {Boolean( $test.err.diff ) ? (
              <p>
                <b>Diff: </b>
                <pre class="expected">
                  Expected:<br />
                  {parse_error_diff( $test.err.diff, 'expected' )}
                </pre>
                <pre class="actual">
                  Actual:<br />
                  {parse_error_diff( $test.err.diff, 'actual' )}
                </pre>
              </p>
            ) : ( null )}
            {Boolean( $test.code ) ? (
              <p>
                <b>Code:</b>
                <pre>
                  {$test.code }
                </pre>
              </p>
            ) : ( null )}
          </AccordionItemPanel>
        </AccordionItem>
    </Accordion>
  }

  return (
    <div>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <div>
          <Accordion allowZeroExpanded allowMultipleExpanded>
            { currentResults.map( ( result ) => (
              get_report_list_item( result )
            ))}
          </Accordion>
          <div>
            {currentPage > 1 && (
              <button onClick={handlePrevPage}>Previous</button>
            )}
            {currentPage < totalPages && (
              <button onClick={handleNextPage}>Next</button>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default Results;