<?php
/**
 * Plugin Name:       WP AI Markdown
 * Plugin URI:        https://github.com/your-repo/wp-ai-markdown
 * Description:       Converts WordPress content into Markdown for AI agents. Serves Markdown automatically to AI crawlers and via ?format=markdown URL parameter.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Jonathon Roberts
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ai-markdown
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIM_VERSION', '1.0.0' );
define( 'WPAIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPAIM_PLUGIN_DIR . 'includes/class-converter.php';
require_once WPAIM_PLUGIN_DIR . 'includes/class-output.php';
require_once WPAIM_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Main plugin bootstrap.
 */
final class WP_AI_Markdown {

	private static ?WP_AI_Markdown $instance = null;

	public static function instance(): WP_AI_Markdown {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function init(): void {
		// Front-end output handler runs early so it can exit before theme loads.
		WPAIM_Output::instance();

		// Admin settings.
		if ( is_admin() ) {
			WPAIM_Admin::instance();
		}
	}
}

WP_AI_Markdown::instance();
