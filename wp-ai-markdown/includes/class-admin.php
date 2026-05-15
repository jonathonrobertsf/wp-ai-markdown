<?php
/**
 * Admin settings page for WP AI Markdown.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIM_Admin {

	private static ?WPAIM_Admin $instance = null;

	public static function instance(): WPAIM_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'WP AI Markdown', 'wp-ai-markdown' ),
			__( 'AI Markdown', 'wp-ai-markdown' ),
			'manage_options',
			'wp-ai-markdown',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'wpaim_options_group',
			'wpaim_options',
			[ $this, 'sanitize_options' ]
		);

		add_settings_section(
			'wpaim_main',
			__( 'General Settings', 'wp-ai-markdown' ),
			'__return_false',
			'wp-ai-markdown'
		);

		add_settings_field(
			'url_param_enabled',
			__( 'Enable ?format=markdown parameter', 'wp-ai-markdown' ),
			[ $this, 'render_checkbox' ],
			'wp-ai-markdown',
			'wpaim_main',
			[
				'key'         => 'url_param_enabled',
				'label'       => __( 'Allow any visitor to append <code>?format=markdown</code> to a URL to view the Markdown version.', 'wp-ai-markdown' ),
				'default'     => true,
			]
		);

		add_settings_field(
			'ai_crawlers_enabled',
			__( 'Auto-serve to AI crawlers', 'wp-ai-markdown' ),
			[ $this, 'render_checkbox' ],
			'wp-ai-markdown',
			'wpaim_main',
			[
				'key'     => 'ai_crawlers_enabled',
				'label'   => __( 'Automatically serve Markdown to known AI crawlers (GPTBot, ClaudeBot, Perplexity, etc.) instead of HTML.', 'wp-ai-markdown' ),
				'default' => true,
			]
		);

		add_settings_field(
			'include_front_matter',
			__( 'Include YAML front matter', 'wp-ai-markdown' ),
			[ $this, 'render_checkbox' ],
			'wp-ai-markdown',
			'wpaim_main',
			[
				'key'     => 'include_front_matter',
				'label'   => __( 'Prepend YAML front matter block (title, URL, date, author, tags, etc.) to every Markdown document.', 'wp-ai-markdown' ),
				'default' => true,
			]
		);
	}

	public function sanitize_options( mixed $input ): array {
		$sanitized = [];
		$bools     = [ 'url_param_enabled', 'ai_crawlers_enabled', 'include_front_matter' ];
		foreach ( $bools as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}
		return $sanitized;
	}

	public function render_checkbox( array $args ): void {
		$options = get_option( 'wpaim_options', [] );
		$key     = $args['key'];
		$checked = isset( $options[ $key ] ) ? (bool) $options[ $key ] : $args['default'];
		printf(
			'<label><input type="checkbox" name="wpaim_options[%s]" value="1" %s /> %s</label>',
			esc_attr( $key ),
			checked( $checked, true, false ),
			wp_kses( $args['label'], [ 'code' => [] ] )
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wpaim-settings">
			<h1><?php esc_html_e( 'WP AI Markdown', 'wp-ai-markdown' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Converts WordPress content into Markdown. Serves it automatically to AI crawlers and via a URL parameter.', 'wp-ai-markdown' ); ?>
			</p>

			<div class="wpaim-how-to">
				<h2><?php esc_html_e( 'How to use', 'wp-ai-markdown' ); ?></h2>
				<ul>
					<li>
						<strong><?php esc_html_e( 'View any post/page as Markdown:', 'wp-ai-markdown' ); ?></strong><br/>
						<?php esc_html_e( 'Add', 'wp-ai-markdown' ); ?>
						<code>?format=markdown</code>
						<?php esc_html_e( 'to any post or page URL.', 'wp-ai-markdown' ); ?><br/>
						<?php esc_html_e( 'Example:', 'wp-ai-markdown' ); ?>
						<code><?php echo esc_html( home_url( '/sample-page/?format=markdown' ) ); ?></code>
					</li>
					<li>
						<strong><?php esc_html_e( 'Site-wide post index:', 'wp-ai-markdown' ); ?></strong><br/>
						<code><?php echo esc_html( home_url( '/?format=markdown' ) ); ?></code>
					</li>
					<li>
						<strong><?php esc_html_e( 'AI crawlers:', 'wp-ai-markdown' ); ?></strong><br/>
						<?php esc_html_e( 'GPTBot, ClaudeBot, Perplexity, Google-Extended and others automatically receive Markdown instead of HTML when the option below is enabled.', 'wp-ai-markdown' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Discovery tag:', 'wp-ai-markdown' ); ?></strong><br/>
						<?php esc_html_e( 'A', 'wp-ai-markdown' ); ?>
						<code>&lt;link rel="alternate" type="text/markdown" /&gt;</code>
						<?php esc_html_e( 'tag is added to every page\'s &lt;head&gt; so AI tools can discover the Markdown version.', 'wp-ai-markdown' ); ?>
					</li>
				</ul>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpaim_options_group' );
				do_settings_sections( 'wp-ai-markdown' );
				submit_button();
				?>
			</form>

			<hr/>

			<div class="wpaim-preview">
				<h2><?php esc_html_e( 'Live Preview', 'wp-ai-markdown' ); ?></h2>
				<p><?php esc_html_e( 'Enter a post URL to preview its Markdown output:', 'wp-ai-markdown' ); ?></p>
				<input type="url" id="wpaim-preview-url" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/sample-page/' ) ); ?>" />
				<a id="wpaim-preview-btn" class="button button-secondary" href="#" target="_blank">
					<?php esc_html_e( 'Open Markdown Preview', 'wp-ai-markdown' ); ?>
				</a>
				<script>
				document.getElementById('wpaim-preview-btn').addEventListener('click', function(e) {
					e.preventDefault();
					var url = document.getElementById('wpaim-preview-url').value.trim();
					if (!url) return;
					var sep = url.includes('?') ? '&' : '?';
					window.open(url + sep + 'format=markdown', '_blank');
				});
				</script>
			</div>

			<hr/>

			<div class="wpaim-agents">
				<h2><?php esc_html_e( 'Detected AI User-Agents', 'wp-ai-markdown' ); ?></h2>
				<p><?php esc_html_e( 'Markdown is automatically served to requests from these User-Agent strings:', 'wp-ai-markdown' ); ?></p>
				<code>
					GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, anthropic-ai, Claude-Web,
					Google-Extended, Googlebot-AI, Gemini, FacebookBot, meta-externalagent,
					Bingbot, BingPreview, PerplexityBot, cohere-ai, YouBot, CCBot,
					DataForSeoBot, Diffbot, AI2Bot, Timpibot, omgili, omgilibot,
					PetalBot, Bytespider, ImagesiftBot
				</code>
			</div>
		</div>
		<?php
	}

	public function enqueue_styles( string $hook ): void {
		if ( $hook !== 'settings_page_wp-ai-markdown' ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', '
			.wpaim-settings .wpaim-how-to,
			.wpaim-settings .wpaim-preview,
			.wpaim-settings .wpaim-agents {
				background: #fff;
				border: 1px solid #c3c4c7;
				padding: 16px 20px;
				margin: 20px 0;
				max-width: 800px;
				border-radius: 4px;
			}
			.wpaim-settings .wpaim-how-to ul { list-style: disc; padding-left: 20px; }
			.wpaim-settings .wpaim-how-to li { margin-bottom: 12px; }
			.wpaim-settings code { background: #f0f0f1; padding: 2px 5px; border-radius: 3px; font-size: 13px; }
			.wpaim-settings .wpaim-agents code { display: block; white-space: normal; line-height: 1.8; }
		' );
	}
}
