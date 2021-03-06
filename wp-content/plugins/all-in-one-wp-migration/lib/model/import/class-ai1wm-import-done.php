<?php
/**
 * Copyright (C) 2014-2016 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

class Ai1wm_Import_Done {

	public static function execute( $params ) {

		// Set shutdown handler
		@register_shutdown_function( 'Ai1wm_Import_Done::shutdown' );

		// Check multisite.json file
		if ( true === is_file( ai1wm_multisite_path( $params ) ) ) {

			// Read multisite.json file
			$handle = fopen( ai1wm_multisite_path( $params ), 'r' );
			if ( $handle === false ) {
				throw new Ai1wm_Import_Exception( __( 'Unable to read multisite.json file', AI1WM_PLUGIN_NAME ) );
			}

			// Parse multisite.json file
			$multisite = fread( $handle, filesize( ai1wm_multisite_path( $params ) ) );
			$multisite = json_decode( $multisite );

			// Close handle
			fclose( $handle );

			// Activate plugins
			if ( isset( $multisite->Plugins ) && ( $active_sitewide_plugins = $multisite->Plugins ) ) {
				activate_plugins( $active_sitewide_plugins, null, is_multisite() );
			}
		}

		// Set the new MS files rewriting
		if ( get_site_option( AI1WM_MS_FILES_REWRITING ) ) {
			update_site_option( AI1WM_MS_FILES_REWRITING, 0 );
		}

		// Open the archive file for reading
		$archive = new Ai1wm_Extractor( ai1wm_archive_path( $params ) );

		// Unpack must-use plugins
		$archive->extract_by_files_array( WP_CONTENT_DIR, array( AI1WM_MUPLUGINS_NAME ) );

		// Close the archive file
		$archive->close();

		// Load must-use plugins
		foreach ( wp_get_mu_plugins() as $mu_plugin ) {
			include_once( $mu_plugin );
		}

		return $params;
	}

	public static function shutdown() {
		$error = error_get_last();

		// Set error type
		$type = null;
		if ( isset( $error['type'] ) ) {
			$type = $error['type'];
		}

		// Set error file
		$file = null;
		if ( isset( $error['file'] ) ) {
			$file = $error['file'];
		}

		// Deactivate must-use plugins on fatal and parse errors
		if ( in_array( $type, array( E_ERROR, E_PARSE ) ) && stripos( $file, AI1WM_MUPLUGINS_NAME ) !== false ) {
			foreach ( wp_get_mu_plugins() as $mu_plugin ) {
				if ( copy( $mu_plugin, sprintf( '%s-%s', $mu_plugin, date( 'YmdHis' ) ) ) ) {
					if ( ( $handle = fopen( $mu_plugin, 'w' ) ) ) {
						fclose( $handle );
					}
				}
			}
		}

		// Set progress
		Ai1wm_Status::done(
			sprintf(
				__(
					'You need to perform two more steps:<br />' .
					'<strong>1. You must save your permalinks structure twice. <a class="ai1wm-no-underline" href="%s" target="_blank">Permalinks Settings</a></strong> <small>(opens a new window)</small><br />' .
					'<strong>2. <a class="ai1wm-no-underline" href="https://wordpress.org/support/view/plugin-reviews/all-in-one-wp-migration?rate=5#postform" target="_blank">Optionally, review the plugin</a>.</strong> <small>(opens a new window)</small>',
					AI1WM_PLUGIN_NAME
				),
				admin_url( 'options-permalink.php#submit' )
			),
			__(
				'Your data has been imported successfuly!',
				AI1WM_PLUGIN_NAME
			)
		);
	}
}
