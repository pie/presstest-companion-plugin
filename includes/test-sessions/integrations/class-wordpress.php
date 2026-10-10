<?php
/**
 * WordPress core integration: users, posts (including attachments and any
 * custom post type), comments, and terms.
 *
 * Only objects created during a session are recorded and removed. Changes a
 * test makes to existing content are not reverted, so tests should work on
 * data they created themselves.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

use PIE\PresstestCompanion\TestSessions\Bootstrap;
use PIE\PresstestCompanion\TestSessions\Cleaner;
use PIE\PresstestCompanion\TestSessions\Email_Capture;
use PIE\PresstestCompanion\TestSessions\Session;
use PIE\PresstestCompanion\TestSessions\Session_Repository;
use PIE\PresstestCompanion\TestSessions\Test_Data_Settings;
use PIE\PresstestCompanion\TestSessions\Tracker;

/**
 * Tracking, cleanup, and fixtures for core WordPress objects.
 */
class WordPress extends Abstract_Integration {

	/**
	 * Integration slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- a slug, not the brand name.
	}

	/**
	 * Integration display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'WordPress';
	}

	/**
	 * Core is always active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return true;
	}

	/**
	 * Records core objects as they are created.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'user_register', array( $this, 'track_user' ) );
		add_action( 'wp_insert_post', array( $this, 'track_post' ), 10, 3 );
		// New attachments return from wp_insert_post() before its action fires.
		add_action( 'add_attachment', array( $this, 'track_attachment' ) );
		add_action( 'wp_insert_comment', array( $this, 'track_comment' ) );
		add_action( 'created_term', array( $this, 'track_term' ), 10, 3 );
	}

	/**
	 * Records a new user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function track_user( int $user_id ): void {
		Tracker::record( 'user', $user_id );
	}

	/**
	 * Records a new post of any type except attachments, which never reach
	 * this action (see track_attachment()). Updates and revisions are skipped:
	 * revisions are removed with their parent post.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update to an existing post.
	 * @return void
	 */
	public function track_post( int $post_id, \WP_Post $post, bool $update ): void {
		if ( true === $update || 'revision' === $post->post_type ) {
			return;
		}

		Tracker::record( 'post', $post_id );
	}

	/**
	 * Records a new attachment (uploads through the media library, REST API,
	 * or media_handle_sideload()). Cleanup deletes its files from disk too.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function track_attachment( int $post_id ): void {
		Tracker::record( 'post', $post_id );
	}

	/**
	 * Records a new comment, including WooCommerce order notes.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function track_comment( int $comment_id ): void {
		Tracker::record( 'comment', $comment_id );
	}

	/**
	 * Records a new term with its taxonomy, which deletion needs.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function track_term( int $term_id, int $tt_id, string $taxonomy ): void {
		Tracker::record( 'term', $term_id, array( 'taxonomy' => $taxonomy ) );
	}

	/**
	 * Cleanup order: comments, then posts, then users, then terms — terms
	 * last, so they can be kept if anything outside the session still uses
	 * them once the session's own objects are gone.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array {
		return array(
			'comment' => array(
				'priority' => 20,
				'callback' => array( $this, 'delete_comment' ),
			),
			'post'    => array(
				'priority' => 30,
				'callback' => array( $this, 'delete_post' ),
			),
			'user'    => array(
				'priority' => 90,
				'callback' => array( $this, 'delete_user' ),
			),
			'term'    => array(
				'priority' => 92,
				'callback' => array( $this, 'delete_term' ),
			),
		);
	}

	/**
	 * Permanently deletes a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool True once the comment is gone.
	 */
	public function delete_comment( int $comment_id ): bool {
		if ( null === get_comment( $comment_id ) ) {
			return true;
		}

		return true === wp_delete_comment( $comment_id, true );
	}

