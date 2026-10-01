# Presstest Companion

WordPress plugin that connects a site to the [Presstest](https://presstest.io) automated browser testing service. It provides an admin interface for selecting and running test suites, viewing results, and managing connection settings.

## How it works

1. An administrator opens the **Presstest** admin page and selects one or more test suites to run
2. The plugin POSTs a job request to the Presstest server (`api.presstest.io`) via its own REST API, keeping the API key server-side
3. The Presstest server queues the job, runs it in a Playwright container, and POSTs the JSON results back to this site's REST endpoint (`/wp-json/presstest-companion/v1/report`)
4. The callback is authenticated with a per-site secret token generated on plugin activation (`X-Presstest-Token`)
5. Results are stored in the database and displayed in the **Reports** tab

## Settings

| Setting | Description |
|---|---|
| **API Key** | The `PRESSTEST_API_KEY` value from the Presstest server's `.env` file |
| **Report URL** | Auto-generated — the Presstest server posts results here |
| **Report Token** | Auto-generated on activation — authenticates the results callback |

## Adding test suites

Test suites are defined in two places that must stay in sync:

### 1. The Presstest server (`all-tests/` directory)

Each suite is a directory inside `all-tests/` on the Presstest server containing one or more `_test.js` Playwright test files:

```
all-tests/
  wordpress-core/       ← suite directory name
    login_test.js
  woocommerce-core/
    checkout_test.js
  my-new-suite/         ← add a new directory here
    my_test.js
```

See the [Presstest server README](https://github.com/pie/presstest.io) for full details on writing tests.

### 2. This plugin (`src/apps/presstest-app/data/tests.js`)

Each entry in the `tests` array corresponds to a directory on the server. The `value` must exactly match the directory name:

```js
export const tests = [
    {
        name:  'WordPress Core',   // display label shown in the UI
        value: 'wordpress-core',   // must match the all-tests/ directory name
    },
    {
        name:  'My New Suite',
        value: 'my-new-suite',     // must match all-tests/my-new-suite/
    },
];
```

After editing `tests.js`, deploy the plugin for the new suite to appear in the Run Tests tab.

## Test data (logged-in testing)

With **Settings > Test data > Allow Presstest to create test data** enabled, test runs can create users, posts, orders and memberships, log in as any allowed role, and check emails. Everything a run creates is removed when it finishes, leaving the site as it was found. Use this on staging sites.

- **Roles**: test users may only have the roles ticked in the settings (subscriber and customer by default). Administrator must be ticked explicitly, for testing admin-only functionality.
- **Emails** are never sent during a run. They are captured (even if a "disable emails" plugin is active) so tests can check the right ones would have gone out.
- **Payments**: checkout tests stop, and an admin notice appears, if any enabled payment gateway isn't verifiably in test mode. During test runs, unverified gateways are also removed from checkout. If a gateway is in test mode but Presstest can't tell, confirm it with a filter:

```php
add_filter( 'presstest_companion_gateway_mode', function ( string $mode, WC_Payment_Gateway $gateway ): string {
	return 'my_gateway' === $gateway->id && my_gateway_is_sandbox() ? 'test' : $mode;
}, 10, 2 );
```

- **Cleanup** runs when the run ends, waits for any requests still in flight, and is backed up by an hourly job that clears interrupted runs. **Settings > Test sessions** lists recent runs and can remove all remaining test data immediately; deactivating the plugin does the same.

### What is cleaned up

| Integration | Removed after a run |
|---|---|
| WordPress | Users (and their term relationships), posts of any type, attachments and their files, comments, terms (kept if real content also uses them) |
| WooCommerce | Orders (including checkout drafts and refunds) with stock, coupon usage and total sales restored, cart sessions, guest analytics records. Works with both HPOS and posts order storage |
| Paid Memberships Pro | Orders, membership history, subscriptions, and discount code uses for test users |
| Action Scheduler | One-off and async background jobs queued during the run, and their logs |

Not reverted: edits to content that existed before the run, caches and transients, aggregate counters (e.g. PMPro's view and login counts), and anything already sent to an external service.

### Supporting another plugin

Each plugin is supported by one integration class in `includes/test-sessions/integrations/`. To support a new one, extend `Abstract_Integration`, override what you need, and register it:

```php
use PIE\PresstestCompanion\TestSessions\Integrations\Abstract_Integration;
use PIE\PresstestCompanion\TestSessions\Tracker;

class My_Plugin_Integration extends Abstract_Integration {
	public function get_slug(): string { return 'my-plugin'; }
	public function get_name(): string { return 'My Plugin'; }
	public function is_active(): bool { return class_exists( 'My_Plugin' ); }

	// Record what the plugin creates. Tracker::record() does nothing outside a test run.
	public function register_hooks(): void {
		add_action( 'my_plugin_booking_created', fn( int $id ) => Tracker::record( 'my_booking', $id ) );
	}

	// How to remove it. Lower priorities run first; return true once the object is gone.
	public function get_cleanup_handlers(): array {
		return array(
			'my_booking' => array(
				'priority' => 10,
				'callback' => fn( int $id ): bool => my_plugin_delete_booking( $id ),
			),
		);
	}

	// Optional: problems that make the site unsafe to test (empty array = ready).
	public function preflight(): array { return array(); }

	// Optional: fixtures tests can request with presstest.create( 'my_booking', args ).
	public function get_factories(): array { return array(); }
}

add_filter( 'presstest_companion_integrations', function ( array $integrations ): array {
	$integrations[] = new My_Plugin_Integration();
	return $integrations;
} );
```

See `class-woocommerce.php` and `class-paid-memberships-pro.php` for complete examples, including payment safety checks.
