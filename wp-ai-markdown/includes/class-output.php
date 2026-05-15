<?php
/**
 * Output handler.
 *
 * Intercepts requests and serves Markdown when:
 *   1. The ?format=markdown query parameter is present, OR
 *   2. The visitor's User-Agent matches a known AI crawler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIM_Output {

	private static ?WPAIM_Output $instance = null;

	/**
	 * AI / LLM crawler User-Agent substrings (case-insensitive).
	 *
	 * @var string[]
	 */
	private const AI_USER_AGENTS = [
		// OpenAI
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		// Anthropic
		'ClaudeBot',
		'anthropic-ai',
		'Claude-Web',
		// Google AI
		'Google-Extended',
		'Googlebot-AI',
		'Gemini',
		// Meta
		'FacebookBot',
		'meta-externalagent',
		// Microsoft / Bing AI
		'Bingbot',
		'BingPreview',
		// Perplexity
		'PerplexityBot',
		// Common AI / LLM identifiers
		'cohere-ai',
		'YouBot',
		'CCBot',
		'DataForSeoBot',
		'Diffbot',
		'AI2Bot',
		'Timpibot',
		'omgili',
		'omgilibot',
		'PetalBot',
		'Bytespider',
		'ImagesiftBot',
	];

	public static function instance(): WPAIM_Output {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Hook early — priority 1 — before the theme renders anything.
		add_action( 'template_redirect', [ $this, 'maybe_serve_markdown' ], 1 );

		// Add <link> tag pointing to Markdown version so crawlers can discover it.
		add_action( 'wp_head', [ $this, 'add_markdown_link_tag' ] );
	}

	/**
	 * Decide whether to serve Markdown and do so if needed.
	 */
	public function maybe_serve_markdown(): void {
		$options = get_option( 'wpaim_options', [] );

		$url_param_enabled    = ! isset( $options['url_param_enabled'] ) || (bool) $options['url_param_enabled'];
		$ai_crawlers_enabled  = ! isset( $options['ai_crawlers_enabled'] ) || (bool) $options['ai_crawlers_enabled'];

		$via_url_param = $url_param_enabled && isset( $_GET['format'] ) && $_GET['format'] === 'markdown';
		$via_ai_agent  = $ai_crawlers_enabled && $this->is_ai_crawler();

		if ( ! $via_url_param && ! $via_ai_agent ) {
			return;
		}

		// Only act on singular posts/pages/CPTs.
		if ( ! is_singular() ) {
			// For non-singular pages requested via URL param, show a site-wide index.
			if ( $via_url_param && is_home() ) {
				$this->serve_site_index();
				return;
			}
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$this->serve_post_markdown( $post, $via_url_param );
	}

	/**
	 * Output Markdown for a single post.
	 *
	 * @param WP_Post $post
	 * @param bool    $via_url_param  True when requested via ?format=markdown.
	 */
	private function serve_post_markdown( WP_Post $post, bool $via_url_param ): void {
		$markdown = WPAIM_Converter::post_to_markdown( $post );

		if ( $via_url_param ) {
			// Human-friendly: serve as plain text in browser.
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Markdown-Source: wp-ai-markdown' );
			header( 'Cache-Control: public, max-age=3600' );
		} else {
			// AI crawler: serve text/markdown with a proper MIME type.
			status_header( 200 );
			header( 'Content-Type: text/markdown; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'X-Markdown-Source: wp-ai-markdown' );
		}

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Output a simple Markdown index of recent posts (for home page + ?format=markdown).
	 */
	private function serve_site_index(): void {
		$posts = get_posts( [
			'numberposts' => 50,
			'post_status' => 'publish',
		] );

		$lines   = [];
		$lines[] = '# ' . get_bloginfo( 'name' ) . ' — Content Index';
		$lines[] = '';
		$lines[] = get_bloginfo( 'description' );
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## Posts';
		$lines[] = '';

		foreach ( $posts as $post ) {
			$url     = get_permalink( $post );
			$md_url  = add_query_arg( 'format', 'markdown', $url );
			$date    = get_the_date( 'Y-m-d', $post );
			$lines[] = "- [{$post->post_title}]({$url}) — `{$date}` | [View Markdown]({$md_url})";
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Markdown-Source: wp-ai-markdown' );

		echo implode( "\n", $lines ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Add a <link> discovery tag pointing to the Markdown version of the current page.
	 */
	public function add_markdown_link_tag(): void {
		if ( ! is_singular() ) {
			return;
		}

		$options = get_option( 'wpaim_options', [] );
		if ( isset( $options['url_param_enabled'] ) && ! $options['url_param_enabled'] ) {
			return;
		}

		$md_url = add_query_arg( 'format', 'markdown', get_permalink() );
		echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $md_url ) . '" title="Markdown version" />' . "\n";
	}

	/**
	 * Check whether the current visitor appears to be an AI crawler.
	 */
	private function is_ai_crawler(): bool {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( empty( $ua ) ) {
			return false;
		}

		$ua_lower = strtolower( $ua );
		foreach ( self::AI_USER_AGENTS as $agent ) {
			if ( stripos( $ua_lower, strtolower( $agent ) ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