	/**
	 * Permanently deletes a post, including attachment files from disk.
	 *
	 * Posts another integration owns (e.g. WooCommerce orders stored as
	 * posts) are left for that integration while the session still holds its
	 * record: deleting them here would skip its cleanup (restoring stock and
	 * coupon usage) and lose the data a retry needs.
	 *
	 * @param int        $post_id Post ID.
	 * @param array|null $data    Recorded data (unused).
	 * @param Session    $session Session being cleaned.
	 * @return bool|string True once the post is gone, Cleaner::DEFERRED while its owner's cleanup is pending.
	 */
	public function delete_post( int $post_id, ?array $data, Session $session ) {
		$post = get_post( $post_id );

		if ( null === $post ) {
			return true;
		}

		$owner = Bootstrap::registry()->post_type_owner( $post->post_type );

		if ( null !== $owner && true === ( new Session_Repository() )->has_object( $session->get_id(), $owner, $post_id ) ) {
			return Cleaner::DEFERRED;
		}

		$result = 'attachment' === $post->post_type ? wp_delete_attachment( $post_id, true ) : wp_delete_post( $post_id, true );

		return $result instanceof \WP_Post;
	}

	/**
	 * Deletes a term once nothing uses it.
	 *
	 * Terms are cleaned after posts and users, so whatever still uses one is
	 * either real content or a session object whose own cleanup failed or was
	 * deferred:
	 *   - real content: the term is kept for good. Plugins create shared terms
	 *     on first use (e.g. a status marker on the first user to register),
	 *     and deleting one a real visitor also has would strip it from them;
	 *   - only this session's outstanding objects: the term is deferred, so a
	 *     retry deletes it once those objects are gone.
	 *
	 * @param int        $term_id Term ID.
	 * @param array|null $data    Recorded data: array( 'taxonomy' => string ).
	 * @param Session    $session Session being cleaned.
	 * @return bool|string True once the term is gone, Cleaner::KEPT if real
	 *                     content uses it, Cleaner::DEFERRED if only test
	 *                     objects awaiting cleanup do.
	 */
	public function delete_term( int $term_id, ?array $data, Session $session ) {
		global $wpdb;

		$taxonomy = (string) ( $data['taxonomy'] ?? '' );
		$term     = '' !== $taxonomy ? get_term( $term_id, $taxonomy ) : null;

		// Already gone (or its taxonomy is no longer registered): nothing to do.
		if ( ! $term instanceof \WP_Term ) {
			return true;
		}

		// Everything still attached to the term.
		$object_ids = array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $term->term_taxonomy_id ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);

		// Nothing uses it any more, so it's safe to delete.
		if ( array() === $object_ids ) {
			return true === wp_delete_term( $term_id, $taxonomy );
		}

		// Relationship object IDs are user IDs for user taxonomies and post
		// IDs for everything else; check them against the matching records.
		$taxonomy_object = get_taxonomy( $taxonomy );
		$object_types    = false !== $taxonomy_object ? (array) $taxonomy_object->object_type : array();
		$record_types    = array();

		// Attached to users: check against the session's user records.
		if ( in_array( 'user', $object_types, true ) ) {
			$record_types[] = 'user';
		}

		// Attached to any post type (or unknown): check against its post records.
		if ( array() === $object_types || array() !== array_diff( $object_types, array( 'user' ) ) ) {
			$record_types[] = 'post';
		}

		// Real data uses the term if any attached object isn't one of this
		// session's outstanding records.
		$repository        = new Session_Repository();
		$used_by_real_data = array() !== array_filter(
			$object_ids,
			function ( int $object_id ) use ( $repository, $session, $record_types ): bool {
				foreach ( $record_types as $record_type ) {
					if ( true === $repository->has_object( $session->get_id(), $record_type, $object_id ) ) {
						return false;
					}
				}
				return true;
			}
		);

