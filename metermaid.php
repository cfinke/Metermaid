<?php

/*
Plugin Name: Metermaid
Description: A WordPress plugin to manage tracking water usage for community wells.
Version: 1.0
Author: Christopher Finke
*/

require_once __DIR__ . '/classes/class.system.php';
require_once __DIR__ . '/classes/class.meter.php';
require_once __DIR__ . '/classes/class.reading.php';
require_once __DIR__ . '/classes/class.sms.php';

define( 'METERMAID_STATUS_ACTIVE', 0 );
define( 'METERMAID_STATUS_INACTIVE', 1 );

define( 'METERMAID_DEFAULT_RATE_INTERVAL', 7 );

class METERMAID {
	public static $pending_notices = [];

	public static function init() {
		global $wpdb;
		global $pagenow;

		foreach ( $_POST as $key => $val ) {
			if ( strpos( $key, "metermaid" ) === 0 ) {
				$_POST[ $key ] = stripslashes_deep( $val );
			}
		}

		add_action( 'user_register', array( 'METERMAID', 'set_default_user_role' ), 10, 2 );

		add_filter( 'not_a_blog_default_page', function ( $url ) {
			return 'wp-admin/admin.php?page=metermaid-home';
		} );

		add_filter( 'user_has_cap', array( 'METERMAID', 'user_has_cap' ), 10, 4 );

		$role = get_role( 'administrator' );
		$role->add_cap( 'metermaid', true );

		$role->add_cap( 'metermaid-system-delete', true );

		$role->add_cap( 'metermaid-edit-settings', true );
		$role->add_cap( 'metermaid-add-system', true );
		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-edit-system', true );
		$role->add_cap( 'metermaid-add-meter', true );
		$role->add_cap( 'metermaid-edit-meter', true );
		$role->add_cap( 'metermaid-delete-meter', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-add-reading', true );
		$role->add_cap( 'metermaid-delete-reading', true );
		$role->add_cap( 'metermaid-add-supplement', true );
		$role->add_cap( 'metermaid-delete-supplement', true );
		$role->add_cap( 'metermaid-invite-system', true );
		$role->add_cap( 'metermaid-invite-meter', true );
		$role->add_cap( 'metermaid-manage-sms', true );

		add_role(
			'multisystem_manager',
			__( 'Metermaid: Multi-System Manager', 'metermaid' ),
			[
				'read' => true,

				'metermaid' => true,

				'metermaid-system-delete' => true,

				'metermaid-add-system' => true,
				'metermaid-access-system' => true,
				'metermaid-edit-system' => true,
				'metermaid-add-meter' => true,
				'metermaid-edit-meter' => true,
				'metermaid-delete-meter' => true,
				'metermaid-view-meter' => true,
				'metermaid-add-reading' => true,
				'metermaid-delete-reading' => true,
				'metermaid-add-supplement' => true,
				'metermaid-delete-supplement' => true,
				'metermaid-invite-system' => true,
				'metermaid-invite-meter' => true,
			]
		);

		$role = get_role( 'multisystem_manager' );
		$role->add_cap( 'read', true );
		$role->add_cap( 'metermaid', true );

		$role->add_cap( 'metermaid-system-delete', true );

		$role->add_cap( 'metermaid-add-system', true );
		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-edit-system', true );
		$role->add_cap( 'metermaid-add-meter', true );
		$role->add_cap( 'metermaid-edit-meter', true );
		$role->add_cap( 'metermaid-delete-meter', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-add-reading', true );
		$role->add_cap( 'metermaid-delete-reading', true );
		$role->add_cap( 'metermaid-add-supplement', true );
		$role->add_cap( 'metermaid-delete-supplement', true );
		$role->add_cap( 'metermaid-invite-system', true );
		$role->add_cap( 'metermaid-invite-meter', true );

		add_role(
			'system_manager',
			__( 'Metermaid: System Manager', 'metermaid' ),
			[
				'read' => true,

				'metermaid' => true,

				'metermaid-system-delete' => true,

				'metermaid-access-system' => true,
				'metermaid-edit-system' => true,
				'metermaid-add-meter' => true,
				'metermaid-edit-meter' => true,
				'metermaid-delete-meter' => true,
				'metermaid-view-meter' => true,
				'metermaid-add-reading' => true,
				'metermaid-delete-reading' => true,
				'metermaid-add-supplement' => true,
				'metermaid-delete-supplement' => true,
				'metermaid-invite-system' => true,
				'metermaid-invite-meter' => true,
			]
		);

		$role = get_role( 'system_manager' );
		$role->add_cap( 'read', true );
		$role->add_cap( 'metermaid', true );

		$role->add_cap( 'metermaid-system-delete', true );

		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-edit-system', true );
		$role->add_cap( 'metermaid-add-meter', true );
		$role->add_cap( 'metermaid-edit-meter', true );
		$role->add_cap( 'metermaid-delete-meter', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-add-reading', true );
		$role->add_cap( 'metermaid-delete-reading', true );
		$role->add_cap( 'metermaid-add-supplement', true );
		$role->add_cap( 'metermaid-delete-supplement', true );
		$role->add_cap( 'metermaid-invite-system', true );
		$role->add_cap( 'metermaid-invite-meter', true );

		add_role(
			'system_viewer',
			__( 'Metermaid: System Viewer', 'metermaid' ),
			[
				'read' => true,

				'metermaid' => true,
				'metermaid-access-system' => true,
				'metermaid-view-meter' => true,
				'metermaid-invite-system' => true,
				'metermaid-invite-meter' => true,
			]
		);

		$role = get_role( 'system_viewer' );
		$role->add_cap( 'read', true );
		$role->add_cap( 'metermaid', true );
		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-invite-system', true );
		$role->add_cap( 'metermaid-invite-meter', true );

		add_role(
			'meter_manager',
			__( 'Metermaid: Meter Manager', 'metermaid' ),
			[
				'read' => true,

				'metermaid' => true,
				'metermaid-access-system' => true,
				'metermaid-view-meter' => true,
				'metermaid-add-reading' => true,
				'metermaid-delete-reading' => true,
				'metermaid-add-supplement' => true,
				'metermaid-delete-supplement' => true,
				'metermaid-invite-meter' => true,
			]
		);

		$role = get_role( 'meter_manager' );
		$role->add_cap( 'read', true );
		$role->add_cap( 'metermaid', true );
		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-add-reading', true );
		$role->add_cap( 'metermaid-delete-reading', true );
		$role->add_cap( 'metermaid-add-supplement', true );
		$role->add_cap( 'metermaid-delete-supplement', true );
		$role->add_cap( 'metermaid-invite-meter', true );

		add_role(
			'meter_viewer',
			__( 'Metermaid: Meter Viewer', 'metermaid' ),
			[
				'read' => true,

				'metermaid' => true,
				'metermaid-access-system' => true,
				'metermaid-view-meter' => true,
				'metermaid-invite-meter' => true,
			]
		);

		$role = get_role( 'meter_viewer' );
		$role->add_cap( 'read', true );
		$role->add_cap( 'metermaid', true );
		$role->add_cap( 'metermaid-access-system', true );
		$role->add_cap( 'metermaid-view-meter', true );
		$role->add_cap( 'metermaid-invite-meter', true );

		$all_systems = self::systems();

		/**
		 * add_submenu_page() doesn't let us deep-link, so manage that redirection here.
		 */
		if ( count( $all_systems ) == 1 && isset( $_GET['page'] ) && 'metermaid-add-meter' === $_GET['page'] ) {
			wp_safe_redirect( site_url( 'wp-admin/admin.php?page=metermaid-home' ) . '#tab-add-meter' );
			exit;
		}

		if ( isset( $_GET['page'] ) && 'metermaid-add-system' === $_GET['page'] ) {
			wp_safe_redirect( site_url( 'wp-admin/admin.php?page=metermaid-home' ) . '#tab-add-system' );
			exit;
		}

		if ( isset( $_GET['page'] ) && 'metermaid-edit-profile' === $_GET['page'] ) {
			wp_safe_redirect( site_url( 'wp-admin/admin.php?page=metermaid-home' ) . '#tab-profile' );
			exit;
		}

		if ( isset( $_GET['metermaid_meter_id'] ) && ! isset( $_GET['metermaid_system_id'] ) ) {
			$meter = new METERMAID_METER( $_GET['metermaid_meter_id'] );
			$_GET['metermaid_system_id'] = $meter->system_id;
		}

		if ( ! in_array( 'administrator', wp_get_current_user()->roles ) ) {
			if ( in_array(
				$pagenow,
				array( 'profile.php', )
			) ) {
				wp_safe_redirect( site_url( 'wp-admin/admin.php?page=metermaid-home#tab-profile' ) );

				exit;
			}

			if ( in_array(
				$pagenow,
				array( 'about.php', 'index.php', )
			) ) {
				wp_safe_redirect( site_url( 'wp-admin/admin.php?page=metermaid-home' ) );

				exit;
			}
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_options_menu' ) );

		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'metermaid' ) !== false ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		}

		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_enqueue_scripts' ) );

		add_action( 'admin_title', array( __CLASS__, 'edit_page_title' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		METERMAID::process_form_submissions();
	}

	public static function set_default_user_role( $user_id, $userdata ) {
		// If someone signs up with no invites, make them a sytem manager.
		// If someone has an invite to manage a system, make them a system manager.
		// If someone has an invite to view a system, make them a system viewer.
		// If someone has an invite to manage a meter, make them a meter manager.
		// If someone has an invite to view a meter, make them a meter viewer.

		global $wpdb;

		$user = new WP_User( $user_id );

		if ( ! $user ) {
			return;
		}

		$email_address = $user->user_email;

		$invites = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . $wpdb->prefix . "metermaid_personnel WHERE email=%s",
			$email_address
		) );

		if ( empty( $invites ) ) {
			// Do nothing and leave the signup with the default role of system manager.
		} else {
			$priority = array(
				'meter_viewer' => 1,
				'meter_manager' => 2,
				'system_viewer' => 3,
				'system_manager' => 4,
			);

			$top_role = 'meter_viewer';

			foreach ( $invites as $invite ) {
				if ( $invite->metermaid_meter_id ) {
					if ( $invite->manage ) {
						if ( $priority['meter_manager'] > $priority[ $top_role ] ) {
							$top_role = 'meter_manager';
						}
					} else {
						if ( $priority['meter_viewer'] > $priority[ $top_role ] ) {
							$top_role = 'meter_viewer';
						}
					}
				} else if ( $invite->metermaid_system_id ) {
					if ( $invite->manage ) {
						if ( $priority['system_manager'] > $priority[ $top_role ] ) {
							$top_role = 'system_manager';
						}
					} else {
						if ( $priority['system_viewer'] > $priority[ $top_role ] ) {
							$top_role = 'system_viewer';
						}
					}
				}
			}

			$user->set_role( $top_role );

			if ( count( $invites ) == 1 ) {
				if ( $top_role == 'meter_manager' || $top_role == 'meter_viewer' ) {
					update_user_meta( $user_id, 'metermaid_meter_id', $invites[0]->metermaid_meter_id );
				}
			}
		}
	}

	public static function user_has_cap( $allcaps, $caps, $args, $user ) {
		global $wpdb;

		if ( ! in_array( 'administrator', $user->roles ) && strpos( $args[0], 'metermaid-' ) === 0 ) {
			$cap_to_check = $args[0];

			if ( count( $args ) > 2 ) {
				// This is related to a specific access issue.

				// First confirm that they generally have the right to take this action.
				if ( isset( $allcaps[ $cap_to_check ] ) && $allcaps[ $cap_to_check ] ) {
					// Now confirm that they have the right to take it on this specific system/meter/etc.

					// System-level actions
					if ( in_array(
							$cap_to_check,
							array(
								'metermaid-access-system',
								'metermaid-edit-system',
								'metermaid-add-meter',
							),
							true )
						) {
						$system_id = $args[2];

						// Check if this user is listed as personnel on this system.
						$row = $wpdb->get_row( $wpdb->prepare(
							"SELECT *
							FROM " . $wpdb->prefix . "metermaid_personnel
							WHERE email=%s
								AND metermaid_system_id=%d
							LIMIT 1",
							$user->user_email,
							$system_id
						) );

						if ( ! $row ) {
							unset( $allcaps[ $cap_to_check ] );
						}

					// Meter-level-actions
					} else if ( in_array(
							$cap_to_check,
							array(
								'metermaid-add-reading',
								'metermaid-view-meter',
								'metermaid-delete-meter',
								'metermaid-edit-meter',
							),
							true )
						) {
						$meter_id = $args[2];

						$meter = new METERMAID_METER( $meter_id );

						$system_id = $meter->system_id;

						$row = $wpdb->get_row( $wpdb->prepare(
							"SELECT * FROM " . $wpdb->prefix . "metermaid_personnel
							WHERE email=%s
								AND metermaid_system_id=%d
								AND ( metermaid_meter_id=0 OR metermaid_meter_id=%d )
							LIMIT 1",
							$user->user_email,
							$system_id,
							$meter_id
						) );

						if ( ! $row ) {
							unset( $allcaps[ $cap_to_check ] );
						}
					} else if ( 'metermaid-delete-reading' == $cap_to_check ) {
						$reading_id = $args[2];

						$reading = new METERMAID_READING( $reading_id );

						if ( $reading() && ( $reading->added_by == get_current_user_id() ) ) {
							// Yes, they can delete it, since they added it.
						} else {
							if ( $reading() && current_user_can( 'metermaid-edit-system', $reading->meter->system_id ) ) {
								// Yes, they can delete it, since they manage the system.
							} else {
								unset( $allcaps[ $cap_to_check ] );
							}
						}
					} else if ( 'metermaid-system-delete' == $cap_to_check ) {
						// They can only delete a system that they added.

						$system_id = $args[2];

						$system = new METERMAID_SYSTEM( $system_id );

						if ( ! $system() || $system->added_by != get_current_user_id() ) {
							unset( $allcaps[ $cap_to_check ] );
						}
					}
				}
			}
		}

		return $allcaps;
	}

	public static function edit_page_title() {
		global $title;

		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'metermaid' ) !== false ) {
			if ( isset( $_GET['metermaid_meter_id'] ) ) {
				$meter = new METERMAID_METER( $_GET['metermaid_meter_id'] );

				if ( $meter() ) {
					$title = 'Metermaid &raquo; ' . $meter->name;
				}
			} else if ( isset( $_GET['metermaid_system_id'] ) ) {
				$system = new METERMAID_SYSTEM( $_GET['metermaid_system_id'] );

				if ( $system() ) {
					$title = 'Metermaid &raquo; ' . $system->name;
				}
			} else {
				$title = 'Metermaid';
			}
		}

		return $title;
	}

