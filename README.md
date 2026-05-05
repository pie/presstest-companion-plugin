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
