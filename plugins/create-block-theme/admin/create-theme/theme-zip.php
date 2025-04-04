<?php

require_once( __DIR__ . '/theme-media.php' );
require_once( __DIR__ . '/theme-templates.php' );
require_once( __DIR__ . '/theme-patterns.php' );
require_once( __DIR__ . '/cbt-zip-archive.php' );

class Theme_Zip {

	public static function create_zip( $filename, $theme_slug = null ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'Zip Export not supported.' );
		}

		if ( ! $theme_slug ) {
			$theme_slug = get_stylesheet();
		}

		$zip = new CbtZipArchive( $theme_slug );
		$zip->open( $filename, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		return $zip;
	}

	public static function add_theme_json_to_zip( $zip, $theme_json ) {
		$zip->addFromStringToTheme(
			'theme.json',
			$theme_json
		);
		return $zip;
	}

	public static function add_activated_fonts_to_zip( $zip, $theme_json_string ) {

		$theme_json = json_decode( $theme_json_string, true );

		$font_families_to_copy     = Theme_Fonts::get_user_activated_fonts();
		$theme_font_asset_location = '/assets/fonts/';
		$font_slugs_to_remove      = array();

		foreach ( $font_families_to_copy as &$font_family ) {
			if ( ! isset( $font_family['fontFace'] ) ) {
				continue;
			}
			$font_slugs_to_remove[] = $font_family['slug'];
			foreach ( $font_family['fontFace'] as &$font_face ) {
				$font_filename = basename( $font_face['src'] );
				$font_dir      = wp_get_font_dir();
				if ( str_contains( $font_face['src'], $font_dir['url'] ) ) {
					$zip->addFileToTheme( $font_dir['path'] . '/' . $font_filename, $theme_font_asset_location . '/' . $font_filename );
				} else {
					// otherwise download it from wherever it is hosted
					$tmp_file = download_url( $font_face['src'] );
					$zip->addFileToTheme( $tmp_file, $theme_font_asset_location . '/' . $font_filename );
					unlink( $tmp_file );
				}

				$font_face['src'] = 'file:./assets/fonts/' . $font_filename;
			}
		}

		if ( ! isset( $theme_json['settings']['typography']['fontFamilies'] ) ) {
			$theme_json['settings']['typography']['fontFamilies'] = array();
		}

		// Remove user fonts that have already been added to the theme_json
		// otherwise they will be duplicated when we add them next
		foreach ( $theme_json['settings']['typography']['fontFamilies'] as $key => $theme_font_family ) {
			if ( in_array( $theme_font_family['slug'], $font_slugs_to_remove, true ) ) {
				unset( $theme_json['settings']['typography']['fontFamilies'][ $key ] );
			}
		}

		// Copy user fonts to theme
		$theme_json['settings']['typography']['fontFamilies'] = array_merge(
			$theme_json['settings']['typography']['fontFamilies'],
			$font_families_to_copy
		);

		return wp_json_encode( $theme_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	}

	public static function copy_theme_to_zip( $zip, $new_slug, $new_name ) {

		$theme    = wp_get_theme();
		$old_slug = $theme->get( 'TextDomain' );
		$old_name = $theme->get( 'Name' );

		// Get real path for our folder
		$theme_path = get_stylesheet_directory();

		// Create recursive directory iterator
		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $theme_path ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		// Add all the files (except for templates)
		foreach ( $files as $name => $file ) {

			// Skip directories (they would be added automatically)
			if ( ! $file->isDir() ) {

				// Get real and relative path for current file
				$file_path = wp_normalize_path( $file );

				// If the path is for templates/parts ignore it
				if (
					strpos( $file_path, 'block-template-parts/' ) ||
					strpos( $file_path, 'block-templates/' ) ||
					strpos( $file_path, 'templates/' ) ||
					strpos( $file_path, 'parts/' )
				) {
					continue;
				}

				$relative_path = substr( $file_path, strlen( $theme_path ) + 1 );

				// Replace only text files, skip png's and other stuff.
				$valid_extensions       = array( 'php', 'css', 'scss', 'js', 'txt', 'html' );
				$valid_extensions_regex = implode( '|', $valid_extensions );
				if ( ! preg_match( "/\.({$valid_extensions_regex})$/", $relative_path ) ) {
					$zip->addFileToTheme( $file_path, $relative_path );
				} else {
					$contents = file_get_contents( $file_path );

					// Replace namespace values if provided
					if ( $new_slug ) {
						$contents = Theme_Utils::replace_namespace( $contents, $old_slug, $new_slug, $old_name, $new_name );
					}

					// Add current file to archive
					$zip->addFromStringToTheme( $relative_path, $contents );
				}
			}
		}

		return $zip;
	}

	/**
	 * Add block templates and parts to the zip.
	 *
	 * @since    0.0.2
	 * @param    object               $zip          The zip archive to add the templates to.
	 * @param    string               $export_type  Determine the templates that should be exported.
	 *                      current = templates from currently activated theme (but not a parent theme if there is one) as well as user edited templates
	 *                      user = only user edited templates
	 *                      all = all templates no matter what
	 */
	public static function add_templates_to_zip( $zip, $export_type, $new_slug ) {

		$theme_templates  = Theme_Templates::get_theme_templates( $export_type );
		$template_folders = get_block_theme_folders();

		if ( $theme_templates->templates ) {
			$zip->addThemeDir( $template_folders['wp_template'] );
		}

		if ( $theme_templates->parts ) {
			$zip->addThemeDir( $template_folders['wp_template_part'] );
		}

		foreach ( $theme_templates->templates as $template ) {

			$template = Theme_Templates::prepare_template_for_export( $template );

			// Write the template content
			$zip->addFromStringToTheme(
				$template_folders['wp_template'] . DIRECTORY_SEPARATOR . $template->slug . '.html',
				$template->content
			);

			// Write the media assets if there are any
			if ( $template->media ) {
				self::add_media_to_zip( $zip, $template->media );
			}

			// Write the pattern if it exists
			if ( isset( $template->pattern ) ) {
				$zip->addFromStringToTheme(
					'patterns/' . $template->slug . '.php',
					$template->pattern
				);
			}
		}

		foreach ( $theme_templates->parts as $template ) {
			$template = Theme_Templates::prepare_template_for_export( $template );

			// Write the template content
			$zip->addFromStringToTheme(
				$template_folders['wp_template_part'] . DIRECTORY_SEPARATOR . $template->slug . '.html',
				$template->content
			);

			// Write the media assets if there are any
			if ( $template->media ) {
				self::add_media_to_zip( $zip, $template->media );
			}

			// Write the pattern if it exists
			if ( isset( $template->pattern ) ) {
				$zip->addFromStringToTheme(
					'patterns/' . $template->slug . '.php',
					$template->pattern
				);
			}
		}

		return $zip;
	}

	static function add_media_to_zip( $zip, $media ) {
		$media = array_unique( $media );
		foreach ( $media as $url ) {
			$folder_path   = Theme_Media::get_media_folder_path_from_url( $url );
			$download_file = download_url( $url );

			if ( is_wp_error( $download_file ) ) {
				//we're going to try again with a new URL
				//see, we might be running this in a docker container
				//and if that's the case let's try again on port 80
				$parsed_url = parse_url( $url );
				if ( 'localhost' === $parsed_url['host'] && '80' !== $parsed_url['port'] ) {
					$download_file = download_url( str_replace( 'localhost:' . $parsed_url['port'], 'localhost:80', $url ) );
				}
			}

			// If there was an error downloading the file, skip it.
			// TODO: Implement a warning if the file is missing
			if ( ! is_wp_error( $download_file ) ) {
				$content_array  = file( $download_file );
				$file_as_string = implode( '', $content_array );
				$zip->addFromStringToTheme( $folder_path . basename( $url ), $file_as_string );
			}
		}
	}

}
