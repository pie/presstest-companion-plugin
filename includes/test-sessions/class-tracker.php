<?php
/**
 * Records objects created during a test session.
 *
 * Integrations call Tracker::record() from their creation hooks. Outside a
 * session it does nothing, so the hooks are safe on ordinary site traffic.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Static entry point for recording session objects.
 */
class Tracker {

	/**
	 * Records an object against the current session, if there is one.
	 *
	 * @param string     $type      Object type — must match a cleanup handler key.
	 * @param int        $object_id Object ID.
	 * @param array|null $data      Extra data the cleanup handler needs, if any.
	 * @return void
	 */
	public static function record( string $type, int $object_id, ?array $data = null ): void {
		$session = Session_Context::current();

		if ( null === $session || 0 >= $object_id ) {
			return;
		}

		( new Session_Repository() )->record_object( $session->get_id(), $type, $object_id, $data );
	}

	/**
	 * Whether the current session created the given object.
	 *
	 * Lets handlers act only on test data — e.g. clearing membership rows for
	 * a user the session created, never for a real user it merely changed.
	 *
	 * @param string $type      Object type.
	 * @param int    $object_id Object ID.
	 * @return bool False outside a session.
	 */
	public static function is_session_object( string $type, int $object_id ): bool {
		$session = Session_Context::current();

		if ( null === $session ) {
			return false;
		}

		return ( new Session_Repository() )->has_object( $session->get_id(), $type, $object_id );
	}
}
