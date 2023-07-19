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

// @todo implement pagination
function Results() {
    const [results, setResults]         = useState( [] );
    const [loading, setLoading]         = useState( true );
    const [currentPage, setCurrentPage] = useState( 1 );
    const [totalPages, setTotalPages]   = useState( 1 );

    useEffect(() => {
        // Set up the Axios instance with interceptors
        const axiosInstance = axios.create();

        // Add the interceptors to modify the request before sending
        axiosInstance.interceptors.request.use(( config ) => {
          // Modify the request config before sending
          config.headers['X-WP-Nonce'] = window.wpApiSettings.nonce; // Set the nonce header

          return config;
        });
        // Function to fetch the paginated results
        const fetchResults = async () => {
            try {
                const response = await axiosInstance.get( window.wpApiSettings.root + 'pie-testing-platform/v1/reports' );

                console.log( response );
                setResults( response.data );
                setLoading( false );
            } catch ( error ) {
                console.error( error );
                setLoading( false );
            }
        };

      fetchResults();
  }, [currentPage]);

  const handleNextPage = () => {
    setCurrentPage( prevPage => prevPage + 1 );
  };

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
          {$report_json.domain} - {$report_json.date}
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
            {$test.code}
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
            { results.map( ( result ) => (
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