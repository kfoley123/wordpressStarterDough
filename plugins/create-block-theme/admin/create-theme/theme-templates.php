<?php

require_once( __DIR__ . '/theme-media.php' );
require_once( __DIR__ . '/theme-patterns.php' );

class Theme_Templates {

	/**
	 * Build a collection of templates and template-parts that should be exported (and modified)
	 * based on the given export_type.
	 *
	 * @param string $export_type The type of export to perform. 'all', 'current', or 'user'.
	 * @return object An object containing the templates and parts that should be exported.
	 */
	public static function get_theme_templates( $export_type ) {

		$templates          = get_block_templates();
		$template_parts     = get_block_templates( array(), 'wp_template_part' );
		$exported_templates = array();
		$exported_parts     = array();

		// build collection of templates/parts in currently activated theme
		$templates_paths = get_block_theme_folders();
		$templates_path  = get_stylesheet_directory() . '/' . $templates_paths['wp_template'] . '/';
		$parts_path      = get_stylesheet_directory() . '/' . $templates_paths['wp_template_part'] . '/';

		foreach ( $templates as $template ) {
			if ( self::should_include_template(
				$template,
				$export_type,
				$templates_path
			) ) {
				$exported_templates[] = self::cleanup_template( $template );
			}
		}

		foreach ( $template_parts as $template ) {
			if ( self::should_include_template(
				$template,
				$export_type,
				$parts_path
			) ) {
				$exported_parts[] = self::cleanup_template( $template );
			}
		}

		return (object) array(
			'templates' => $exported_templates,
			'parts'     => $exported_parts,
		);

	}

	/**
	 * Filter a template out (return false) based on the export_type expected and the templates origin.
	 *
	 * @param object $template The template to filter.
	 * @param string $export_type The type of export to perform. 'all', 'current', or 'user'.
	 * @param string $path The path to the templates folder.
	 * @return object|bool The template if it should be included, or false if it should be excluded.
	 */
	static function should_include_template( $template, $export_type, $path ) {
		if ( 'theme' === $template->source && 'user' === $export_type ) {
			return false;
		}
		if (
			'theme' === $template->source &&
			'current' === $export_type &&
			! file_exists( $path . $template->slug . '.html' )
		) {
			return false;
		}
		return true;
	}

	/**
	 * Clean up the template content before exporting.
	 * @param object $template The template to clean up.
	 * @return object The cleaned up template.
	 */
	private static function cleanup_template( $template ) {
		// NOTE: Dashes are encoded as \u002d in the content that we get (noteably in things like css variables used in templates)
		// This replaces that with dashes again. We should consider decoding the entire string but that is proving difficult.
		$template->content = str_replace( '\u002d', '-', $template->content );

		return $template;
	}

	/**
	 * Replace the old theme slug with the new theme slug in the template content.
	 *
	 * @param object $template The template to replace the namespace in.
	 * @param string $new_slug The new theme slug.
	 * @return object The template with the namespace replaced.
	 */
	public static function replace_template_namespace( $template, $new_slug ) {
		$old_slug = wp_get_theme()->get( 'TextDomain' );
		if ( $new_slug ) {
			$template->content = str_replace( $old_slug, $new_slug, $template->content );
		}
		return $template;
	}

	/**
	 * Clear all user templates customizations.
	 * This will remove all user templates from the database.
	 */
	public static function clear_user_templates_customizations() {
		//remove all user templates (they have been saved in the theme)
		$templates      = get_block_templates();
		$template_parts = get_block_templates( array(), 'wp_template_part' );
		foreach ( $template_parts as $template ) {
			if ( 'custom' !== $template->source ) {
				continue;
			}
			wp_delete_post( $template->wp_id, true );
		}

		foreach ( $templates as $template ) {
			if ( 'custom' !== $template->source ) {
				continue;
			}
			wp_delete_post( $template->wp_id, true );
		}
	}

	/**
	 * Extract content from a template that need to be patternized.
	 * Return the modified template and the pattern that was created
	 *
	 * @param object $template The template to extract content from.
	 * @return object The template with the patternized content.
	 */
	public static function paternize_template( $template, $slug = null ) {
		// If there is any PHP in the template then paternize
		if ( str_contains( $template->content, '<?php' ) ) {
			$pattern                 = Theme_Patterns::pattern_from_template( $template, $slug );
			$pattern_link_attributes = array(
				'slug' => $pattern['slug'],
			);
			$template->content       = Theme_Patterns::create_pattern_link( $pattern_link_attributes );
			$template->pattern       = $pattern['content'];
		}
		return $template;
	}

