import React from 'react';

const settings = window.presstest_companion;

/**
 * Settings fields controlling whether Presstest may create test data, and
 * which roles its test users may have.
 *
 * Controlled by the parent Settings form, which saves these values with the
 * rest of the settings.
 *
 * @param {object}   props
 * @param {boolean}  props.enabled       Whether test data is enabled.
 * @param {Function} props.onEnabled     Called with the new enabled value.
 * @param {string[]} props.roles         Allowed role slugs.
 * @param {Function} props.onRoles       Called with the new list of role slugs.
 * @returns {JSX.Element}
 */
function TestDataFields( { enabled, onEnabled, roles, onRoles } ) {
	/**
	 * Adds or removes a role from the allowed list.
	 *
	 * @param {string}  role    Role slug.
	 * @param {boolean} checked Whether the role is now allowed.
	 */
	const toggleRole = ( role, checked ) => {
		onRoles( checked ? [ ...roles, role ] : roles.filter( r => r !== role ) );
	};

	return (
		<>
			<h3>Test data</h3>
			<p className='field-description'>
				Lets logged-in tests create users, posts, orders and memberships on this site. Everything a test run creates is removed when the run finishes, and emails are never sent &mdash; they are captured for the tests to check instead.
			</p>
			<fieldset>
				<label className='fieldset-instruction checkbox-label'>
					<input
						type='checkbox'
						checked={enabled}
						onChange={e => onEnabled( e.target.checked )}
					/>
					Allow Presstest to create test data
				</label>
			</fieldset>
			<fieldset>
				<legend className='fieldset-instruction'>Roles test users may have:</legend>
				{ settings.roles.map( role => (
					<label key={role.value} className='checkbox-label checkbox-label--stacked'>
						<input
							type='checkbox'
							checked={roles.includes( role.value )}
							disabled={false === enabled}
							onChange={e => toggleRole( role.value, e.target.checked )}
						/>
						{role.label}
					</label>
				) ) }
				{ roles.includes( 'administrator' ) && (
					<p className='field-description field-description--error'>
						Tests can create administrator accounts. Only allow this on staging sites, for testing admin-only functionality.
					</p>
				) }
			</fieldset>
		</>
	);
}

export default TestDataFields;
