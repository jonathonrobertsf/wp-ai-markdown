<?php
/**
 * HTML to Markdown converter.
 *
 * Converts a WordPress post/page into clean Markdown without requiring
 * any external libraries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIM_Converter {

	/**
	 * Convert a WP_Post object to a Markdown string.
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public static function post_to_markdown( WP_Post $post ): string {
		$lines = [];

		// --- Front matter -------------------------------------------------
		$lines[] = '---';
		$lines[] = 'title: ' . self::yaml_escape( get_the_title( $post ) );
		$lines[] = 'url: ' . get_permalink( $post );
		$lines[] = 'date: ' . get_the_date( 'Y-m-d', $post );
		$lines[] = 'modified: ' . get_the_modified_date( 'Y-m-d', $post );
		$lines[] = 'author: ' . get_the_author_meta( 'display_name', (int) $post->post_author );
		$lines[] = 'type: ' . $post->post_type;

		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$cat_names = array_map( fn( $c ) => $c->name, $categories );
			$lines[]   = 'categories: [' . implode( ', ', $cat_names ) . ']';
		}

		$tags = get_the_tags( $post->ID );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			$tag_names = array_map( fn( $t ) => $t->name, $tags );
			$lines[]   = 'tags: [' . implode( ', ', $tag_names ) . ']';
		}

		$excerpt = has_excerpt( $post->ID )
			? wp_strip_all_tags( get_the_excerpt( $post ) )
			: '';
		if ( $excerpt ) {
			$lines[] = 'excerpt: ' . self::yaml_escape( $excerpt );
		}

		$featured = get_the_post_thumbnail_url( $post->ID, 'full' );
		if ( $featured ) {
			$lines[] = 'featured_image: ' . $featured;
		}

		$lines[] = '---';
		$lines[] = '';

		// --- Title --------------------------------------------------------
		$lines[] = '# ' . get_the_title( $post );
		$lines[] = '';

		// --- Body ---------------------------------------------------------
		$content = apply_filters( 'the_content', $post->post_content );
		$lines[] = self::html_to_markdown( $content );

		return implode( "\n", $lines );
	}

	/**
	 * Convert an HTML string to Markdown.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function html_to_markdown( string $html ): string {
		if ( empty( trim( $html ) ) ) {
			return '';
		}

		// Normalize line endings.
		$html = str_replace( "\r\n", "\n", $html );
		$html = str_replace( "\r", "\n", $html );

		// Use DOMDocument for accurate parsing.
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return wp_strip_all_tags( $html );
		}

		$markdown = self::convert_node( $body );
		$markdown = self::clean_markdown( $markdown );

		return $markdown;
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	private static function convert_node( DOMNode $node, int $list_depth = 0, string $list_type = '' ): string {
		if ( $node->nodeType === XML_TEXT_NODE ) {
			$text = $node->nodeValue;
			// Collapse whitespace for inline text.
			$text = preg_replace( '/\s+/', ' ', $text );
			return $text;
		}

		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			return '';
		}

		/** @var DOMElement $node */
		$tag      = strtolower( $node->tagName );
		$children = '';

		// Recurse into children first for most tags.
		$pass_depth = $list_depth;
		$pass_type  = $list_type;

		if ( $tag === 'ul' ) {
			$pass_type = 'ul';
		} elseif ( $tag === 'ol' ) {
			$pass_type = 'ol';
		}

		if ( $tag === 'ul' || $tag === 'ol' ) {
			$pass_depth = $list_depth + 1;
		}

		if ( ! in_array( $tag, [ 'head', 'script', 'style', 'noscript', 'iframe' ], true ) ) {
			foreach ( $node->childNodes as $child ) {
				$children .= self::convert_node( $child, $pass_depth, $pass_type );
			}
		}

		switch ( $tag ) {
			// Headings.
			case 'h1': return "\n\n# " . trim( $children ) . "\n\n";
			case 'h2': return "\n\n## " . trim( $children ) . "\n\n";
			case 'h3': return "\n\n### " . trim( $children ) . "\n\n";
			case 'h4': return "\n\n#### " . trim( $children ) . "\n\n";
			case 'h5': return "\n\n##### " . trim( $children ) . "\n\n";
			case 'h6': return "\n\n###### " . trim( $children ) . "\n\n";

			// Paragraphs / blocks.
			case 'p':
				return "\n\n" . trim( $children ) . "\n\n";

			case 'blockquote':
				$quoted = preg_replace( '/^/m', '> ', trim( $children ) );
				return "\n\n" . $quoted . "\n\n";

			case 'pre':
				$code_content = '';
				foreach ( $node->childNodes as $child ) {
					if ( $child->nodeType === XML_ELEMENT_NODE && strtolower( $child->tagName ) === 'code' ) {
						$code_content = $child->textContent;
					}
				}
				if ( ! $code_content ) {
					$code_content = $node->textContent;
				}
				$lang = self::detect_code_lang( $node );
				return "\n\n```" . $lang . "\n" . rtrim( $code_content ) . "\n```\n\n";

			// Inline code.
			case 'code':
				// If already inside <pre>, handled above.
				$parent_tag = $node->parentNode ? strtolower( $node->parentNode->nodeName ) : '';
				if ( $parent_tag === 'pre' ) {
					return $children;
				}
				return '`' . $node->textContent . '`';

			// Inline formatting.
			case 'strong':
			case 'b':
				return '**' . trim( $children ) . '**';

			case 'em':
			case 'i':
				return '_' . trim( $children ) . '_';

			case 's':
			case 'del':
			case 'strike':
				return '~~' . trim( $children ) . '~~';

			// Links.
			case 'a':
				/** @var DOMElement $node */
				$href  = $node->getAttribute( 'href' );
				$title = $node->getAttribute( 'title' );
				$label = trim( $children );
				if ( empty( $label ) ) {
					return $href ? $href : '';
				}
				if ( empty( $href ) ) {
					return $label;
				}
				$title_part = $title ? ' "' . addslashes( $title ) . '"' : '';
				return '[' . $label . '](' . $href . $title_part . ')';

			// Images.
			case 'img':
				/** @var DOMElement $node */
				$src   = $node->getAttribute( 'src' );
				$alt   = $node->getAttribute( 'alt' );
				$title = $node->getAttribute( 'title' );
				if ( empty( $src ) ) {
					return '';
				}
				$title_part = $title ? ' "' . addslashes( $title ) . '"' : '';
				return '![' . $alt . '](' . $src . $title_part . ')';

			// Lists.
			case 'ul':
			case 'ol':
				return "\n" . $children . "\n";

			case 'li':
				$indent  = str_repeat( '  ', max( 0, $list_depth - 1 ) );
				$marker  = ( $list_type === 'ol' ) ? '1.' : '-';
				$content = trim( $children );
				return $indent . $marker . ' ' . $content . "\n";

			// Definition list.
			case 'dl': return "\n\n" . $children . "\n\n";
			case 'dt': return "\n**" . trim( $children ) . "**\n";
			case 'dd': return ":   " . trim( $children ) . "\n";

			// Table.
			case 'table': return self::table_to_markdown( $node );
			case 'thead':
			case 'tbody':
			case 'tfoot':
			case 'tr':
			case 'th':
			case 'td':
				return $children;

			// Horizontal rule.
			case 'hr': return "\n\n---\n\n";

			// Line break.
			case 'br': return "  \n";

			// Divs / sections — just pass through children.
			case 'div':
			case 'section':
			case 'article':
			case 'main':
			case 'aside':
			case 'header':
			case 'footer':
			case 'nav':
			case 'figure':
			case 'figcaption':
			case 'span':
			case 'body':
			default:
				return $children;
		}
	}

	/**
	 * Build a Markdown table from a <table> DOMElement.
	 */
	private static function table_to_markdown( DOMElement $table ): string {
		$rows       = [];
		$col_widths = [];

		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$row = [];
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell->nodeType !== XML_ELEMENT_NODE ) {
					continue;
				}
				$cell_tag = strtolower( $cell->tagName );
				if ( $cell_tag !== 'td' && $cell_tag !== 'th' ) {
					continue;
				}
				$text  = trim( wp_strip_all_tags( $cell->textContent ) );
				$text  = str_replace( '|', '\\|', $text );
				$row[] = $text;
			}
			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}

		if ( empty( $rows ) ) {
			return '';
		}

		// Determine max columns.
		$max_cols = max( array_map( 'count', $rows ) );

		// Pad rows.
		foreach ( $rows as &$row ) {
			while ( count( $row ) < $max_cols ) {
				$row[] = '';
			}
		}
		unset( $row );

		// Calculate column widths.
		for ( $c = 0; $c < $max_cols; $c++ ) {
			$col_widths[ $c ] = 3; // minimum width.
			foreach ( $rows as $row ) {
				$col_widths[ $c ] = max( $col_widths[ $c ], mb_strlen( $row[ $c ] ) );
			}
		}

		$md    = "\n\n";
		$first = true;
		foreach ( $rows as $row ) {
			$cells = [];
			for ( $c = 0; $c < $max_cols; $c++ ) {
				$cells[] = str_pad( $row[ $c ], $col_widths[ $c ] );
			}
			$md .= '| ' . implode( ' | ', $cells ) . " |\n";

			if ( $first ) {
				$sep = [];
				for ( $c = 0; $c < $max_cols; $c++ ) {
					$sep[] = str_repeat( '-', $col_widths[ $c ] );
				}
				$md   .= '| ' . implode( ' | ', $sep ) . " |\n";
				$first = false;
			}
		}
		$md .= "\n";

		return $md;
	}

	/**
	 * Attempt to detect the language of a <pre><code> block.
	 */
	private static function detect_code_lang( DOMElement $pre ): string {
		foreach ( $pre->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE && strtolower( $child->tagName ) === 'code' ) {
				/** @var DOMElement $child */
				$class = $child->getAttribute( 'class' );
				if ( preg_match( '/language-(\S+)/', $class, $m ) ) {
					return $m[1];
				}
			}
		}
		$class = $pre->getAttribute( 'class' );
		if ( preg_match( '/language-(\S+)/', $class, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Collapse excessive blank lines and trim leading/trailing whitespace.
	 */
	private static function clean_markdown( string $md ): string {
		// No more than 2 consecutive blank lines.
		$md = preg_replace( '/\n{3,}/', "\n\n", $md );
		return trim( $md );
	}

	/**
	 * Escape a value for YAML front-matter.
	 */
	private static function yaml_escape( string $value ): string {
		if ( strpbrk( $value, ":#\n'\"" ) !== false ) {
			$value = str_replace( '"', '\\"', $value );
			return '"' . $value . '"';
		}
		return $value;
	}
}