	public static function db_setup() {
		METERMAID::sql();
	}

	public static function sql() {
		global $wpdb;

		$METERMAID_DB_SCHEMA_VERSION = 2;

		$existing_schema_version = get_option( 'metermaid_db_schema_version', 0 );

		if ( $METERMAID_DB_SCHEMA_VERSION != $existing_schema_version ) {
			switch ( $existing_schema_version ) {
				case 0:
					$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_systems
						(
							metermaid_system_id bigint NOT NULL AUTO_INCREMENT,
							name varchar(100) NOT NULL,
							unit varchar(32) NOT NULL,
							rate_interval int NOT NULL,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_system_id),
							INDEX name (name)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
					);

					$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_meters
						(
							metermaid_meter_id bigint NOT NULL AUTO_INCREMENT,
							metermaid_system_id bigint NOT NULL,
							name varchar(100) NOT NULL,
							status int NOT NULL,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_meter_id),
							INDEX name (name)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
					);

					$wpdb->query( "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "metermaid_relationships
						(
							metermaid_relationship_id bigint NOT NULL AUTO_INCREMENT,
							parent_meter_id bigint NOT NULL,
							child_meter_id bigint NOT NULL,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_relationship_id),
							UNIQUE KEY relationship (parent_meter_id,child_meter_id),
							KEY child_meter_id (child_meter_id)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
					);

					$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_readings
						(
							metermaid_reading_id bigint NOT NULL AUTO_INCREMENT,
							metermaid_meter_id bigint NOT NULL,
							reading int NOT NULL,
							real_reading int NOT NULL,
							reading_date date NOT NULL,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_reading_id),
							INDEX metermaid_meter_id (metermaid_meter_id),
							UNIQUE KEY reading_date (reading_date, metermaid_meter_id)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
					);

