<?php

/*
Plugin Name: Metermaid
Description: A WordPress plugin to manage tracking water usage for community wells.
Version: 1.0
Author: Christopher Finke
*/

require_once __DIR__ . '/classes/class.meter.php';
require_once __DIR__ . '/classes/class.reading.php';

class METERMAID {
	public static function sql() {
		global $wpdb;

		$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_meters
			(
				metermaid_meter_id bigint(20) NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				location varchar(100) NOT NULL,
				PRIMARY KEY (metermaid_meter_id),
				INDEX name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);

		$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_relationships
			(
				metermaid_relationship_id bigint(20) NOT NULL AUTO_INCREMENT,
				parent_meter_id bigint(20) NOT NULL,
				child_meter_id bigint(20) NOT NULL,
				PRIMARY KEY (metermaid_relationship_id),
				UNIQUE KEY relationship (parent_meter_id, child_meter_id),
				INDEX child_meter_id (child_meter_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);

		$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_readings
			(
				metermaid_reading_id bigint(20) NOT NULL AUTO_INCREMENT,
				meter_id bigint(20) NOT NULL,
				reading varchar(20) NOT NULL,
				reading_date date NOT NULL,
				PRIMARY KEY (metermaid_reading_id),
				INDEX meter_id (meter_id),
				UNIQUE KEY reading_date (reading_date, meter_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);
	}

	public static function db_setup() {
		METERMAID::sql();
	}


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

		if ( isset( $_GET['meter'] ) ) {
			return self::meter_detail_page( $_GET['meter'] );
		}

		if ( isset( $_POST['metermaid_action'] ) ) {
			if ( 'add_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-add-meter' ) ) {
					echo 'You are not authorized to add a meter.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_meters SET name=%s, location=%s",
					$_POST['metermaid_meter_name'],
					$_POST['metermaid_meter_location']
				) );

				$meter_id = $wpdb->insert_id;

				if ( ! empty( $_POST['metermaid_parent_meters'] ) ) {
					foreach ( $_POST['metermaid_parent_meters'] as $parent_meter_id ) {
						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET parent_meter_id=%s, child_meter_id=%s ON DUPLICATE KEY UPDATE parent_meter_id=VALUES(parent_meter_id)",
							$parent_meter_id,
							$meter_id
						) );
					}
				}

				if ( ! empty( $_POST['metermaid_child_meters'] ) ) {
					foreach ( $_POST['metermaid_child_meters'] as $child_meter_id ) {
						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET child_meter_id=%s, parent_meter_id=%s ON DUPLICATE KEY UPDATE child_meter_id=VALUES(child_meter_id)",
							$child_meter_id,
							$meter_id
						) );
					}
				}

				?>
				<div class="updated">
					<p>The meter has been added.</p>
				</div>
				<?php
			} else if ( 'add_reading' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-add-reading' ) ) {
					echo 'You are not authorized to add a reading.';
					wp_die();
				}

				$reading_int = str_replace( ',', '', $_POST['metermaid_reading'] );

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_readings SET meter_id=%s, reading=%s, reading_date=%s ON DUPLICATE KEY UPDATE reading=VALUES(reading)",
					$_POST['metermaid_meter_id'],
					$reading_int,
					$_POST['metermaid_reading_date']
				) );

				?>
				<div class="updated">
					<p>The reading has been added.</p>
				</div>
				<?php
			} else if ( 'delete_meter' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-delete-meter' ) ) {
					echo 'You are not authorized to delete a meter.';
					wp_die();
				}

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_meters WHERE metermaid_meter_id=%s LIMIT 1",
					$_POST['meter_id'],
				) );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s",
					$_POST['meter_id'],
				) );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "metermaid_relationships WHERE parent_meter_id=%s OR child_meter_id=%s",
					$_POST['meter_id'],
					$_POST['meter_id']
				) );

				?>
				<div class="updated">
					<p>The meter has been deleted.</p>
				</div>
				<?php
			}
		}

		$all_meters = $wpdb->get_results(
			"SELECT m.*, r.parent_meter_id AS is_parent FROM " . $wpdb->prefix . "metermaid_meters m LEFT JOIN " . $wpdb->prefix . "metermaid_relationships r ON m.metermaid_meter_id=r.parent_meter_id GROUP BY m.metermaid_meter_id ORDER BY is_parent DESC, m.name ASC"
		);

		?>
		<div class="wrap">
			<h2>Metermaid</h2>
			<form method="post" action="">
				<h3>Add Meter</h3>
				<input type="hidden" name="metermaid_action" value="add_meter" />
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-meter' ) ); ?>" />

				<p>
					<label>
						Name
						<input type="text" name="metermaid_meter_name" />
					</label>
				</p>
				<p>
					<label>
						Location
						<input type="text" name="metermaid_meter_location" />
					</label>
				</p>
				<?php if ( ! empty( $all_meters ) ) { ?>
					<p>
						<label>
							Parent Meters
							<?php METERMAID::meter_list_selection( 'metermaid_parent_meters', true ); ?>
						</label>
					</p>
					<p>
						<label>
							Child Meters
							<?php METERMAID::meter_list_selection( 'metermaid_child_meters', true ); ?>
						</label>
					</p>
					<?php } ?>
				<input type="submit" value="Add Meter" />
			</form>
			<?php if ( ! empty( $all_meters ) ) { ?>
				<form method="post" action="">
					<h3>Add Reading</h3>
					<input type="hidden" name="metermaid_action" value="add_reading" />
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-reading' ) ); ?>" />

					<p>
						<label>
							Meter
							<?php METERMAID::meter_list_selection( 'metermaid_meter_id' ); ?>
						</label>
					</p>
					<p>
						<label>
							Date
							<input type="date" name="metermaid_reading_date" value="<?php echo esc_html( date( 'Y-m-d' ) ); ?>" />
						</label>
					</p>
					<p>
						<label>
							Reading
							<input type="text" name="metermaid_reading" value="" />
						</label>
					</p>
					<input type="submit" value="Add Reading" />
				</form>
			<?php } ?>
			<h2>All Meters</h2>
			<table class="wp-list-table widefat striped">
				<thead>
					<th></th>
					<th>Name</th>
					<th>Location</th>
					<th>Last Reading</th>
					<th>Last Reading Date</th>
					<th>gpd All Time</th>
				</thead>
				<tbody>
					<?php $last_was_parent = false; ?>
					<?php foreach ( $all_meters as $meter ) {
						$meter = new METERMAID_METER( $meter );

						if ( $meter->is_parent ) {
							$last_was_parent = true;
						} else if ( $last_was_parent ) {
							?><tr><td colspan="100"><hr /></td></tr><?php
							$last_was_parent = false;
						}

						$readings = $wpdb->get_results( $wpdb->prepare(
							"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s ORDER BY reading_date DESC",
							$meter->id
						) );

						?>
						<tr>
							<td>
								<form method="post" action="" onsubmit="if ( prompt( 'Are you sure you want to delete this entry? Type DELETE to confirm.' ) !== 'DELETE' ) { return false; } else { return true; }">
									<input type="hidden" name="metermaid_action" value="delete_meter" />
									<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-meter' ) ); ?>" />
									<input type="hidden" name="meter_id" value="<?php echo esc_attr( $meter->metermaid_meter_id ); ?>" />
									<input type="submit" value="Delete" />
								</form>
							</td>
							<td><a href="<?php echo esc_url( add_query_arg( 'meter', $meter->id ) ); ?>"><?php echo esc_html( $meter->name ); ?></a></td>
							<td><?php echo esc_html( $meter->location ); ?></td>
							<td>
								<?php if ( ! empty( $readings ) ) { ?>
									<?php echo esc_html( number_format( $readings[0]->reading, 0 ) ); ?>
								<?php } ?>
							</td>
							<td>
								<?php if ( ! empty( $readings ) ) { ?>
									<?php echo esc_html( $readings[0]->reading_date ); ?>
								<?php } ?>
							</td>
							<td>
								<?php if ( count( $readings ) > 1 ) { ?>
									<?php echo esc_html( round(
										( $readings[0]->reading - $readings[ count( $readings ) - 1 ]->reading ) // total gallons
										/
										(
											(
												  strtotime( $readings[0]->reading_date )
												- strtotime( $readings[ count( $readings ) - 1 ]->reading_date )
											)
											/ ( 24 * 60 * 60 )
										) // total days between first and last readings
									) ); ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function meter_detail_page( $meter_id ) {
		global $wpdb;

		$meter = new METERMAID_METER( $meter_id );

		?>
		<div class="wrap">
			<?php

			if ( isset( $_POST['metermaid_action'] ) ) {
				if ( 'delete_reading' == $_POST['metermaid_action'] ) {
					if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-delete-reading' ) ) {
						echo 'You are not authorized to delete a reading.';
						wp_die();
					}

					$wpdb->query( $wpdb->prepare(
						"DELETE FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_reading_id=%s LIMIT 1",
						$_POST['reading_id'],
					) );

					?>
					<div class="updated">
						<p>The reading has been deleted.</p>
					</div>
					<?php
				} else if ( 'add_reading' == $_POST['metermaid_action'] ) {
					if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-add-reading' ) ) {
						echo 'You are not authorized to add a reading.';
						wp_die();
					}

					$reading_int = str_replace( ',', '', $_POST['metermaid_reading'] );

					$wpdb->query( $wpdb->prepare(
						"INSERT INTO " . $wpdb->prefix . "metermaid_readings SET meter_id=%s, reading=%s, reading_date=%s ON DUPLICATE KEY UPDATE reading=VALUES(reading)",
						$_POST['metermaid_meter_id'],
						$reading_int,
						$_POST['metermaid_reading_date']
					) );

					?>
					<div class="updated">
						<p>The reading has been added.</p>
					</div>
					<?php
				}
			}

			if ( empty( $meter ) ) {
				?><h1>Meter Not Found</h1><?php
			} else {
				?>
				<h1>Meter Details: <?php echo esc_html( $meter->display_name() ); ?></h1>

				<form method="post" action="">
					<h3>Add Reading</h3>
					<input type="hidden" name="metermaid_action" value="add_reading" />
					<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-reading' ) ); ?>" />

					<p>
						<label>
							Date
							<input type="date" name="metermaid_reading_date" value="<?php echo esc_html( date( 'Y-m-d' ) ); ?>" />
						</label>
					</p>
					<p>
						<label>
							Reading
							<input type="text" name="metermaid_reading" value="" />
						</label>
					</p>
					<input type="submit" value="Add Reading" />
				</form>

				<table class="wp-list-table widefat striped">
					<thead>
						<th></th>
						<th>Date</th>
						<th>Reading</th>
						<th>gpd Since Last</th>
					</thead>
					<tbody>
						<?php

						foreach ( $meter->readings as $idx => $reading ) {
							?>
							<tr>
								<td>
									<form method="post" action="" onsubmit="if ( prompt( 'Are you sure you want to delete this reading? Type DELETE to confirm.' ) !== 'DELETE' ) { return false; } else { return true; }">
										<input type="hidden" name="metermaid_action" value="delete_reading" />
										<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-reading' ) ); ?>" />
										<input type="hidden" name="reading_id" value="<?php echo esc_attr( $reading->id ); ?>" />
										<input type="submit" value="Delete" />
									</form>
								</td>
								<td><?php echo esc_html( $reading->reading_date ); ?></td>
								<td><?php echo esc_html( number_format( $reading->reading, 0 ) ); ?></td>
								<td>
									<?php if ( count( $meter->readings ) > $idx + 1 ) { ?>
										<?php echo esc_html( round(
												( $reading->reading - $meter->readings[ $idx + 1 ]->reading ) // total gallons
												/
												(
													(
														  strtotime( $reading->reading_date )
														- strtotime( $meter->readings[ $idx + 1 ]->reading_date )
													)
													/ ( 24 * 60 * 60 )
												) // total days between readings
										) ); ?>
									<?php } ?>
								</td>
							</tr>
							<?php
						}

						?>
					</tbody>
				</table>
				<?php
			}

			?>
		</div>
		<?php
	}

	public static function meters() {
		global $wpdb;

		$all_meters = $wpdb->get_results(
			"SELECT m.*, r.parent_meter_id AS is_parent FROM " . $wpdb->prefix . "metermaid_meters m LEFT JOIN " . $wpdb->prefix . "metermaid_relationships r ON m.metermaid_meter_id=r.parent_meter_id GROUP BY m.metermaid_meter_id ORDER BY is_parent DESC, m.name ASC"
		);

		return $all_meters;
	}

	public static function meter_list_selection( $field_name, $multiple = false ) {
		global $wpdb;

		$all_meters = METERMAID::meters();

		$last_was_parent = false;

		?>
		<select name="<?php echo esc_attr( $field_name ); ?><?php if ( $multiple ) { ?>[]<?php } ?>"<?php if ( $multiple ) { ?> multiple<?php } ?>>
			<?php if ( ! $multiple ) { ?>
				<option value="">-- Select Meter --</option>
			<?php } ?>
			<?php

			foreach ( $all_meters as $meter ) {
				if ( $meter->is_parent ) {
					$last_was_parent = true;
				} else if ( $last_was_parent ) {
					?><option value="">--</option><?php
					$last_was_parent = false;
				}

				?>
				<option value="<?php echo esc_attr( $meter->metermaid_meter_id ); ?>"><?php echo esc_html( $meter->name ); ?><?php if ( $meter->location ) { ?> (<?php echo esc_html( $meter->location ); ?>)<?php } ?></option>
			<?php } ?>
		</select>
		<?php
	}
}

add_action( 'init', array( 'METERMAID', 'init' ) );
add_action( 'plugins_loaded', array( 'METERMAID', 'db_setup' ) );

register_activation_hook( __FILE__, array( 'METERMAID', 'sql' ) );