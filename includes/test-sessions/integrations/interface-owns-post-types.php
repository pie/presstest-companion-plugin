<?php
/**
 * Optional contract for integrations whose objects are stored as posts.
 *
 * Some plugins store their own objects as posts — e.g. WooCommerce orders
 * with posts-based storage. Those posts are recorded twice: by the WordPress
 * integration (as "post") and by the owning integration (e.g. "wc_order").
 * Declaring ownership stops the generic post cleanup from deleting them
 * before the owner's handler has run its own cleanup (restoring stock, etc.),
 * including when the owner's plugin is inactive.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

/**
 * Declares which post types an integration's cleanup owns.
 */
interface Owns_Post_Types {

	/**
	 * Post types owned by this integration, mapped to the object type its
	 * cleanup handler records them as.
	 *
	 * Must not depend on the integrated plugin being active.
	 *
	 * @return array<string, string> Post type => object type, e.g. array( 'shop_order' => 'wc_order' ).
	 */
	public function get_owned_post_types(): array;
}