					$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_supplements
						(
							metermaid_supplement_id bigint NOT NULL AUTO_INCREMENT,
							metermaid_meter_id bigint NOT NULL,
							amount int NOT NULL,
							supplement_date date NOT NULL,
							note TEXT,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_supplement_id),
							INDEX metermaid_meter_id (metermaid_meter_id),
							UNIQUE KEY supplement_date (supplement_date, metermaid_meter_id)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
					);

					$wpdb->query( "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "metermaid_personnel
						(
							metermaid_personnel_id bigint NOT NULL AUTO_INCREMENT,
							email varchar(100) NOT NULL,
							metermaid_system_id bigint NOT NULL,
							metermaid_meter_id bigint NOT NULL,
							added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
							added_by bigint NOT NULL,
							PRIMARY KEY (metermaid_personnel_id),
							INDEX email (email),
							INDEX metermaid_system_and_meter (metermaid_system_id, metermaid_meter_id),
							INDEX added (added)
						) ENGINE=InnoDB DEFAULT CHARSET utf8mb4"
					);
				case 1:
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "metermaid_relationships ADD metermaid_system_id BIGINT NOT NULL AFTER metermaid_relationship_id" );
					$wpdb->query( "CREATE INDEX metermaid_system_id ON " . $wpdb->prefix . "metermaid_relationships (metermaid_system_id)" );

					// Add the system ID to any existing rows.
					$existing_rows = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . "metermaid_relationships WHERE metermaid_system_id=0 GROUP BY parent_meter_id" );

					foreach ( $existing_rows as $row ) {
						$meter = new METERMAID_METER( $row->parent_meter_id );

						if ( ! $meter() ) {
							error_log( "Missing meter: " . $row->metermaid_meter_id );
						} else {
							$wpdb->query( $wpdb->prepare(
								"UPDATE " . $wpdb->prefix . "metermaid_relationships SET metermaid_system_id=%d WHERE parent_meter_id=%d",
								$meter->system_id,
								$meter->id
							) );
						}
					}
				break;
			}

			update_option( 'metermaid_db_schema_version', $METERMAID_DB_SCHEMA_VERSION );
		}
	}

	public static function add_options_menu() {
		add_menu_page(
			'Metermaid',                        // Page title
			'Metermaid',                        // Menu title
			'metermaid',                        // capability
			'metermaid',	                    // menu slug
			array( 'METERMAID', 'admin_page' ), // Callback
			plugins_url( 'metermaid/images/admin-menu-icon.png' ),
			4                                   // Position
		);

		add_submenu_page(
			'metermaid',
			__( 'Metermaid Dashboard', 'metermaid' ),
			__( 'Dashboard', 'metermaid' ),
			'metermaid',
			'metermaid-home',
			array( 'METERMAID', 'admin_page' ),
			1
		);

		if ( current_user_can( 'metermaid-add-system' ) ) {
			add_submenu_page(
				'metermaid',
				__( 'Add System', 'metermaid' ),
				__( 'Add System', 'metermaid' ),
				'metermaid-add-system',
				'metermaid-add-system',
				array( 'METERMAID', 'admin_page' ),
				2
			);
		}

		$systems = METERMAID::systems();

		if (
			count( $systems ) == 1 &&
			current_user_can( 'metermaid-add-meter', $systems[0]->id )
		) {
			add_submenu_page(
				'metermaid',
				__( 'Add Meter', 'metermaid' ),
				__( 'Add Meter', 'metermaid' ),
				'metermaid-add-meter',
				'metermaid-add-meter',
				array( 'METERMAID', 'admin_page' ),
				3
			);
		}

		add_submenu_page(
			'metermaid',
			__( 'Edit Profile', 'metermaid' ),
			__( 'Edit Profile', 'metermaid' ),
			'metermaid',
			'metermaid-edit-profile',
			array( 'METERMAID', 'admin_page' ),
			4
		);
		// Remove the auto-generated "Metermaid" submenu item.
		remove_submenu_page( 'metermaid', 'metermaid' );
	}

	public static function enqueue_scripts() {
		wp_enqueue_script( 'metermaid-google-charts', 'https://www.gstatic.com/charts/loader.js' );

		wp_register_style( 'metermaid-css', plugin_dir_url( __FILE__ ) . '/css/metermaid.css', array(), time() );
		wp_enqueue_style( 'metermaid-css' );

		wp_register_script( 'metermaid-admin.js', plugin_dir_url( __FILE__ ) . '/js/metermaid-admin.js', array( 'jquery' ), time() );

		$metermaid_i18n = array(
			'meter_delete_confirm' => __( 'Are you sure you want to delete this meter?', 'metermaid' ),
			'reading_delete_confirm' => __( 'Are you sure you want to delete this reading?', 'metermaid' ),
			'supplement_delete_confirm' => __( 'Are you sure you want to delete this supplement?', 'metermaid' ),
			'system_delete_confirm' => __( 'Are you sure you want to delete this system? All meters and readings in it will be deleted as well.', 'metermaid' ),
		);

		wp_localize_script( 'metermaid-admin.js', 'metermaid_i18n', $metermaid_i18n );

		wp_enqueue_script( 'metermaid-admin.js' );
	}

	public static function login_enqueue_scripts() {
		wp_register_style( 'metermaid-login-css', plugin_dir_url( __FILE__ ) . '/css/metermaid-login.css', array(), time() );
		wp_enqueue_style( 'metermaid-login-css' );
	}

	public static function process_form_submissions() {
		global $wpdb;

		if ( isset( $_POST['metermaid_action'] ) ) {
			if ( 'edit_settings' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-edit-settings' ) ) {
					echo 'You are not authorized to edit the settings.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-edit-settings' ) ) {
					echo 'You are not authorized to add these settings.';
					wp_die();
				}

				update_option( 'METERMAID_TWILIO_PHONE_NUMBER', $_POST['metermaid_twilio_phone_number'] );
				update_option( 'METERMAID_TWILIO_ACCOUNT_SID', $_POST['metermaid_twilio_account_sid'] );
				update_option( 'METERMAID_TWILIO_AUTH_TOKEN', $_POST['metermaid_twilio_auth_token'] );
				update_option( 'METERMAID_TWILIO_MESSAGING_SERVICE_SID', $_POST['metermaid_twilio_messaging_service_sid'] );

				METERMAID::save_pending_notice( 'success', __( 'The settings have been saved.', 'metermaid' ) );
			} else if ( 'edit_profile' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-edit-profile' ) ) {
					echo 'You are not authorized to edit a profile.';
					wp_die();
				}

				update_user_meta( get_current_user_id(), 'nickname', $_POST['metermaid_profile_name'] );
				update_user_meta( get_current_user_id(), 'metermaid_phone_number', METERMAID_SMS::standardize_phone_number( $_POST['metermaid_profile_phone'] ) );

				if ( ! empty( $_POST['metermaid_profile_meter'] ) ) {
					if ( current_user_can( 'metermaid-add-reading', $_POST['metermaid_profile_meter'] ) ) {
						update_user_meta( get_current_user_id(), 'metermaid_meter_id', $_POST['metermaid_profile_meter'] );
					}
				}

				METERMAID::save_pending_notice( 'success', __( 'The profile has been updated.', 'metermaid' ) );
			} else if ( 'add_system' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-add-system' ) ) {
					echo 'You are not authorized to add a system.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-add-system' ) ) {
					echo 'You are not authorized to add this system.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_systems SET name=%s, unit=%s, rate_interval=%d, added=NOW(), added_by=%d",
					$_POST['metermaid_system_name'],
					$_POST['metermaid_system_unit'],
					$_POST['metermaid_system_rate_interval'],
					get_current_user_id()
				) );

				$system_id = $wpdb->insert_id;
				$_GET['metermaid_system_id'] = $system_id;

				METERMAID::save_pending_notice( 'success', __( 'The system has been added.', 'metermaid' ) );
			} else if ( 'add_reading' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-add-reading' ) ) {
					echo 'You are not authorized to add a reading.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-add-reading', $_POST['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to add a reading for this meter.';
					wp_die();
				}

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );
				$success = $meter->add_reading( $_POST['metermaid_reading'], $_POST['metermaid_reading_date'] );

				if ( $success ) {
					METERMAID::save_pending_notice( 'success', __( 'The reading has been added.', 'metermaid' ) );
				} else {
					METERMAID::save_pending_notice( 'error', __( 'The reading could not be added because another person already saved a reading for that date.', 'metermaid' ) );
				}
			} else if ( 'add_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-add-meter' ) ) {
					echo 'You are not authorized to add a meter.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-add-meter', $_POST['metermaid_system_id'] ) ) {
					echo 'You are not authorized to add a meter to this system.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_meters SET metermaid_system_id=%d, name=%s, contact_name=%s, contact_email=%s, contact_phone=%s, added=NOW(), added_by=%d",
					$_POST['metermaid_system_id'],
					$_POST['metermaid_meter_name'],
					$_POST['metermaid_meter_contact_name'],
					$_POST['metermaid_meter_contact_email'],
					METERMAID_SMS::standardize_phone_number( $_POST['metermaid_meter_contact_phone'] ),
					get_current_user_id()
				) );

				wp_cache_delete( $_POST['metermaid_system_id'], 'metermaid-meters' );

				$meter_id = $wpdb->insert_id;

				if ( ! empty( $_POST['metermaid_parent_meters'] ) ) {
					foreach ( array_filter( $_POST['metermaid_parent_meters'] ) as $parent_meter_id ) {
						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET metermaid_system_id=%s, parent_meter_id=%s, child_meter_id=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE parent_meter_id=VALUES(parent_meter_id)",
							$_POST['metermaid_system_id'],
							$parent_meter_id,
							$meter_id,
							get_current_user_id()
						) );
					}
				}

				if ( ! empty( $_POST['metermaid_child_meters'] ) ) {
					foreach ( array_filter( $_POST['metermaid_child_meters'] ) as $child_meter_id ) {
						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET metermaid_system_id=%s, child_meter_id=%s, parent_meter_id=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE child_meter_id=VALUES(child_meter_id)",
							$_POST['metermaid_system_id'],
							$child_meter_id,
							$meter_id,
							get_current_user_id()
						) );
					}
				}

				wp_cache_delete( $_POST['metermaid_system_id'], 'metermaid-relationships' );

				METERMAID::save_pending_notice( 'success', __( 'The meter has been added.', 'metermaid' ) );
			} else if ( 'delete_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-delete-meter' ) ) {
					echo 'You are not authorized to delete a meter.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-delete-meter', $_POST['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to delete this meter.';
					wp_die();
				}

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );

				if ( ! $meter() ) {
					wp_die( 'Invalid meter ID.' );
				}

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_meters WHERE metermaid_meter_id=%s LIMIT 1",
					$_POST['metermaid_meter_id'],
				) );

				wp_cache_delete( $_POST['metermaid_meter_id'], 'metermaid-meter' );
				wp_cache_delete( $meter->system_id, 'metermaid-meters' );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_meter_id=%s",
					$_POST['metermaid_meter_id'],
				) );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_relationships WHERE parent_meter_id=%s OR child_meter_id=%s",
					$_POST['metermaid_meter_id'],
					$_POST['metermaid_meter_id']
				) );

				wp_cache_delete( $meter->system_id, 'metermaid-relationships' );

				/* // Is there value in keeping around the personnel entries?
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_personnel WHERE metermaid_meter_id=%s",
					$_POST['metermaid_meter_id']
				) );
				*/

				METERMAID::save_pending_notice( 'success', __( 'The meter has been deleted.', 'metermaid' ) );
			} else if ( 'edit_system' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-edit-system' ) ) {
					echo 'You are not authorized to edit a system.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-edit-system', $_POST['metermaid_system_id'] ) ) {
					echo 'You are not authorized to edit this system.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"UPDATE " . $wpdb->prefix . "metermaid_systems SET name=%s, unit=%s, rate_interval=%d WHERE metermaid_system_id=%d LIMIT 1",
					$_POST['metermaid_system_name'],
					$_POST['metermaid_system_unit'],
					$_POST['metermaid_system_rate_interval'],
					$_POST['metermaid_system_id']
				) );

				wp_cache_delete( $_POST['metermaid_system_id'], 'metermaid-system' );

				METERMAID::save_pending_notice( 'success', __( 'The system has been updated.', 'metermaid' ) );
			} if ( 'delete_reading' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-delete-reading' ) ) {
					echo 'You are not authorized to delete a reading.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-delete-reading', $_POST['metermaid_reading_id'] ) ) {
					echo 'You are not authorized to delete this reading.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_reading_id=%s LIMIT 1",
					$_POST['metermaid_reading_id'],
				) );

				$meter = new METERMAID_METER( $_GET['metermaid_meter_id'] );
				$meter->recalculate_real_readings();

				METERMAID::save_pending_notice( 'success', __( 'The reading has been deleted.', 'metermaid' ) );
			} else if ( 'delete_supplement' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-delete-supplement' ) ) {
					echo 'You are not authorized to delete a supplement.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-delete-supplement', $_GET['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to delete a supplement for this meter.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_supplements WHERE metermaid_supplement_id=%s LIMIT 1",
					$_POST['supplement_id'],
				) );

				METERMAID::save_pending_notice( 'success', __( 'The supplement has been deleted.', 'metermaid' ) );
			} else if ( 'add_supplement' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-add-supplement' ) ) {
					echo 'You are not authorized to add a supplement.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-add-supplement', $_GET['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to add a supplement for this meter.';
					wp_die();
				}

				$amount_int = intval( str_replace( ',', '', $_POST['metermaid_supplement_amount'] ) );

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_supplements SET metermaid_meter_id=%s, amount=%d, supplement_date=%s, note=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE amount=VALUES(amount)",
					$_POST['metermaid_meter_id'],
					$amount_int,
					$_POST['metermaid_supplement_date'],
					$_POST['metermaid_supplement_note'],
					get_current_user_id()
				) );

				METERMAID::save_pending_notice( 'success', __( 'The suppplement has been added.', 'metermaid' ) );
			} else if ( 'edit_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-edit-meter' ) ) {
					echo 'You are not authorized to edit a meter.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-edit-meter', $_POST['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to edit this meter.';
					wp_die();
				}

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );

				if ( ! $meter() ) {
					wp_die( 'Invalid meter ID.' );
				}

				$wpdb->query( $wpdb->prepare(
					"UPDATE " . $wpdb->prefix . "metermaid_meters SET name=%s, status=%d, contact_name=%s, contact_email=%s, contact_phone=%s WHERE metermaid_meter_id=%d LIMIT 1",
					$_POST['metermaid_meter_name'],
					$_POST['metermaid_meter_status'],
					$_POST['metermaid_meter_contact_name'],
					$_POST['metermaid_meter_contact_email'],
					$_POST['metermaid_meter_contact_phone'],
					$_POST['metermaid_meter_id']
				) );

				wp_cache_delete( $_POST['metermaid_meter_id'], 'metermaid-meter' );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM ".$wpdb->prefix."metermaid_relationships WHERE parent_meter_id=%d OR parent_meter_id=%d",
					$_POST['metermaid_meter_id'],
					$_POST['metermaid_meter_id']
				) );

				if ( ! empty( $_POST['metermaid_parent_meters'] ) ) {
					foreach ( array_filter( $_POST['metermaid_parent_meters'] ) as $parent_meter_id ) {
						if ( $parent_meter_id == $_POST['metermaid_meter_id'] ) {
							// A meter can't be a parent of itself.
							continue;
						}

						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET metermaid_system_id=%s, parent_meter_id=%s, child_meter_id=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE parent_meter_id=VALUES(parent_meter_id)",
							$meter->system_id,
							$parent_meter_id,
							$_POST['metermaid_meter_id'],
							get_current_user_id()
						) );
					}
				}

				if ( ! empty( $_POST['metermaid_child_meters'] ) ) {
					foreach ( array_filter( $_POST['metermaid_child_meters'] ) as $child_meter_id ) {
						if ( $child_meter_id == $_POST['metermaid_meter_id'] ) {
							// A meter can't be a child of itself.
							continue;
						}

						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET metermaid_system_id=%s, child_meter_id=%s, parent_meter_id=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE child_meter_id=VALUES(child_meter_id)",
							$meter->system_id,
							$child_meter_id,
							$_POST['metermaid_meter_id'],
							get_current_user_id()
						) );
					}
				}

				wp_cache_delete( $meter->system_id, 'metermaid-relationships' );

				METERMAID::save_pending_notice( 'success', __( 'The meter has been updated.', 'metermaid' ) );
			} else if ( 'invite' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-invite' ) ) {
					echo 'You are not authorized to invite anyone.';
					wp_die();
				}

				$system = new METERMAID_SYSTEM( $_POST['metermaid_invite_system_id'] );

				if ( ! $system() ) {
					echo 'Invalid system ID.';
					wp_die();
				}

				// Confirm it's a valid email.
				// @todo

				$meter_id_to_insert = $_POST['metermaid_invite_meter_id'];
				$manage_value = 0;

				$new_user_email_subject = __( 'You have been invited to use Metermaid', 'metermaid' );
				$existing_user_email_subject = __( 'You have been given more access on Metermaid', 'metermaid' );

				$new_user_email_body = __( 'Hello!', 'metermaid' );
				$new_user_email_body .= "\n\n";
				$new_user_email_body .= sprintf(
					__( '%s has invited you to use Metermaid, an online tool for managing water meters and meter readings.', 'metermaid' ),
					get_user_meta( wp_get_current_user()->ID, 'nickname', true )
				);
				$new_user_email_body .= "\n\n";

				$existing_user_email_body = __( 'Hello!', 'metermaid' );
				$existing_user_email_body .= "\n\n";

				// Confirm this user is allowed to do what they're doing and prep the data for saving.
				if ( $_POST['metermaid_invite_access_level'] == 'system' ) {
					if ( ! current_user_can( 'metermaid-invite-system', $_POST['metermaid_invite_system_id'] ) ) {
						echo 'You are not authorized to invite anyone to this meter.';
						wp_die();
					}

					if ( $_POST['metermaid_invite_manage_system'] == '1' ) {
						if ( ! current_user_can( 'metermaid-edit-system', $_POST['metermaid_invite_system_id'] ) ) {
							echo 'You are not authorized to invite anyone to make changes to this system, since you do not have that authority yourself.';
							wp_die();
						}

						$manage_value = 1;

						$new_user_email_body .= sprintf( __( "You've been given access to manage this system: %s", 'metermaid' ), $system->name );
						$new_user_email_body .= "\n\n";

						$existing_user_email_body .= sprintf(
							__( '%1$s has invited you to manage this system on Metermaid: %1$s.', 'metermaid' ),
							get_user_meta( wp_get_current_user()->ID, 'nickname', true ),
							$system->name
						);
						$existing_user_email_body .= "\n\n";

					} else {
						$new_user_email_body .= sprintf( __( "You've been given access to view this system: %s", 'metermaid' ), $system->name );
						$new_user_email_body .= "\n\n";

						$existing_user_email_body .= sprintf(
							__( '%1$s has invited you to view this system on Metermaid: %1$s.', 'metermaid' ),
							get_user_meta( wp_get_current_user()->ID, 'nickname', true ),
							$system->name
						);
						$existing_user_email_body .= "\n\n";
					}

					$meter_id_to_insert = 0;
				} else if ( $_POST['metermaid_invite_access_level'] == 'meter' ) {
					$meter = new METERMAID_METER( $_POST['metermaid_invite_meter_id'] );

					if ( ! $meter() ) {
						echo 'Invalid meter ID.';
						wp_die();
					}

					if ( ! current_user_can( 'metermaid-invite-meter', $_POST['metermaid_invite_meter_id'] ) ) {
						echo 'You are not authorized to invite anyone to this meter.';
						wp_die();
					}

					if ( $_POST['metermaid_invite_manage_meter'] == '1' ) {
						if ( ! current_user_can( 'metermaid-add-reading', $_POST['metermaid_invite_meter_id'] ) ) {
							echo 'You are not authorized to invite anyone to make changes to this meter, since you do not have that authority yourself.';
							wp_die();
						}

						$new_user_email_body .= sprintf( __( "You've been given access to manage this meter: %s", 'metermaid' ), $meter->name );
						$new_user_email_body .= "\n\n";

						$existing_user_email_body .= sprintf(
							__( '%1$s has invited you to manage this meter on Metermaid: %1$s.', 'metermaid' ),
							get_user_meta( wp_get_current_user()->ID, 'nickname', true ),
							$meter->name
						);
						$existing_user_email_body .= "\n\n";

						$manage_value = 1;
					} else {
						$new_user_email_body .= sprintf( __( "You've been given access to view this meter: %s", 'metermaid' ), $meter->name );
						$new_user_email_body .= "\n\n";

						$existing_user_email_body .= sprintf(
							__( '%1$s has invited you to view this meter on Metermaid: %1$s.', 'metermaid' ),
							get_user_meta( wp_get_current_user()->ID, 'nickname', true ),
							$meter->name
						);
						$existing_user_email_body .= "\n\n";
					}
				}

				$new_user_email_body .= sprintf(
					__( 'Visit %1$s to register for free using the address that this email was sent to (%2$s).', 'metermaid' ),
					site_url( 'wp-login.php?action=register' ),
					$_POST['metermaid_invite_email']
				);
				$new_user_email_body .= "\n\n";
				$new_user_email_body .= __( 'If you have any questions, email me at help@metermaid.org!' );
				$new_user_email_body .= "\n\n";
				$new_user_email_body .= 'Chris at Metermaid';

				$existing_user_email_body .= sprintf(
					__( 'Visit %1$s to and log in using the address that this email was sent to (%2$s).', 'metermaid' ),
					site_url(),
					$_POST['metermaid_invite_email']
				);
				$existing_user_email_body .= "\n\n";
				$existing_user_email_body .= __( 'If you have any questions, email me at help@metermaid.org!' );
				$existing_user_email_body .= "\n\n";
				$existing_user_email_body .= 'Chris at Metermaid';

				$existing_user = get_user_by( 'email', $_POST['metermaid_invite_email'] );

				if ( $existing_user ) {
					wp_mail( $_POST['metermaid_invite_email'], $existing_user_email_subject, $existing_user_email_body, array( 'Bcc: help@metermaid.org' ) );
				} else {
					wp_mail( $_POST['metermaid_invite_email'], $new_user_email_subject, $new_user_email_body, array( 'Bcc: help@metermaid.org' ) );
				}

				$wpdb->query( $wpdb->prepare( "INSERT INTO " . $wpdb->prefix . "metermaid_personnel SET
					email=%s,
					metermaid_system_id=%d,
					metermaid_meter_id=%d,
					manage=%d,
					added=NOW(),
					added_by=%d ON DUPLICATE KEY UPDATE added=NOW()",
					$_POST['metermaid_invite_email'],
					$_POST['metermaid_invite_system_id'],
					$meter_id_to_insert,
					$manage_value,
					get_current_user_id()
				) );

				METERMAID::save_pending_notice( 'success', sprintf( __( 'Invite sent to %s', 'metermaid' ), $_POST['metermaid_invite_email'] ) );
			} else if ( 'delete_system' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-delete-system' ) ) {
					echo 'You are not authorized to delete a system.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-system-delete', $_POST['metermaid_system_id'] ) ) {
					echo 'You are not authorized to delete this system.';
					wp_die();
				}

				$system = new METERMAID_SYSTEM( $_POST['metermaid_system_id'] );

				if ( ! $system() ) {
					echo 'Invalid system ID.';
					wp_die();
				}

				$meter_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT metermaid_meter_id
					FROM " . $wpdb->prefix . "metermaid_meters
					WHERE metermaid_system_id=%d",
					$_POST['metermaid_system_id']
				) );

				// Delete any parent/child relationships.
				$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_relationships WHERE metermaid_system_id=%d", $system->id ) );

				foreach ( $meter_ids as $meter_id ) {
					// Delete all readings.
					$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_meter_id=%d", $meter_id ) );

					// Delete any supplements.
					$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_supplements WHERE parent_meter_id=%d OR child_meter_id=%d", $meter_id, $meter_id ) );

					// Delete the meter.
					$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_meters WHERE metermaid_meter_id=%d", $meter_id ) );

					wp_cache_delete( $meter_id, 'metermaid-meter' );
					wp_cache_delete( $system->id, 'metermaid-meters' );
				}

				// Delete any personnel.
				$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_personnel WHERE metermaid_system_id=%d", $_POST['metermaid_system_id'] ) );

				// Delete the system entry.
				$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "metermaid_systems WHERE metermaid_system_id=%d LIMIT 1", $_POST['metermaid_system_id'] ) );

				wp_cache_delete( $_POST['metermaid_system_id'], 'metermaid-system' );
				wp_cache_delete( $_POST['metermaid_system_id'], 'metermaid-relationships' );

				METERMAID::save_pending_notice( 'success', __( 'The system was deleted.', 'metermaid' ) );
			} else if ( 'export_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-export-meter' ) ) {
					echo 'You are not authorized to export a meter.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-view-meter', $_POST['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to export this meter.';
					wp_die();
				}

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );

				if ( ! $meter() ) {
					wp_die( 'Invalid meter ID.' );
				}

				$readings = $meter->readings();

				// Ascending order for the export.
				$readings = array_reverse( $readings );

				header( "Content-Type: text/csv" );
				header( "Content-Disposition: attachment; filename=metermaid-meter-" . $_POST['metermaid_meter_id'] . ".csv" );

				echo "Date,Reading\n";

				foreach ( $readings as $reading ) {
					echo $reading->reading_date . "," . $reading->reading . "\n";
				}

				exit;
			} else if ( 'export_meters' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-export-meters' ) ) {
					echo 'You are not authorized to export meters from this system.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-access-system', $_POST['metermaid_system_id'] ) ) {
					echo 'You are not authorized to access this sytem.';
					wp_die();
				}

				$system = new METERMAID_SYSTEM( $_POST['metermaid_system_id'] );

				if ( ! $system() ) {
					wp_die( 'Invalid system ID.' );
				}

				$meters = $system->readable_meters;

				header( "Content-Type: text/csv" );
				header( "Content-Disposition: attachment; filename=metermaid-meters-" . $_POST['metermaid_system_id'] . ".csv" );

				echo "Date";

				foreach ( $meters as $meter ) {
					echo "," . '"' . str_replace( '"', '""', $meter->name ) . '"';
				}

				echo "\n";

				$readings_by_date = array();

				foreach ( $meters as $idx => $meter ) {
					foreach ( $meter->readings() as $reading ) {
						if ( ! isset( $readings_by_date[ $reading->reading_date ] ) ) {
							$readings_by_date[ $reading->reading_date ] = array_fill( 0, count( $meters ), null );
						}

						$readings_by_date[ $reading->reading_date ][ $idx ] = $reading->reading;
					}
				}

				ksort( $readings_by_date );

				foreach ( $readings_by_date as $date => $reading_values ) {
					echo $date . "," . join( ",", $reading_values ) . "\n";
				}

				exit;

			} else if ( 'import_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['metermaid_nonce'], 'metermaid-import-meter' ) ) {
					echo 'You are not authorized to import readings.';
					wp_die();
				}

				if ( ! current_user_can( 'metermaid-add-reading', $_POST['metermaid_meter_id'] ) ) {
					echo 'You are not authorized to add readings for this meter.';
					wp_die();
				}

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );

				if ( ! $meter() ) {
					wp_die( 'Invalid meter ID.' );
				}

				$csv = $_FILES['metermaid_import_file'];

				if ( ! empty( $csv['error'] ) ) {
					$upload_error_strings = array(
						1 => __( 'The CSV file is larger than the maximum file size.', 'metermaid' ),
						2 => __( 'The CSV file is larger than the maximum file size.', 'metermaid' ),
						3 => __( 'The CSV file only partially uploaded. Try again.', 'metermaid' ),
						4 => __( 'No file was uploaded. Try again.', 'metermaid' ),
						6 => __( 'The server couldn\'t write the file to disk. Try again.', 'metermaid' ),
						7 => __( 'The server failed to write file to disk. Try again.', 'metermaid' ),
					);

					$error_message = $upload_error_strings[ $csv['error'] ] ?? sprintf( __( 'An error occurred (error code %s).', 'metermaid' ), $csv['error'] );

					METERMAID::save_pending_notice( 'error', $error_message );
				} else {
					$success = true;
					$count = 0;
					$header_fields = array();

					if ( ( $handle = fopen( $csv['tmp_name'], 'r' ) ) !== false ) {
						while ( $success && ( $data = fgetcsv( $handle, 1000, ",") ) !== false ) {
							if ( empty( $header_fields ) ) {
								foreach ( $data as $column => $label ) {
									$header_fields[ trim( strtolower( $label ) ) ] = $column;
								}

								if ( ! isset( $header_fields['reading'] ) ) {
									METERMAID::save_pending_notice( 'error', __( 'The CSV must contain a "Reading" column.', 'metermaid' ) );
									$success = false;
								}

								if ( ! isset( $header_fields['date'] ) ) {
									METERMAID::save_pending_notice( 'error', __( 'The CSV must contain a "Date" column.', 'metermaid' ) );
									$success = false;
								}
							} else {
								$date = $data[ $header_fields['date'] ];
								$reading = $data[ $header_fields['reading'] ];

								$meter->add_reading( $reading, $date );
								$count++;
							}
						}

						fclose( $handle );
					}

					if ( $success ) {
						METERMAID::save_pending_notice( 'success', sprintf( __( '%s readings were imported.', 'metermaid' ), number_format( $count ) ) );
					}
				}
			}
		}
	}

	public static function save_pending_notice( $notice_type, $message ) {
		if ( ! isset( METERMAID::$pending_notices[ $notice_type ] ) ) {
			METERMAID::$pending_notices[ $notice_type ] = array();
		}

		METERMAID::$pending_notices[ $notice_type ][] = $message;
	}

	public static function admin_notices() {
		if ( empty( METERMAID::$pending_notices ) ) {
			return;
		}

		foreach ( METERMAID::$pending_notices as $notice_type => $messages ) {
			foreach ( $messages as $message ) {
				echo '<div class="notice notice-' . esc_attr( $notice_type ) . '"><p>' . esc_html( $message ) . '</p></div>';
			}
		}

		METERMAID::$pending_notices = [];
	}

	public static function admin_page() {
		global $wpdb;

		if ( isset( $_GET['metermaid_meter_id'] ) ) {
			if ( ! current_user_can( 'metermaid-view-meter', $_GET['metermaid_meter_id'] ) ) {
				echo 'You are not authorized to access this meter.';
				wp_die();
			}

			return self::meter_detail_page( $_GET['metermaid_meter_id'] );
		}

		if ( isset( $_GET['metermaid_system_id'] ) ) {
			if ( ! current_user_can( 'metermaid-access-system', $_GET['metermaid_system_id'] ) ) {
				echo 'You are not authorized to access this system.';
				wp_die();
			}

			return self::system_detail_page( $_GET['metermaid_system_id'] );
		}

		$all_systems = self::systems();

		if ( count( $all_systems ) == 1 && ! current_user_can( 'metermaid-add-system' ) ) {
			return self::system_detail_page( $all_systems[0]->id );
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( __( 'Metermaid', 'metermaid' ) ); ?>
				&raquo;
				<?php echo esc_html( __( 'Your Water Systems', 'metermaid' ) ); ?>
			</h1>

			<div class="metermaid-tabbed-content-container">
				<nav class="nav-tab-wrapper">
					<?php if ( current_user_can( 'metermaid-add-system' ) ) { ?><a href="#tab-add-system" class="nav-tab" data-metermaid-tab="add-system"><?php echo esc_html( __( 'Add System', 'metermaid' ) ); ?></a><?php } ?>
					<?php if ( current_user_can( 'metermaid-edit-settings' ) ) { ?><a href="#tab-edit-settings" class="nav-tab" data-metermaid-tab="edit-settings"><?php echo esc_html( __( 'Configure Metermaid', 'metermaid' ) ); ?></a><?php } ?>
					<a href="#tab-profile" class="nav-tab" data-metermaid-tab="profile"><?php echo esc_html( __( 'Edit Profile', 'metermaid' ) ); ?></a>
				</nav>
				<div class="metermaid-tabbed-content card">
					<?php if ( current_user_can( 'metermaid-add-system' ) ) { ?>
						<div data-metermaid-tab="add-system">
							<?php self::system_form(); ?>
						</div>
					<?php } ?>
					<div data-metermaid-tab="profile">
						<?php self::profile_form(); ?>
					</div>
					<?php if ( current_user_can( 'metermaid-edit-settings' ) ) { ?>
						<div data-metermaid-tab="edit-settings">
							<?php self::settings_form(); ?>
						</div>
					<?php } ?>
				</div>
			</div>

			<?php foreach ( $all_systems as $system ) { ?>
				<h2 class="wp-heading-inline"><a href="<?php echo esc_attr( add_query_arg( 'metermaid_system_id', $system->id ) ); ?>"><?php echo esc_html( $system->name ); ?></a></h2>

				<?php $system->display_meter_table( false ); ?>
			<?php } ?>
		</div>
		<?php
	}

	public static function system_detail_page( $system_id ) {
		global $wpdb;

		$system = new METERMAID_SYSTEM( $system_id );

		if ( ! current_user_can( 'metermaid-add-meter', $system_id ) && count( $system->readable_meters ) == 1 ) {
			return METERMAID::meter_detail_page( $system->readable_meters[0]->id );
		}

		?>
		<div class="wrap">
			<?php

			if ( ! $system() ) {
				?><h1><?php echo esc_html( __( 'System Not Found', 'metermaid' ) ); ?></h1><?php
			} else {
				?>

				<h1 class="wp-heading-inline">
					<a href="<?php echo esc_url( remove_query_arg( array( 'metermaid_system_id', 'metermaid_meter_id' ) ) ); ?>"><?php echo esc_html( __( 'Metermaid', 'metermaid' ) ); ?></a>
					&raquo;
					<?php echo esc_html( $system->name ); ?>
				</h1>

				<div class="metermaid-tabbed-content-container">
					<nav class="nav-tab-wrapper">
						<?php if ( current_user_can( 'metermaid-add-reading' ) ) { ?><a href="#tab-reading" class="nav-tab" data-metermaid-tab="reading"><?php echo esc_html( __( 'Add Reading', 'metermaid' ) ); ?></a><?php } ?>
						<?php if ( current_user_can( 'metermaid-add-meter' ) ) { ?><a href="#tab-add-meter" class="nav-tab" data-metermaid-tab="add-meter"><?php echo esc_html( __( 'Add Meter', 'metermaid' ) ); ?></a><?php } ?>
						<?php if ( current_user_can( 'metermaid-edit-system', $system->id ) ) { ?><a href="#tab-settings" class="nav-tab" data-metermaid-tab="settings"><?php echo esc_html( __( 'Configure System', 'metermaid' ) ); ?></a><?php } ?>
						<?php if ( count( $system->readable_meters ) > 0 ) { ?>
							<a href="#tab-export" class="nav-tab" data-metermaid-tab="export"><?php echo esc_html( __( 'Export', 'metermaid' ) ); ?></a>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-invite-system', $system->id ) ) { ?><a href="#tab-invite" class="nav-tab" data-metermaid-tab="invite"><?php echo esc_html( __( 'Invite Others', 'metermaid' ) ); ?></a><?php } ?>
						<a href="#tab-profile" class="nav-tab" data-metermaid-tab="profile"><?php echo esc_html( __( 'Edit Profile', 'metermaid' ) ); ?></a>
					</nav>
					<div class="metermaid-tabbed-content card">
						<?php if ( current_user_can( 'metermaid-add-reading' ) ) { ?>
							<div data-metermaid-tab="reading">
								<?php if ( count( $system->writeable_meters ) > 0 ) { ?>
									<?php self::add_reading_form( $system->id ); ?>
								<?php } else { ?>
									<p><a href="#tab-add-meter"><?php echo esc_html( __( 'Add a meter before entering any readings.', 'metermaid' ) ); ?></a></p>
								<?php } ?>
							</div>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-add-meter' ) ) { ?>
							<div data-metermaid-tab="add-meter">
								<?php self::meter_form( $system->id ); ?>
							</div>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-edit-system', $system->id ) ) { ?>
							<div data-metermaid-tab="settings">
								<?php self::system_form( $system->id ); ?>
							</div>
						<?php } ?>
						<?php if ( count( $system->readable_meters ) > 0 ) { ?>
							<div data-metermaid-tab="export">
								<table class="form-table">
									<tr>
										<th scope="row">
											<?php echo esc_html( __( 'Export All Meter Readings', 'metermaid' ) ); ?>
										</th>
										<td>
											<p><?php echo esc_html( __( 'The CSV will contain a single Date column and one column for each meter\'s readings.', 'metermaid' ) ); ?></p>
											<form method="post" action="">
												<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-export-meters' ) ); ?>" />
												<input type="hidden" name="metermaid_action" value="export_meters" />
												<input type="hidden" name="metermaid_system_id" value="<?php echo esc_attr( $system->id ); ?>" />
												<p>
													<input type="submit" class="button button-primary" value="<?php echo esc_attr( __( 'Download CSV', 'metermaid' ) ); ?>" />
												</p>
											</form>
										</td>
									</tr>
								</table>
							</div>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-invite-system', $system->id ) ) { ?>
							<div data-metermaid-tab="invite">
								<?php self::invite_form( $system->id ); ?>
							</div>
						<?php } ?>
						<div data-metermaid-tab="profile">
							<?php self::profile_form(); ?>
						</div>
					</div>
				</div>

				<?php $system->display_meter_table( true ); ?>
			<?php } ?>
		</div>
		<?php
	}

	public static function meter_detail_page( $meter_id ) {
		global $wpdb;

		$meter = new METERMAID_METER( $meter_id );

		if ( isset( $_GET['recalculate'] ) ) {
			$meter->recalculate_real_readings();
		}

		?>
		<div class="wrap">
			<?php

			if ( empty( $meter ) ) {
				?><h1><?php echo esc_html( __( 'Meter Not Found', 'metermaid' ) ); ?></h1><?php
			} else {
				$meter_readings = $meter->readings();

				?>
				<h1 class="wp-heading-inline">
					<a href="<?php echo esc_url( remove_query_arg( 'metermaid_meter_id' ) ); ?>"><?php echo esc_html( __( 'Metermaid', 'metermaid' ) ); ?></a>
					&raquo;
					<a href="<?php echo esc_url( add_query_arg( 'metermaid_system_id', $meter->system_id, remove_query_arg( 'metermaid_meter_id' ) ) ); ?>"><?php echo esc_html( $meter->system->name ); ?></a>
					&raquo;
					<?php

					echo sprintf( esc_html(	__( 'Meter Details: %s', 'metermaid' ) ), esc_html( $meter->name ) );

					?>
				</h1>

				<div class="metermaid-tabbed-content-container">
					<nav class="nav-tab-wrapper">
						<?php if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) { ?><a href="#tab-reading" class="nav-tab" data-metermaid-tab="reading"><?php echo esc_html( __( 'Add Reading', 'metermaid' ) ); ?></a><?php } ?>
						<?php if ( current_user_can( 'metermaid-add-supplement', $meter->id ) ) { ?><a href="#tab-supplement" class="nav-tab" data-metermaid-tab="supplement"><?php echo esc_html( __( 'Add Supplement', 'metermaid' ) ); ?></a><?php } ?>
						<?php if ( current_user_can( 'metermaid-edit-meter', $meter->id ) ) { ?><a href="#tab-settings" class="nav-tab" data-metermaid-tab="settings"><?php echo esc_html( __( 'Configure Meter', 'metermaid' ) ); ?></a><?php } ?>
						<a href="#tab-import_export" class="nav-tab" data-metermaid-tab="import_export">
							<?php if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) { ?>
								<?php echo esc_html( __( 'Import/Export', 'metermaid' ) ); ?></a>
							<?php } else { ?>
								<?php echo esc_html( __( 'Export', 'metermaid' ) ); ?></a>
							<?php } ?>
						</a>
						<a href="#tab-invite" class="nav-tab" data-metermaid-tab="invite"><?php echo esc_html( __( 'Invite Others', 'metermaid' ) ); ?></a>
						<a href="#tab-profile" class="nav-tab" data-metermaid-tab="profile"><?php echo esc_html( __( 'Edit Profile', 'metermaid' ) ); ?></a>
					</nav>
					<div class="metermaid-tabbed-content card">
						<?php if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) { ?>
							<div data-metermaid-tab="reading">
								<?php self::add_reading_form( $meter->system_id, $meter->id ); ?>
							</div>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-add-supplement', $meter->id ) ) { ?>
							<div data-metermaid-tab="supplement">
								<?php self::add_supplement_form( $meter->id ); ?>
							</div>
						<?php } ?>
						<?php if ( current_user_can( 'metermaid-edit-meter', $meter->id ) ) { ?>
							<div data-metermaid-tab="settings">
								<?php self::meter_form( $meter->system_id, $meter->id ); ?>
							</div>
						<?php } ?>
						<div data-metermaid-tab="import_export">
							<table class="form-table">
								<tr>
									<th scope="row">
										<?php echo esc_html( __( 'Export Readings as CSV', 'metermaid' ) ); ?>
									</th>
									<td>
										<form method="post" action="">
											<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-export-meter' ) ); ?>" />
											<input type="hidden" name="metermaid_action" value="export_meter" />
											<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
											<input type="submit" class="button button-primary" value="<?php echo esc_attr( __( 'Download CSV', 'metermaid' ) ); ?>" />
										</form>
									</td>
								</tr>
								<?php if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) { ?>
									<tr>
										<th scope="row">
											<?php echo esc_html( __( 'Import readings from CSV', 'metermaid' ) ); ?>
										</th>
										<td>
											<p><?php echo esc_html( sprintf( __( 'The CSV must have a column labeled "%1$s" and a column labeled "%2$s".', 'metermaid' ), 'Date', 'Reading' ) ); ?></p>
											<form method="post" action="" enctype="multipart/form-data">
												<input type="hidden" name="metermaid_action" value="import_meter" />
												<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-import-meter' ) ); ?>" />
												<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
												<p>
													<input type="file" name="metermaid_import_file" />
												</p>
												<p>
													<input type="submit" class="button button-secondary" value="Import CSV" />
												</p>
											</form>
										</td>
									</tr>
								<?php } ?>
							</table>
						</div>
						<div data-metermaid-tab="invite">
							<?php self::invite_form( $meter->system_id, $meter->id ); ?>
						</div>
						<div data-metermaid-tab="profile">
							<?php self::profile_form(); ?>
						</div>
					</div>
				</div>

				<?php if ( ! empty( $meter_readings ) ) { ?>
					<div class="metermaid-tabbed-content-container">
						<nav class="nav-tab-wrapper">
							<a href="#tab-readings" class="nav-tab" data-metermaid-tab="readings">
								<?php echo esc_html( __( 'Readings', 'metermaid' ) ); ?>
							</a>
							<?php if ( count( $meter_readings ) > 1 ) { ?>
								<a href="#tab-year-chart" class="nav-tab" data-metermaid-tab="year-chart">
									<?php echo esc_html( sprintf( __( '%s Chart', 'metermaid' ), strtoupper( $meter->system->measurement()['rate_abbreviation'] ) ) ); ?>
								</a>
								<a href="#tab-supplement-chart" class="nav-tab" data-metermaid-tab="supplement-chart">
									<?php echo esc_html( __( 'YTD Chart', 'metermaid' ) ); ?>
								</a>
							<?php } ?>
							<?php if ( $meter->is_parent() ) { ?>
								<a href="#tab-children-chart" class="nav-tab" data-metermaid-tab="children-chart"><?php echo esc_html( __( 'Children', 'metermaid' ) ); ?></a>
							<?php } ?>
						</nav>
						<div class="metermaid-tabbed-content card">
							<div data-metermaid-tab="readings">
								<?php

								if ( ! empty( $meter_readings ) ) { ?>
									<table class="wp-list-table widefat striped">
										<thead>
											<th></th>
											<th><?php echo esc_html( __( 'Date', 'metermaid' ) ); ?></th>
											<th><?php echo esc_html( __( 'Reading', 'metermaid' ) ); ?></th>
											<?php if ( $meter->is_parent() ) { ?>
												<th><?php echo esc_html( __( 'Children +/-', 'metermaid' ) ); ?></th>
											<?php } ?>
											<th>
												<?php echo esc_html( sprintf( __( '%1$s Since Last (At least %2$s days)', 'metermaid' ), strtoupper( $meter->system->measurement()['rate_abbreviation'] ), $meter->system->rate_interval ) ); ?>
											</th>
											<th><?php echo esc_html( sprintf( __( '%s Since Last', 'metermaid' ), $meter->system->measurement()['plural'] ) ); ?></th>
											<th><?php echo esc_html( sprintf( __( '%s YTD', 'metermaid' ), $meter->system->measurement()['plural'] ) ); ?></th>
										</thead>
										<tbody>
											<?php

											foreach ( $meter_readings as $idx => $reading ) {
												?>
												<tr>
													<td>
														<?php if ( $reading->id && current_user_can( 'metermaid-delete-reading', $reading->id ) ) { ?>
															<form method="post" action="" onsubmit="return confirm( metermaid_i18n.reading_delete_confirm );">
																<input type="hidden" name="metermaid_action" value="delete_reading" />
																<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-reading' ) ); ?>" />
																<input type="hidden" name="metermaid_reading_id" value="<?php echo esc_attr( $reading->id ); ?>" />
																<input type="submit" class="button button-secondary" value="Delete" />
															</form>
														<?php } ?>
													</td>
													<td><?php echo esc_html( date( get_option( 'date_format' ), strtotime( $reading->reading_date ) ) ); ?></td>
													<td><?php echo esc_html( number_format( $reading->reading, 0 ) ); ?></td>
													<?php if ( $meter->is_parent() ) {
														$children_readings = $meter->children_readings();
														$supplements = $meter->supplements();

														?>
														<td>
															<?php

															if ( isset( $children_readings[ $reading->reading_date ] ) ) {
																// Now, figure out the difference between this reading and the next child reading, and then compare that difference to the diff between today's master reading and the reading from the date of the child reading.

																$found = false;

																foreach ( $children_readings as $date => $children_reading ) {
																	if ( $date == $reading->reading_date ) {
																		$found = true;
																	} else if ( $found ) {
																		$total_gallons = $children_readings[ $reading->reading_date ] - $children_reading;

																		foreach ( $meter_readings as $_reading ) {
																			if ( $date == $_reading->reading_date ) {
																				$includes_supplements = false;

																				$master_total_gallons = $reading->real_reading - $_reading->real_reading;
																				$difference = $total_gallons - $master_total_gallons;

																				// Now, add any supplementary water to the difference.
																				foreach ( $supplements as $supplement ) {
																					if ( $supplement->supplement_date < $reading->reading_date && $supplement->supplement_date >= $_reading->reading_date ) {
																						$difference -= $supplement->amount;
																						$includes_supplements = true;
																					}
																				}

																				$difference_per_day = round( $difference / ( ( strtotime( $reading->reading_date ) - strtotime( $_reading->reading_date ) ) / 60 / 60 / 24 ) );

																				$difference_percent = round( $difference / $total_gallons * 100, 1 );

																				if ( $difference > 0 ) {
																					echo '<span title="' . esc_attr( __( 'The child meters of this meter read higher than expected. Either they are overreporting, or this meter is underreporting.', 'metermaid' ) ) . '" class="metermaid-surplus">+' . esc_html( number_format( $difference, 0 ) ) . ' / ' . esc_html( $difference_percent ) . '%; ' . number_format( $difference_per_day, 0 ) . ' ' . esc_html( $meter->system->measurement()['rate_abbreviation'] ) . '</span>';
																				} else if ( $difference < 0 ) {
																					echo '<span title="' . esc_attr( __( 'The child meters of this meter read lower than expected. Either they are underreporting, or this meter is overreporting.', 'metermaid' ) ) . '" class="metermaid-deficit">' . esc_html( number_format( $difference, 0 ) ) . ' / ' . esc_html( $difference_percent ) . '%; ' . number_format( $difference_per_day, 0 ) . ' ' . esc_html( $meter->system->measurement()['rate_abbreviation'] ) . '</span>';
																				} else {
																					echo '<span title="' . esc_attr( __( 'The child meters of this meter perfectly match this meter.', 'metermaid' ) ) . '" class="metermaid-balanced">0%</span>';
																				}

																				if ( $includes_supplements ) {
																					echo '<abbr title="Includes supplementary water">*</abbr>';
																				}

																				break;
																			}
																		}

																		break;
																	}
																}
															}

															?>
														</td>
													<?php } ?>
													<td>
														<?php echo esc_html( self::gpd( $reading, $meter->readings(), $meter->system->rate_interval ) ); ?>
													</td>
													<td>
														<?php

														if ( isset( $meter_readings[ $idx + 1 ] ) ) {
															echo esc_html( number_format( $reading->real_reading - $meter_readings[ $idx + 1 ]->real_reading, 0 ) );
														}

														?>
													</td>
													<td>
														<?php

														$gallons_ytd = $meter->gallons_ytd( $reading );

														echo esc_html( number_format( $gallons_ytd ?? 0 ) );

														$gallons_last_year_at_this_time = $meter->estimate_real_reading_on_this_date_last_year( $reading->reading_date );

														if ( $gallons_ytd && $gallons_last_year_at_this_time ) {
															$tooltip_text = sprintf(
																__( 'Versus %1$s %2$s last year at this point', 'metermaid' ),
																number_format( $gallons_last_year_at_this_time ),
																$meter->system->measurement()['plural']
															);

															if ( $gallons_ytd < $gallons_last_year_at_this_time ) {
																$percent_decrease = round( ( ( $gallons_last_year_at_this_time - $gallons_ytd ) / $gallons_last_year_at_this_time ) * 100 );

																echo ' <span class="metermaid-surplus" title="' . esc_attr( $tooltip_text ) . '">(-' . number_format( $percent_decrease, 0 ) . '%)</span>';
															} else {
																$percent_increase = round( ( ( $gallons_ytd - $gallons_last_year_at_this_time ) / $gallons_last_year_at_this_time ) * 100 );

																echo ' <span class="metermaid-deficit" title="' . esc_attr( $tooltip_text ) . '">(+' . number_format( $percent_increase, 0 ) . '%)</span>';
															}
														}

														?>
													</td>
												</tr>
												<?php
											}

											?>
										</tbody>
									</table>
								<?php } ?>
							</div>
							<?php if ( count( $meter_readings ) > 1 ) { ?>
								<div data-metermaid-tab="year-chart">
									<?php $meter->output_year_chart(); ?>
								</div>
								<div data-metermaid-tab="supplement-chart">
									<?php $meter->output_ytd_chart(); ?>
								</div>
							<?php } ?>
							<?php if ( $meter->is_parent() ) { ?>
								<div data-metermaid-tab="children-chart">
									<?php $meter->output_children_chart(); ?>
								</div>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
				<?php

				$supplements = $meter->supplements();

				?>
				<?php if ( ! empty( $supplements ) ) { ?>
					<h2><?php echo esc_html( __( 'Supplementary Water', 'metermaid' ) ); ?></h2>
					<table class="wp-list-table widefat striped">
						<thead>
							<tr>
								<th></th>
								<th><?php echo esc_html( __( 'Date', 'metermaid' ) ); ?></th>
								<th><?php echo esc_html( __( 'Supplement', 'metermaid' ) ); ?></th>
								<th><?php echo esc_html( __( 'Note', 'metermaid' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $supplements as $supplement ) { ?>
								<tr>
									<td>
										<?php if ( current_user_can( 'metermaid-delete-supplement', $meter->id ) ) { ?>
											<form method="post" action="" onsubmit="return confirm( metermaid_i18n.supplement_delete_confirm );">
												<input type="hidden" name="metermaid_action" value="delete_supplement" />
												<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-supplement' ) ); ?>" />
												<input type="hidden" name="supplement_id" value="<?php echo esc_attr( $supplement->metermaid_supplement_id ); ?>" />
												<input type="submit" class="button button-secondary" value="Delete" />
											</form>
										<?php } ?>
									</td>
									<td><?php echo esc_html( date( get_option( 'date_format' ), strtotime( $supplement->supplement_date ) ) ); ?></td>
									<td><?php echo number_format( $supplement->amount, 0 ); ?></td>
									<td><?php echo esc_html( $supplement->note ); ?></td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<?php

				}

				if ( $meter->is_parent() ) {
					$child_meters = array();

					foreach ( $meter->children() as $child_id ) {
						if ( current_user_can( 'metermaid-view-meter', $child_id ) ) {
							$child_meters[] = new METERMAID_METER( $child_id );
						}
					}
				}

				if ( ! empty( $child_meters ) ) {
					?>
					<h2><?php echo esc_html( __( 'Child Meters', 'metermaid' ) ); ?></h2>
					<table class="widefat striped wp-list-table">
						<thead>
							<tr>
								<th></th>
								<th><?php echo esc_html( __( 'Child Meter', 'metermaid' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php

							$children = $meter->children();

							foreach ( $child_meters as $child ) {
								?>
								<tr>
									<td></td>
									<td>
										<a href="<?php echo esc_attr( add_query_arg( 'metermaid_meter_id', $child->id ) ); ?>">
											<?php echo esc_html( $child->name ); ?>
										</a>
									</td>
								</tr>
								<?php
							}

							?>
						</tbody>
					</table>
					<?php
				}
			}

			?>
		</div>
		<?php
	}

	public static function systems() {
		global $wpdb;

		static $all_systems = null;

		if ( is_null( $all_systems ) ) {
			$system_rows = $wpdb->get_results(
				"SELECT * FROM " . $wpdb->prefix . "metermaid_systems ORDER BY name ASC"
			);

			$all_systems = array();

			foreach ( $system_rows as $system_row ) {
				if ( current_user_can( 'metermaid-access-system', $system_row->metermaid_system_id ) ) {
					$all_systems[] = new METERMAID_SYSTEM( $system_row );
				}
			}
		}

		return $all_systems;

	}

	public static function system_list_selection( $field_name, $multiple = false, $selected = array() ) {
		global $wpdb;

		$all_systems = METERMAID::systems();

		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		?>
		<select name="<?php echo esc_attr( $field_name ); ?><?php if ( $multiple ) { ?>[]<?php } ?>"<?php if ( $multiple ) { ?> multiple<?php } ?>>
			<?php

			if ( ! $multiple ) {
				?>
				<option value=""><?php echo esc_html( __( '-- Select System --', 'metermaid' ) ); ?></option>
				<?php
			}

			foreach ( $all_systems as $system ) {
				?>
				<option value="<?php echo esc_attr( $system->id ); ?>"<?php if ( in_array( $system->id, $selected ) ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $system->name ); ?></option>
				<?php
			}

			?>
		</select>
		<?php
	}

	public static function meter_list_selection( $system_id, $field_name, $multiple = false, $selected = array(), $exclude = array() ) {
		global $wpdb;

		$system = new METERMAID_SYSTEM( $system_id );
		// @todo Error handle invalid system.

		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		?>
		<select name="<?php echo esc_attr( $field_name ); ?><?php if ( $multiple ) { ?>[]<?php } ?>"<?php if ( $multiple ) { ?> multiple<?php } ?>>
			<?php

			if ( ! $multiple && count( $system->writeable_meters ) > 1 ) {
				?>
				<option value=""><?php echo esc_html( __( '-- Select Meter --', 'metermaid' ) ); ?></option>
				<?php
			}

			$last_was_parent = false;

			foreach ( $system->writeable_meters as $meter ) {
				if ( in_array( $meter->id, $exclude ) ) {
					continue;
				}

				if ( $meter->is_parent() ) {
					$last_was_parent = true;
				} else if ( $last_was_parent ) {
					?><option value="" data-metermaid-system-id="<?php echo esc_attr( $system->id ); ?>">--</option><?php
					$last_was_parent = false;
				}

				?>
				<option data-metermaid-system-id="<?php echo esc_attr( $system->id ); ?>" value="<?php echo esc_attr( $meter->id ); ?>"<?php if ( in_array( $meter->id, $selected ) ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $meter->name ); ?></option>
				<?php
			}

			?>
		</select>
		<?php
	}

	public static function system_form( $system_id = null ) {
		$system = new METERMAID_SYSTEM( $system_id );

		if ( $system_id && ! $system() ) {
			wp_die( 'Invalid system id.' );
		}

		?>
		<form method="post" action="" class="metermaid_add_system_form">
			<?php if ( $system() ) { ?>
				<input type="hidden" name="metermaid_action" value="edit_system" />
				<input type="hidden" name="metermaid_system_id" value="<?php echo esc_attr( $system_id ); ?>" />
				<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-edit-system' ) ); ?>" />
			<?php } else { ?>
				<input type="hidden" name="metermaid_action" value="add_system" />
				<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-system' ) ); ?>" />
			<?php } ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Water System Name', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_system_name" value="<?php echo esc_attr( $system ? $system->name : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Unit of measurement', 'metermaid' ) ); ?>
					</th>
					<td>
						<select name="metermaid_system_unit">
							<?php foreach ( METERMAID::$units_of_measurement as $unit => $unit_meta ) { ?>
								<option value="<?php echo esc_attr( $unit ); ?>" <?php if ( $system() && ( $unit == $system->unit ) ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $unit_meta['plural'] ); ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Minimum rate interval (in days)', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" inputmode="numeric" pattern="[0-9,.]*" name="metermaid_system_rate_interval" value="<?php echo esc_attr( $system() ? $system->rate_interval : METERMAID_DEFAULT_RATE_INTERVAL ); ?>" />
						<p class="description"><?php echo esc_html( __( 'What is the least number of days between which Metermaid should calculate average usage rates? A higher number means a more accurate result, but it will also be more likely to hide short-term trends.' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( $system_id ? __( 'Save System Configuration', 'metermaid' ) : __( 'Add System', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php if ( $system() && current_user_can( 'metermaid-system-delete', $system->id ) ) { ?>
			<hr />
			<table class="form-table">
				<tr>
					<th scope="row"><?php echo esc_html( __( 'Delete This System', 'metermaid' ) ); ?></th>
					<td>
						<p><?php echo esc_html( __( 'Deleting this system will remove all of its meters and its readings.' ) ); ?></p>

						<form method="post" action="<?php echo esc_attr( site_url( 'wp-admin/admin.php?page=metermaid-home' ) ); ?>" onsubmit="return confirm( metermaid_i18n.system_delete_confirm );">
							<input type="hidden" name="metermaid_action" value="delete_system" />
							<input type="hidden" name="metermaid_system_id" value="<?php echo esc_attr( $system_id ); ?>" />
							<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-system' ) ); ?>" />
							<p>
								<input type="submit" class="button button-delete" value="<?php echo esc_attr( sprintf( __( 'Delete %s', 'metermaid' ), $system->name ) ); ?>" />
							</p>
						</form>
					</td>
				</tr>
			</table>
		<?php } ?>
		<?php
	}

	public static function settings_form() {
		?>
		<p>
			<?php echo esc_html( __( "To enable Metermaid's SMS integration, enter your Twilio settings below.", 'metermaid' ) ); ?>
			<a href="<?php echo esc_attr( add_query_arg( 'page', 'metermaid-sms', remove_query_arg( 'page' ) ) ); ?>"><?php echo esc_html( __( "(View SMS dashboard)", 'metermaid' ) ); ?></a>
		</p>

		<form method="post" action="" class="metermaid_settings_form">
			<input type="hidden" name="metermaid_action" value="edit_settings" />
			<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-edit-settings' ) ); ?>" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Twilio Phone Number', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_twilio_phone_number" value="<?php echo esc_attr( get_option( 'METERMAID_TWILIO_PHONE_NUMBER' ) ); ?>" />
						<p class="description"><?php echo esc_html( __( 'For display purposes only, so you can enter a number like 555-MYWATER.', 'metermaid' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Twilio Account SID', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_twilio_account_sid" value="<?php echo esc_attr( get_option( 'METERMAID_TWILIO_ACCOUNT_SID' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Twilio Auth Token', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_twilio_auth_token" value="<?php echo esc_attr( get_option( 'METERMAID_TWILIO_AUTH_TOKEN' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Twilio Messaging Service SID', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_twilio_messaging_service_sid" value="<?php echo esc_attr( get_option( 'METERMAID_TWILIO_MESSAGING_SERVICE_SID' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( __( 'Save Configuration', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static function add_reading_form( $system_id, $meter_id = null ) {
		?>
		<form method="post" action="" class="metermaid_add_reading_form">
			<input type="hidden" name="metermaid_action" value="add_reading" />
			<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-reading' ) ); ?>" />

			<?php if ( $meter_id ) { ?>
				<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter_id ); ?>" />
			<?php } ?>

			<table class="form-table">
				<?php

				if ( ! $meter_id ) {
					$system = new METERMAID_SYSTEM( $system_id );

					if ( $system_id && ! $system() ) {
						wp_die( 'Invalid system id.' );
					}

					?>
					<tr>
						<th scope="row">
							<?php echo esc_html( __( 'Meter', 'metermaid' ) ); ?>
						</th>
						<td>
							<?php METERMAID::meter_list_selection( $system->id, 'metermaid_meter_id' ); ?>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Date', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="date" name="metermaid_reading_date" value="<?php echo esc_html( current_datetime()->format( 'Y-m-d' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Reading', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" inputmode="numeric" pattern="[0-9,.]*" name="metermaid_reading" value="" />
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( __( 'Add Reading', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	// Sort readings in reverse descending order.
	public static function readings_sort( $a, $b ) {
		if ( $a->reading_date < $b->reading_date ) {
			return 1;
		} else if ( $a->reading_date > $b->reading_date ) {
			return -1;
		}

		return 0;
	}

	public static function gpd( $reading, $readings, $minimum_days = 1 ) {
		usort( $readings, array( __CLASS__, 'readings_sort' ) );

		foreach ( $readings as $_reading ) {
			if ( $_reading->reading_date >= date( "Y-m-d", strtotime( $reading->reading_date ) - ( 24 * 60 * 60 * ( $minimum_days ) ) ) ) {
				continue;
			}

			return number_format( round(
				( $reading->real_reading - $_reading->real_reading ) /
					(
					(
						  strtotime( $reading->reading_date )
						- strtotime( $_reading->reading_date )
					)
					/ ( 24 * 60 * 60 )
				)
			), 0 );
		}

		return '';
	}

	public static $units_of_measurement = array(
		'gallon' => array(
			'singular' => 'Gallon',
			'plural' => 'Gallons',
			'rate_abbreviation' => 'gpd',
		),
		'cubic-foot' => array(
			'singular' => 'Cubic Foot',
			'plural' => 'Cubic Feet',
			'rate_abbreviation' => 'cfd',
		),
		'cubic-meter' => array(
			'singular' => 'Cubic Meter',
			'plural' => 'Cubic Meters',
			'rate_abbreviation' => 'cmd',
		),
	);

	public static $defaults = array(
		'unit_of_measurement' => 'gallon',
		'minimum_rate_interval' => 7,
	);

	public static function add_supplement_form( $meter_id ) {
		?>
		<form method="post" action="">
			<input type="hidden" name="metermaid_action" value="add_supplement" />
			<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-supplement' ) ); ?>" />
			<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter_id ); ?>" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Date', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="date" name="metermaid_supplement_date" value="<?php echo esc_html( current_datetime()->format( 'Y-m-d' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Amount', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" inputmode="numeric" pattern="[0-9,.]*" name="metermaid_supplement_amount" value="" />
						<p class="description"><?php echo esc_html( __( 'A supplement is water that is added to the system after this meter. For example, water being delivered directly to a holding tank downstream from this meter would be a supplement.', 'metermaid' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Note', 'metermaid' ) ); ?>
					</th>
					<td>
						<textarea name="metermaid_supplement_note"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( __( 'Add Supplement', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static function meter_form( $system_id, $meter_id = null ) {
		$system = new METERMAID_SYSTEM( $system_id );
		$meter = new METERMAID_METER( $meter_id );

		?>
		<form method="post" action="">
			<?php if ( $meter() ) { ?>
				<input type="hidden" name="metermaid_action" value="edit_meter" />
				<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter_id ); ?>" />
				<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-edit-meter' ) ); ?>" />
			<?php } else { ?>
				<input type="hidden" name="metermaid_action" value="add_meter" />
				<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-meter' ) ); ?>" />
				<input type="hidden" name="metermaid_system_id" value="<?php echo esc_attr( $system_id ); ?>" />
			<?php } ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Meter Name, ID, or Location', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_meter_name" value="<?php echo esc_attr( $meter() ? $meter->name : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Status', 'metermaid' ) ); ?>
					</th>
					<td>
						<select name="metermaid_meter_status">
							<?php foreach ( METERMAID_METER::statuses() as $status_value => $status_label ) { ?>
								<option
									value="<?php echo esc_attr( $status_value ); ?>"
									<?php if ( $meter() && ( $meter->status == $status_value ) ) { ?>
										selected="selected"
									<?php } ?>
								>
									<?php echo esc_html( $status_label ); ?>
								</option>
							<?php } ?>
						</select>
						<p class="description"><?php echo esc_html( __( 'Inactive meters can either be meters that have been removed from the system or ones that can be assumed to have the same reading as their last reading.', 'metermaid' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Contact Name', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_meter_contact_name" value="<?php echo esc_attr( $meter() ? $meter->contact_name : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Contact Email', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_meter_contact_email" value="<?php echo esc_attr( $meter() ? $meter->contact_email : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Contact Phone', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_meter_contact_phone" value="<?php echo esc_attr( $meter() ? METERMAID_SMS::readable_phone_number( $meter->contact_phone ) : '' ); ?>" />
					</td>
				</tr>
				<?php

				if (
					( $meter() && count( $system->writeable_meters ) > 1 ) ||
					( ! $meter() && count( $system->writeable_meters ) > 0 )
					) {

					?>
					<tr>
						<th scope="row">
							<?php echo esc_html( __( 'Parent Meters', 'metermaid' ) ); ?>
						</th>
						<td>
							<?php METERMAID::meter_list_selection( $system_id, 'metermaid_parent_meters', true, $meter() ? $meter->parents() : [], array( $meter->id ) ); ?>
							<p class="description"><?php echo esc_html( __( 'A parent meter is a meter that is located upstream from this meter.', 'metermaid' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php echo esc_html( __( 'Child Meters', 'metermaid' ) ); ?>
						</th>
						<td>
							<?php METERMAID::meter_list_selection( $system_id, 'metermaid_child_meters', true, $meter() ? $meter->children() : [], array( $meter->id ) ); ?>
							<p class="description"><?php echo esc_html( __( 'A child meter is a meter that is located downstream from this meter.', 'metermaid' ) ); ?></p>
						</td>
					</tr>
					<?php

				}

				?>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( $meter() ? __( 'Update Meter', 'metermaid' ) : __( 'Add Meter', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static function profile_form() {
		?>
		<form method="post" action="">
			<input type="hidden" name="metermaid_action" value="edit_profile" />
			<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-edit-profile' ) ); ?>" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Your Name', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_profile_name" value="<?php echo esc_attr( get_user_meta( wp_get_current_user()->ID, 'nickname', true ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Phone', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_profile_phone" value="<?php echo esc_attr( METERMAID_SMS::readable_phone_number( get_user_meta( wp_get_current_user()->ID, 'metermaid_phone_number', true ) ) ); ?>" />
					</td>
				</tr>
				<?php if ( current_user_can( 'metermaid-add-reading' ) ) { ?>
					<tr>
						<th scope="row">
							<?php echo esc_html( __( 'Your Meter', 'metermaid' ) ); ?>
						</th>
						<td>
							<select name="metermaid_profile_meter">
								<option value=""><?php echo esc_html( __( '-- Select Meter --', 'metermaid' ) ); ?></option>
								<?php

								$all_systems = METERMAID::systems();
								$user_meter = get_user_meta( get_current_user_id(), 'metermaid_meter_id', true );

								foreach ( $all_systems as $system ) {
									$meters = $system->writeable_meters;

									if ( ! empty( $meters ) ) {
										?>
										<optgroup label="<?php echo esc_attr( $system->name ); ?>">
											<?php foreach ( $meters as $meter ) { ?>
												<option value="<?php echo esc_attr( $meter->id ); ?>"<?php if ( $meter->id == $user_meter ) { ?> selected="selected"<?php } ?>>
													<?php echo esc_html( $meter->name ); ?>
												</option>
											<?php } ?>
										</optgroup>
										<?php
									}
								}

								?>
							</select>
							<?php if ( METERMAID_SMS::is_configured() && get_option( 'METERMAID_TWILIO_PHONE_NUMBER' ) ) { ?>
								<p class="description"><?php echo esc_html( sprintf( __( 'Enter your phone number, select your meter, and then you can log meter readings by texting the current reading to %s.' ), get_option( 'METERMAID_TWILIO_PHONE_NUMBER' ) ) ); ?></p>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( __( 'Update Profile', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static function invite_form( $system_id = null, $meter_id = null ) {
		$meter = new METERMAID_METER( $meter_id );

		if ( $meter_id && ! $meter() ) {
			wp_die( 'Invalid meter id.' );
		}

		$system = new METERMAID_SYSTEM( $system_id );

		if ( $system_id && ! $system() ) {
			wp_die( 'Invalid system id.' );
		}

		if ( $system() && $meter() ) {
			if ( $system->id != $meter->system_id ) {
				wp_die( 'Mismatched system id.' );
			}
		}

		if ( ! $system() ) {
			wp_die( 'Invite form requires a system.' );
		}

		$show_meter = false;
		$show_system = false;
		$show_manage_meter = false;
		$show_manage_system = false;

		if ( $meter() && current_user_can( 'metermaid-invite-meter', $meter->id ) ) {
			$show_meter = true;

			if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) {
				$show_manage_meter = true;
			}
		}

		if ( current_user_can( 'metermaid-invite-system', $system->id ) ) {
			$show_system = true;

			if ( current_user_can( 'metermaid-edit-system', $system->id ) ) {
				$show_manage_system = true;
			}
		}

		?>
		<form method="post" action="">
			<input type="hidden" name="metermaid_action" value="invite" />
			<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-invite' ) ); ?>" />

			<?php if ( $meter() ) { ?>
				<input type="hidden" name="metermaid_invite_meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
			<?php } ?>

			<input type="hidden" name="metermaid_invite_system_id" value="<?php echo esc_attr( $system->id ); ?>" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'E-mail Address', 'metermaid' ) ); ?>
					</th>
					<td>
						<input type="text" name="metermaid_invite_email" value="" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Access', 'metermaid' ) ); ?>
					</th>
					<td>
						<?php if ( $show_meter ) { ?>
							<p>
								<label>
									<input type="radio" name="metermaid_invite_access_level" value="meter" checked="checked" /> <?php echo esc_html( __( 'Just this meter', 'metermaid' ) ); ?>
								</label>
							</p>
						<?php } ?>
						<?php if ( $show_system ) { ?>
							<p>
								<label>
									<input type="radio" name="metermaid_invite_access_level" value="system" <?php

									if ( ! $meter() || ! current_user_can( 'metermaid-invite-meter', $meter->id ) ) {
										echo ' checked="checked" ';
									}

									?> /> <?php echo esc_html( sprintf( __( 'The entire system: %s', 'metermaid' ), $system->name ) ); ?>
								</label>
							</p>
						<?php } ?>
					</td>
				</tr>
				<?php if ( $show_meter ) { ?>
					<tr class="metermaid_invite_manage_meter">
						<th scope="row">
							<?php echo esc_html( __( 'Can they make changes to it?', 'metermaid' ) ); ?>
						</th>
						<td>
							<?php if ( $show_manage_meter ) { ?>
								<p>
									<label>
										<input type="radio" name="metermaid_invite_manage_meter" value="1" /> <?php echo esc_html( __( 'Yes', 'metermaid' ) ); ?>
									</label>
								</p>
							<?php } ?>
							<p>
								<label>
									<input type="radio" name="metermaid_invite_manage_meter" value="0" checked="checked" /> <?php echo esc_html( __( 'No, but they can see all of the information', 'metermaid' ) ); ?>
								</label>
							</p>
						</td>
					</tr>
				<?php } ?>
				<?php if ( $show_system ) { ?>
					<tr class="metermaid_invite_manage_system">
						<th scope="row">
							<?php echo esc_html( __( 'Can they make changes to it?', 'metermaid' ) ); ?>
						</th>
						<td>
							<?php if ( $show_manage_system ) { ?>
								<p>
									<label>
										<input type="radio" name="metermaid_invite_manage_system" value="1" /> <?php echo esc_html( __( 'Yes', 'metermaid' ) ); ?>
									</label>
								</p>
							<?php } ?>
							<p>
								<label>
									<input type="radio" name="metermaid_invite_manage_system" value="0" checked="checked" /> <?php echo esc_html( __( 'No, but they can see all of the information', 'metermaid' ) ); ?>
								</label>
							</p>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="<?php echo esc_attr( __( 'Send Invite', 'metermaid' ) ); ?>" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static function UTC_NOW() {
		return gmdate( "Y-m-d H:i:s" );
	}

	public static function local_timestamp( $date, $format_string = "Y-m-d H:i:s" ) {
		$timestamp = new DateTime( $date, new DateTimeZone( 'UTC' ) );
		$timestamp->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) );
		return $timestamp->format( $format_string );
	}
}

add_action( 'init', array( 'METERMAID', 'init' ), 10 );
add_action( 'plugins_loaded', array( 'METERMAID', 'db_setup' ) );

register_activation_hook( __FILE__, array( 'METERMAID', 'sql' ) );