<?php
/**
 * Captures and blocks every email sent during a test session.
 *
 * Emails are never sent while testing; they are stored against the session so
 * tests can check the right ones would have gone out. Capture happens on the
 * wp_mail filter, which core runs before pre_wp_mail and before PHPMailer — so
 * it still works when a "disable emails" or mail-logging plugin is active.
 *
 * An email belongs to a session when either:
 *   - it is sent during a session request; or
 *   - any recipient is a session test user (presstest-<id>-…@presstest.invalid),
 *     which catches emails sent later from cron or background queues.
 *
 * Blocking is layered: pre_wp_mail short-circuits wp_mail() as "sent"; if a
 * plugin has replaced wp_mail() and skipped that filter, phpmailer_init strips
 * the recipients so PHPMailer refuses to send.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Hooks into wp_mail() to capture and block session emails.
 */
class Email_Capture {

	/**
	 * Domain for test user addresses. The .invalid TLD is reserved (RFC 2606),
	 * so these addresses can never be delivered even if blocking failed.
	 */
	const TEST_EMAIL_DOMAIN = 'presstest.invalid';

	/**
	 * Session data access.
	 *
	 * @var Session_Repository
	 */
	private Session_Repository $repository;

	/**
	 * Whether the email currently passing through wp_mail() must be blocked.
	 *
	 * @var bool
	 */
	private bool $block_current = false;

	/**
	 * Sets up capture.
	 *
	 * @param Session_Repository $repository Session data access.
	 */
	public function __construct( Session_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers the mail hooks. Late priorities so we see the final email
	 * after other plugins have modified it, and have the last word on sending.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_mail', array( $this, 'capture' ), PHP_INT_MAX );
		add_filter( 'pre_wp_mail', array( $this, 'block' ), PHP_INT_MAX );
		add_action( 'phpmailer_init', array( $this, 'block_phpmailer' ), PHP_INT_MAX );
	}

	/**
	 * Builds a unique, undeliverable address for a session test user.
	 *
	 * @param int $session_id Session ID.
	 * @return string
	 */
	public static function test_address( int $session_id ): string {
		return sprintf( 'presstest-%d-%s@%s', $session_id, strtolower( wp_generate_password( 8, false ) ), self::TEST_EMAIL_DOMAIN );
	}

	/**
	 * Stores the email if it belongs to a session. Never alters the email.
	 *
	 * @param array $atts wp_mail() arguments: to, subject, message, headers, attachments.
	 * @return array Unchanged arguments.
	 */
	public function capture( $atts ) {
		$this->block_current = false;

		if ( ! is_array( $atts ) ) {
			return $atts;
		}

		$recipients = self::normalise_list( $atts['to'] ?? array() );
		$session    = Session_Context::current() ?? $this->session_for_recipients( $recipients );

		if ( null === $session ) {
			return $atts;
		}

		$this->repository->record_email(
			$session->get_id(),
			array(
				'recipients'  => $recipients,
				'subject'     => (string) ( $atts['subject'] ?? '' ),
				'message'     => (string) ( $atts['message'] ?? '' ),
				'headers'     => self::normalise_list( $atts['headers'] ?? array(), "\n" ),
				'attachments' => array_map( 'wp_basename', self::normalise_list( $atts['attachments'] ?? array(), "\n" ) ),
			)
		);

		$this->block_current = true;

		return $atts;
	}

	/**
	 * Short-circuits wp_mail() for captured emails, reporting success so the
	 * site's own flow continues exactly as if the email had been sent.
	 *
	 * @param null|bool $short_circuit Short-circuit value from earlier filters.
	 * @return null|bool True to skip sending; otherwise the incoming value.
	 */
	public function block( $short_circuit ) {
		if ( true === $this->block_current ) {
			$this->block_current = false;
			return true;
		}

		return $short_circuit;
	}

	/**
	 * Fallback for replaced wp_mail() implementations that skip pre_wp_mail:
	 * removes every recipient, so PHPMailer refuses to send.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer about to send.
	 * @return void
	 */
	public function block_phpmailer( $phpmailer ): void {
		if ( true !== $this->block_current ) {
			return;
		}

		$this->block_current = false;
		$phpmailer->clearAllRecipients();
	}

	/**
	 * Finds the open session a test user address belongs to.
	 *
	 * @param string[] $recipients Recipient addresses, possibly with names.
	 * @return Session|null
	 */
	private function session_for_recipients( array $recipients ): ?Session {
		$pattern = '/presstest-(\d+)-[a-z0-9]+@' . preg_quote( self::TEST_EMAIL_DOMAIN, '/' ) . '/i';

		foreach ( $recipients as $recipient ) {
			if ( 1 === preg_match( $pattern, $recipient, $matches ) ) {
				$session = $this->repository->find( (int) $matches[1] );
				if ( null !== $session && $session->is_open() ) {
					return $session;
				}
			}
		}

		return null;
	}

	/**
	 * Turns wp_mail()'s string-or-array arguments into a clean list.
	 *
	 * @param mixed  $value     String or array from wp_mail().
	 * @param string $separator Separator used when $value is a string.
	 * @return string[]
	 */
	private static function normalise_list( $value, string $separator = ',' ): array {
		$items = is_array( $value ) ? $value : explode( $separator, (string) $value );
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $items ) ), fn( string $item ): bool => '' !== $item ) );
	}
}
