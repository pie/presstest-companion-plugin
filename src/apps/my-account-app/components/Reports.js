import React, { useState, useEffect } from 'react';
import axios from 'axios';

function Results() {
    const [results, setResults]         = useState( [] );
    const [loading, setLoading]         = useState( true );
    const [currentPage, setCurrentPage] = useState( 1 );
    const [totalPages, setTotalPages]   = useState( 0 );

  useEffect(() => {
     // Set up the Axios instance with interceptors
     const axiosInstance = axios.create();

     // Add the interceptors to modify the request before sending
     axiosInstance.interceptors.request.use(( config ) => {
         // Modify the request config before sending
         config.headers['X-WP-Nonce'] = window.wp.api.nonce; // Set the nonce header

         return config;
     });
    // Function to fetch the paginated results
    const fetchResults = async () => {
      try {
        const response = await axiosInstance.get( window.wp.api.root + 'pie-testing-platform/v1/reports', {
          params: {
            page: currentPage, // Current page number
            per_page: 10, // Number of results per page
          },
        });

        setResults( response.data.results );
        setTotalPages( response.data.totalPages );
        setLoading( false );
      } catch ( error ) {
        console.error( error );
        setLoading( false );
      }
    };

    fetchResults();
  }, [currentPage]);

  const handleNextPage = () => {
    setCurrentPage((prevPage) => prevPage + 1);
  };

  const handlePrevPage = () => {
    setCurrentPage((prevPage) => prevPage - 1);
  };

  return (
    <div>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <div>
          <ul>
            {results.map((result) => (
              <li key={result.id}>
                <h3>{result.title}</h3>
                <p>{result.description}</p>
              </li>
            ))}
          </ul>
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