	/**
	 * Prepare a template for export.
	 * This will escape text in the template, eliminate environment specific content,
	 * make template images local, and paternize the template.
	 *
	 * @param object $template The template to prepare for export.
	 * @param string $slug The slug of the theme.
	 * @return object The prepared template.
	 */
	public static function prepare_template_for_export( $template, $slug = null, $options = null ) {

		if ( ! $options ) {
			$options = array(
				'localizeText'   => false,
				'removeNavRefs'  => true,
				'localizeImages' => true,
			);
		}

		$template = self::eliminate_environment_specific_content( $template, $options );

		if ( array_key_exists( 'localizeText', $options ) && $options['localizeText'] ) {
			$template = self::escape_text_in_template( $template );
		}

		if ( array_key_exists( 'localizeImages', $options ) && $options['localizeImages'] ) {
			$template = Theme_Media::make_template_images_local( $template );
		}

		if ( $slug ) {
			$template = self::replace_template_namespace( $template, $slug );
		}

		$template = self::paternize_template( $template, $slug );

		return $template;
	}

	/**
	 * Copy the templates and template-parts (including user customizations)
	 * as well as any media to the theme filesystem.
	 * If patterns need to be created for media or localizations they will also be added.
	 *
	 * @param string $export_type The type of export to perform. 'all', 'current', or 'user'.
	 * @param string $path The path to the theme folder. If null it is assumed to be the current theme.
	 * @param string $slug The slug of the theme. If null it is assumed to be the current theme.
	 */
	public static function add_templates_to_local( $export_type, $path = null, $slug = null, $options = null ) {

		$theme_templates  = self::get_theme_templates( $export_type );
		$template_folders = get_block_theme_folders();

		$base_dir          = $path ? $path : get_stylesheet_directory();
		$template_dir      = $base_dir . DIRECTORY_SEPARATOR . $template_folders['wp_template'];
		$template_part_dir = $base_dir . DIRECTORY_SEPARATOR . $template_folders['wp_template_part'];
		$patterns_dir      = $base_dir . DIRECTORY_SEPARATOR . 'patterns';

		// If there is no templates folder, and it is needed, create it.
		if ( ! is_dir( $template_dir ) && count( $theme_templates->templates ) > 0 ) {
			wp_mkdir_p( $template_dir );
		}

		// If there is no parts folder, and it is needed, create it.
		if ( ! is_dir( $template_part_dir ) && count( $theme_templates->parts ) > 0 ) {
			wp_mkdir_p( $template_part_dir );
		}

		foreach ( $theme_templates->templates as $template ) {

			$template = self::prepare_template_for_export( $template, $slug, $options );

			// Write the template content
			file_put_contents(
				$template_dir . DIRECTORY_SEPARATOR . $template->slug . '.html',
				$template->content
			);

			// Write the media assets if there are any
			if ( $template->media ) {
				Theme_Media::add_media_to_local( $template->media );
			}

			// Write the pattern if it exists
			if ( isset( $template->pattern ) ) {
				// If there is no patterns folder, create it.
				if ( ! is_dir( $patterns_dir ) ) {
					wp_mkdir_p( $patterns_dir );
				}
				file_put_contents(
					$patterns_dir . DIRECTORY_SEPARATOR . $template->slug . '.php',
					$template->pattern
				);
			}
		}

		foreach ( $theme_templates->parts as $template ) {

			$template = self::prepare_template_for_export( $template, $slug, $options );

			// Write the template content
			file_put_contents(
				$template_part_dir . DIRECTORY_SEPARATOR . $template->slug . '.html',
				$template->content
			);

			// Write the media assets if there are any
			if ( $template->media ) {
				Theme_Media::add_media_to_local( $template->media );
			}

			// Write the pattern if it exists
			if ( isset( $template->pattern ) ) {
				// If there is no patterns folder, create it.
				if ( ! is_dir( $patterns_dir ) ) {
					wp_mkdir_p( $patterns_dir );
				}
				file_put_contents(
					$patterns_dir . DIRECTORY_SEPARATOR . $template->slug . '.php',
					$template->pattern
				);
			}
		}
	}

	public static function escape_text_in_template( $template ) {

		$template_blocks  = parse_blocks( $template->content );
		$text_to_localize = array();

		// Gather up all the strings that need to be localized
		foreach ( $template_blocks as &$block ) {
			$text_to_localize = array_merge( $text_to_localize, self::get_text_to_localize_from_block( $block ) );
		}
		$text_to_localize = array_unique( $text_to_localize );

		// Localize the strings
		foreach ( $text_to_localize as $text ) {
			$template->content = str_replace( $text, self::escape_text( $text ), $template->content );
		}

		return $template;
	}