		// Keep it for good if real data uses it; otherwise wait for the session's
		// own objects to be cleaned, then delete it on a later attempt.
		return true === $used_by_real_data ? Cleaner::KEPT : Cleaner::DEFERRED;
	}

	/**
	 * Deletes a user and any content they still own.
	 *
	 * @param int $user_id User ID.
	 * @return bool True once the user is gone.
	 */
	public function delete_user( int $user_id ): bool {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		if ( false !== get_userdata( $user_id ) ) {
			if ( is_multisite() ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
			}

			$deleted = is_multisite() ? wpmu_delete_user( $user_id ) : wp_delete_user( $user_id );

			if ( true !== $deleted ) {
				return false;
			}
		}

		// Core removes a deleted post's term relationships but not a user's;
		// plugins attach terms to users (e.g. PMPro's abandoned signup marker).
		// Done only once the user is gone, so a failed deletion leaves the
		// relationships in place and their terms are deferred, not orphaned.
		wp_delete_object_term_relationships( $user_id, get_object_taxonomies( 'user' ) );

		return true;
	}

	/**
	 * Fixture factories for core objects.
	 *
	 * @return array<string, callable>
	 */
	public function get_factories(): array {
		return array(
			'user'    => array( $this, 'create_user' ),
			'post'    => array( $this, 'create_post' ),
			'term'    => array( $this, 'create_term' ),
			'comment' => array( $this, 'create_comment' ),
		);
	}

	/**
	 * Creates a test user with a unique login and an undeliverable address.
	 *
	 * Args: role (default "subscriber" — must be allowed in the plugin's test
	 * data settings), first_name, last_name, meta (key => value).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the user belongs to.
	 * @return array|\WP_Error Login details: id, username, email, password, role.
	 */
	public function create_user( array $args, Session $session ) {
		$role = sanitize_key( (string) ( $args['role'] ?? 'subscriber' ) );

		if ( ! in_array( $role, Test_Data_Settings::allowed_roles(), true ) ) {
			return new \WP_Error(
				'presstest_role_not_allowed',
				sprintf(
					/* translators: %s: role slug. */
					__( 'Test users with the "%s" role are not allowed. Enable the role in Presstest > Settings > Test data.', 'presstest-companion' ),
					$role
				),
				array( 'status' => 403 )
			);
		}

		if ( null === get_role( $role ) ) {
			return new \WP_Error(
				'presstest_unknown_role',
				/* translators: %s: role slug. */
				sprintf( __( 'The "%s" role does not exist on this site.', 'presstest-companion' ), $role ),
				array( 'status' => 400 )
			);
		}

		// Roles and capabilities are stored as user meta, so writing them
		// here would bypass the allowed-roles check above. Refuse before the
		// user is created, so a rejected request leaves nothing behind.
		$meta = array();

		foreach ( (array) ( $args['meta'] ?? array() ) as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( true === self::is_privilege_meta_key( $key ) ) {
				return new \WP_Error(
					'presstest_meta_not_allowed',
					/* translators: %s: user meta key. */
					sprintf( __( 'User meta "%s" controls roles and capabilities and cannot be set on test users. Use the role argument instead.', 'presstest-companion' ), $key ),
					array( 'status' => 403 )
				);
			}

			$meta[ $key ] = $value;
		}

		$username = sprintf( 'presstest_%d_%s', $session->get_id(), strtolower( wp_generate_password( 6, false ) ) );
		$password = wp_generate_password( 24, false );
		$email    = Email_Capture::test_address( $session->get_id() );

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => $role,
				'first_name' => sanitize_text_field( (string) ( $args['first_name'] ?? 'Presstest' ) ),
				'last_name'  => sanitize_text_field( (string) ( $args['last_name'] ?? 'User' ) ),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		// Defence in depth: whatever set them (this request or another
		// plugin's hook), a test user must end up with allowed roles only.
		if ( false === Test_Data_Settings::user_has_only_allowed_roles( $user_id ) ) {
			$this->delete_user( $user_id );

			return new \WP_Error(
				'presstest_role_not_allowed',
				__( 'The test user was given a role or capability that is not allowed, so it was removed. Check for plugins that change roles when users are created.', 'presstest-companion' ),
				array( 'status' => 403 )
			);
		}

		return array(
			'id'       => $user_id,
			'username' => $username,
			'email'    => $email,
			'password' => $password,
			'role'     => $role,
		);
	}

	/**
	 * Whether a user meta key holds roles or capabilities.
	 *
	 * Matches {prefix}capabilities and {prefix}user_level for any table prefix
	 * and every multisite site (e.g. wp_2_capabilities). Case-insensitive,
	 * because MySQL compares meta keys case-insensitively.
	 *
	 * @param string $key Sanitised meta key.
	 * @return bool
	 */
	private static function is_privilege_meta_key( string $key ): bool {
		return 1 === preg_match( '/(^|_)(capabilities|user_level)$/i', $key );
	}

	/**
	 * Creates a post of any registered type.
	 *
	 * Args: post_type (default "post"; not "attachment"), status (default
	 * "publish"), title, content, author (user ID), meta (key => value).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the post belongs to.
	 * @return array|\WP_Error Post details: id, url, post_type, status.
	 */
	public function create_post( array $args, Session $session ) {
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );

		if ( ! post_type_exists( $post_type ) ) {
			/* translators: %s: post type slug. */
			return new \WP_Error( 'presstest_unknown_post_type', sprintf( __( 'Post type "%s" does not exist.', 'presstest-companion' ), $post_type ), array( 'status' => 400 ) );
		}

		// An attachment's files are deleted with it, and this factory can't
		// establish that the session owns them: pointing _wp_attached_file at
		// an existing upload would make cleanup delete the site's file. Tests
		// that need media should upload it (uploads are recorded with files
		// the session created).
		if ( 'attachment' === $post_type ) {
			return new \WP_Error(
				'presstest_attachment_not_allowed',
				__( 'Attachments cannot be created with the post fixture. Upload the file through the site (e.g. the media library) instead; uploads made during a test run are removed afterwards.', 'presstest-companion' ),
				array( 'status' => 400 )
			);
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post_type,
					'post_status'  => sanitize_key( (string) ( $args['status'] ?? 'publish' ) ),
					'post_title'   => sanitize_text_field( (string) ( $args['title'] ?? sprintf( 'Presstest %s %d', $post_type, $session->get_id() ) ) ),
					'post_content' => wp_kses_post( (string) ( $args['content'] ?? '' ) ),
					'post_author'  => absint( $args['author'] ?? 0 ),
					'meta_input'   => (array) ( $args['meta'] ?? array() ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'id'        => $post_id,
			'url'       => get_permalink( $post_id ),
			'post_type' => $post_type,
			'status'    => get_post_status( $post_id ),
		);
	}

	/**
	 * Creates a term.
	 *
	 * Args: taxonomy (default "category"), name.
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the term belongs to.
	 * @return array|\WP_Error Term details: id, taxonomy, slug, url.
	 */
	public function create_term( array $args, Session $session ) {
		$taxonomy = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
		$name     = sanitize_text_field( (string) ( $args['name'] ?? sprintf( 'Presstest %d %s', $session->get_id(), wp_generate_password( 6, false ) ) ) );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			/* translators: %s: taxonomy slug. */
			return new \WP_Error( 'presstest_unknown_taxonomy', sprintf( __( 'Taxonomy "%s" does not exist.', 'presstest-companion' ), $taxonomy ), array( 'status' => 400 ) );
		}

		$term = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$created = get_term( (int) $term['term_id'], $taxonomy );
		$link    = get_term_link( $created );

		return array(
			'id'       => (int) $term['term_id'],
			'taxonomy' => $taxonomy,
			'slug'     => $created->slug,
			'url'      => is_wp_error( $link ) ? '' : $link,
		);
	}

	/**
	 * Creates a comment.
	 *
	 * Args: post_id (required), content, user_id, author_email, approved
	 * (default true).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the comment belongs to.
	 * @return array|\WP_Error Comment details: id, post_id.
	 */
	public function create_comment( array $args, Session $session ) {
		$post_id = absint( $args['post_id'] ?? 0 );

		if ( null === get_post( $post_id ) ) {
			return new \WP_Error( 'presstest_unknown_post', __( 'A valid post_id is required to create a comment.', 'presstest-companion' ), array( 'status' => 400 ) );
		}

		$comment_id = wp_insert_comment(
			wp_slash(
				array(
					'comment_post_ID'      => $post_id,
					'comment_content'      => sanitize_textarea_field( (string) ( $args['content'] ?? 'Presstest comment.' ) ),
					'user_id'              => absint( $args['user_id'] ?? 0 ),
					'comment_author_email' => sanitize_email( (string) ( $args['author_email'] ?? Email_Capture::test_address( $session->get_id() ) ) ),
					'comment_approved'     => false === ( $args['approved'] ?? true ) ? 0 : 1,
				)
			)
		);

		if ( false === $comment_id ) {
			return new \WP_Error( 'presstest_comment_failed', __( 'The comment could not be created.', 'presstest-companion' ), array( 'status' => 500 ) );
		}

		return array(
			'id'      => $comment_id,
			'post_id' => $post_id,
		);
	}
}
