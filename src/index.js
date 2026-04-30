import React from 'react';
import ReactDOM from 'react-dom/client';
import PresstestApp from './apps/presstest-app/PresstestApp';

const container = document.getElementById( 'presstest-admin-app' );

if ( container ) {
	const root = ReactDOM.createRoot( container );
	root.render(
		<React.StrictMode>
			<PresstestApp />
		</React.StrictMode>
	);
}