	private static function get_text_to_localize_from_block( $block ) {

		$text_to_localize = array();

		// Text Blocks (paragraphs and headings)
		if ( in_array( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/verse' ), true ) ) {
			$markup = $block['innerContent'][0];
			// remove the tags from the beginning and end of the markup
			$markup             = substr( $markup, strpos( $markup, '>' ) + 1 );
			$markup             = substr( $markup, 0, strrpos( $markup, '<' ) );
			$text_to_localize[] = $markup;
		}

		// Quote Blocks
		if ( in_array( $block['blockName'], array( 'core/quote', 'core/pullquote' ), true ) ) {
			$markup = serialize_blocks( array( $block ) );
			// Grab paragraph tag content
			if ( preg_match( '/<p[^>]*>(.*?)<\/p>/', $markup, $matches ) ) {
				$text_to_localize[] = $matches[1];
			}
			// Grab cite tag content
			if ( preg_match( '/<cite[^>]*>(.*?)<\/cite>/', $markup, $matches ) ) {
				$text_to_localize[] = $matches[1];
			}
		}

		// Button Blocks
		if ( in_array( $block['blockName'], array( 'core/button' ), true ) ) {
			$markup = $block['innerContent'][0];
			if ( preg_match( '/<a[^>]*>(.*?)<\/a>/', $markup, $matches ) ) {
				$text_to_localize[] = $matches[1];
			}
		}

		// Alt text in Image and Cover Blocks
		if ( in_array( $block['blockName'], array( 'core/image', 'core/cover', 'core/media-text' ), true ) ) {
			$markup = $block['innerContent'][0];
			if ( preg_match( '/alt="(.*?)"/', $markup, $matches ) ) {
				$text_to_localize[] = $matches[1];
			}
			if ( array_key_exists( 'alt', $block['attrs'] ) ) {
				$text_to_localize[] = $block['attrs']['alt'];
			}
		}

		// Table Blocks
		if ( in_array( $block['blockName'], array( 'core/table' ), true ) ) {
			$markup = serialize_blocks( array( $block ) );
			// Grab table cell content
			if ( preg_match_all( '/<td[^>]*>(.*?)<\/td>/', $markup, $matches ) ) {
				$text_to_localize = array_merge( $text_to_localize, $matches[1] );
			}
			// Grab table header content
			if ( preg_match_all( '/<th[^>]*>(.*?)<\/th>/', $markup, $matches ) ) {
				$text_to_localize = array_merge( $text_to_localize, $matches[1] );
			}
			// Grab the caption
			if ( preg_match_all( '/<figcaption[^>]*>(.*?)<\/figcaption>/', $markup, $matches ) ) {
				$text_to_localize = array_merge( $text_to_localize, $matches[1] );
			}
		}

		// process inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$text_to_localize = array_merge( $text_to_localize, self::get_text_to_localize_from_block( $inner_block ) );
			}
		}

		return $text_to_localize;
	}

	public static function escape_text( $text ) {
		if ( ! $text ) {
			return $text;
		}
		$text = addcslashes( $text, "'" );
		return "<?php echo __('" . $text . "', '" . wp_get_theme()->get( 'TextDomain' ) . "');?>";
	}

	private static function eliminate_environment_specific_content_from_block( $block, $options = null ) {

		// remove theme attribute from template parts
		if ( 'core/template-part' === $block['blockName'] && isset( $block['attrs']['theme'] ) ) {
			unset( $block['attrs']['theme'] );
		}

		// (optionally) remove ref attribute from nav blocks
		if ( 'core/navigation' === $block['blockName'] && isset( $block['attrs']['ref'] ) ) {
			if ( ! $options || ( array_key_exists( 'removeNavRefs', $options ) && $options['removeNavRefs'] ) ) {
				unset( $block['attrs']['ref'] );
			}
		}

		// remove id attributes and classes from image and cover blocks
		if ( in_array( $block['blockName'], array( 'core/image', 'core/cover' ), true ) ) {
			// remove id attribute from image and cover blocks
			if ( isset( $block['attrs']['id'] ) ) {
				$image_id = $block['attrs']['id'];
				unset( $block['attrs']['id'] );
				// remove wp-image-[id] class from inner content
				foreach ( $block['innerContent'] as $inner_key => $inner_content ) {
					$block['innerContent'][ $inner_key ] = str_replace( 'wp-image-' . $image_id, '', $inner_content );
				}
			}
		}

		// remove taxQuery attribute from query blocks
		if ( 'core/query' === $block['blockName'] ) {
			if ( isset( $block['attrs']['query']['taxQuery'] ) ) {
				unset( $block['attrs']['query']['taxQuery'] );
			}
		}

		// process any inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block_key => $inner_block ) {
				$block['innerBlocks'][ $inner_block_key ] = static::eliminate_environment_specific_content_from_block( $inner_block, $options );
			}
		}

		return $block;
	}

	public static function eliminate_environment_specific_content( $template, $options = null ) {

		$template_blocks = parse_blocks( $template->content );
		$parsed_content  = '';

		foreach ( $template_blocks as $block ) {
			$parsed_block    = static::eliminate_environment_specific_content_from_block( $block, $options );
			$parsed_content .= serialize_block( $parsed_block );
		}

		$template->content = $parsed_content;
		return $template;
	}
}
