<?php
/**
 * Plugin update checker.
 *
 * Registers an update source pointing at the hosted metadata JSON so WordPress
 * can detect new versions and prompt the administrator to update.
 *
 * @link       https://presstest.io
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
	PRESSTEST_UPDATE_JSON_URL,
	PRESSTEST_COMPANION_FILE_PATH . 'presstest-companion.php',
	'presstest-companion'
);
