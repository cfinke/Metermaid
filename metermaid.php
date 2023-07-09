<?php

/*
Plugin Name: Metermaid
Description: Assists in tracking water usage for community wells.
Version: 1.0
Author: Christopher Finke
*/

class METERMAID {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_options_menu' ) );
	}

	public static function add_options_menu() {
		add_menu_page(
			'Metermaid',
			'Metermaid',
			'publish_posts',
			'metermaid',
			array( 'METERMAID', 'admin_page' ),
			'dashicons-welcome-write-blog',
			4
		);
		/*
		add_submenu_page(
			'metermaid',
			'Metermaid',
			'Metermaid',
			'publish_posts',
			'metermaid',
			array( 'METERMAID', 'admin_page' ),
			1
		);

		remove_submenu_page( 'metermaid','metermaid' );
		*/
	}

	public static function admin_page() {
		global $wpdb;

		?>
		<div class="wrap">
			<h2>Metermaid</h2>
		</div>
		<?php
	}
}

add_action( 'init', array( 'METERMAID', 'init' ) );
