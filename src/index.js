import React from 'react';
import ReactDOM from 'react-dom/client';
import MyAccountApp from './apps/my-account-app/MyAccountApp';

const container = document.getElementById( 'presstest-admin-app' );

if ( container ) {
	const root = ReactDOM.createRoot( container );
	root.render(
		<React.StrictMode>
			<MyAccountApp />
		</React.StrictMode>
	);
}
