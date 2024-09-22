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
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_options_menu' ) );

		if ( isset( $_GET['page'] ) && 'metermaid' == $_GET['page'] ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		}

		add_action( 'admin_title', array( __CLASS__, 'edit_page_title' ) );
	}

	public static function edit_page_title() {
		global $title;

		if ( isset( $_GET['page'] ) && 'metermaid' == $_GET['page'] ) {
			if ( isset( $_GET['meter'] ) ) {
				$meter = new METERMAID_METER( $_GET['meter'] );

				if ( $meter ) {
					$title = 'Metermaid :: ' . $meter->display_name();
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
				reading int(11) NOT NULL,
				real_reading int(11) NOT NULL,
				reading_date date NOT NULL,
				PRIMARY KEY (metermaid_reading_id),
				INDEX meter_id (meter_id),
				UNIQUE KEY reading_date (reading_date, meter_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);

		$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."metermaid_supplements
			(
				metermaid_supplement_id bigint(20) NOT NULL AUTO_INCREMENT,
				meter_id bigint(20) NOT NULL,
				amount int(11) NOT NULL,
				supplement_date date NOT NULL,
				note TEXT,
				PRIMARY KEY (metermaid_supplement_id),
				INDEX meter_id (meter_id),
				UNIQUE KEY supplement_date (supplement_date, meter_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8"
		);
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

	public static function enqueue_scripts() {
		wp_enqueue_script( 'metermaid-google-charts', 'https://www.gstatic.com/charts/loader.js' );

		wp_register_style( 'metermaid-css', plugin_dir_url( __FILE__ ) . '/css/metermaid.css', array(), time() );
		wp_enqueue_style( 'metermaid-css' );

	}

	public static function admin_page() {
		global $wpdb;

		if ( isset( $_POST['metermaid_action'] ) ) {
			if ( 'update_settings' == $_POST['metermaid_action'] ) {
				if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-update-settings' ) ) {
					echo 'You are not authorized to update settings.';
					wp_die();
				}

				if ( isset( METERMAID::$units_of_measurement[ $_POST['metermaid_unit_of_measurement'] ] ) ) {
					update_option( 'metermaid_unit_of_measurement', $_POST['metermaid_unit_of_measurement'] );
				}

				$rate_interval = max( 1, intval( $_POST['metermaid_minimum_rate_interval'] ) );

				update_option( 'metermaid_minimum_rate_interval', $rate_interval );

				?>
				<div class="updated">
					<p>Settings updated.</p>
				</div>
				<?php
			} else if ( 'add_meter' == $_POST['metermaid_action'] ) {
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
					foreach ( array_filter( $_POST['metermaid_parent_meters'] ) as $parent_meter_id ) {
						$wpdb->query( $wpdb->prepare(
							"INSERT INTO ".$wpdb->prefix."metermaid_relationships SET parent_meter_id=%s, child_meter_id=%s ON DUPLICATE KEY UPDATE parent_meter_id=VALUES(parent_meter_id)",
							$parent_meter_id,
							$meter_id
						) );
					}
				}

				if ( ! empty( $_POST['metermaid_child_meters'] ) ) {
					foreach ( array_filter( $_POST['metermaid_child_meters'] ) as $child_meter_id ) {
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

				$reading_int = intval( str_replace( ',', '', $_POST['metermaid_reading'] ) );

				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . $wpdb->prefix . "metermaid_readings SET meter_id=%s, reading=%d, reading_date=%s ON DUPLICATE KEY UPDATE reading=VALUES(reading)",
					$_POST['metermaid_meter_id'],
					$reading_int,
					$_POST['metermaid_reading_date']
				) );

				$meter = new METERMAID_METER( $_POST['metermaid_meter_id'] );
				$meter->recalculate_real_readings();

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

		if ( isset( $_GET['meter'] ) ) {
			return self::meter_detail_page( $_GET['meter'] );
		} else if ( isset( $_GET['metermaid_add_meter'] ) ) {
			return self::add_meter_page();
		}

		$all_meters = self::meters();

		?>
		<div class="wrap">
			<h2>Metermaid <span>(<a href="<?php echo esc_url( add_query_arg( 'metermaid_add_meter', '1' ) ); ?>">Add Meter</a>)</span></h2>
			<?php if ( ! empty( $all_meters ) ) { ?>
				<?php self::add_reading_form(); ?>
			<?php } ?>
			<?php self::add_settings_form(); ?>
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
						if ( $meter->is_parent() ) {
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
									<input type="hidden" name="meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
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
										( $readings[0]->real_reading - $readings[ count( $readings ) - 1 ]->real_reading ) // total gallons
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

	public static function add_meter_page() {
		global $wpdb;

		$all_meters = METERMAID::meters();

		?>
		<div class="wrap">
			<form method="post" action="">
				<h1>Add Meter <span>(<a href="?page=metermaid">Back to main</a>)</span></h1>
				<input type="hidden" name="metermaid_action" value="add_meter" />
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-meter' ) ); ?>" />

				<table class="form-table">
					<tr>
						<th scope="row">
							Name
						</th>
						<td>
							<input type="text" name="metermaid_meter_name" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							Location
						</th>
						<td>
							<input type="text" name="metermaid_meter_location" />
						</td>
					</tr>
					<?php if ( ! empty( $all_meters ) ) { ?>
						<tr>
							<th scope="row">
								Parent Meters
							</th>
							<td>
								<?php METERMAID::meter_list_selection( 'metermaid_parent_meters', true ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								Child Meters
							</th>
							<td>
								<?php METERMAID::meter_list_selection( 'metermaid_child_meters', true ); ?>
							</td>
						</tr>
					<?php } ?>
					<tr>
						<th scope="row"></th>
						<td>
							<input class="button button-primary" type="submit" value="Add Meter" />
						</td>
					</tr>
				</table>
			</form>
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

					$meter = new METERMAID_METER( $_GET['meter'] );
					$meter->recalculate_real_readings();

					?>
					<div class="updated">
						<p>The reading has been deleted.</p>
					</div>
					<?php
				} else if ( 'delete_supplement' == $_POST['metermaid_action'] ) {
					if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-delete-supplement' ) ) {
						echo 'You are not authorized to delete a supplement.';
						wp_die();
					}

					$wpdb->query( $wpdb->prepare(
						"DELETE FROM " . $wpdb->prefix . "metermaid_supplements WHERE metermaid_supplement_id=%s LIMIT 1",
						$_POST['supplement_id'],
					) );

					?>
					<div class="updated">
						<p>The supplement has been deleted.</p>
					</div>
					<?php
				} else if ( 'add_supplement' == $_POST['metermaid_action'] ) {
					if ( ! wp_verify_nonce( $_POST['nonce'], 'metermaid-add-supplement' ) ) {
						echo 'You are not authorized to add a supplement.';
						wp_die();
					}

					$amount_int = intval( str_replace( ',', '', $_POST['metermaid_supplement_amount'] ) );

					$wpdb->query( $wpdb->prepare(
						"INSERT INTO " . $wpdb->prefix . "metermaid_supplements SET meter_id=%s, amount=%d, supplement_date=%s, note=%s ON DUPLICATE KEY UPDATE amount=VALUES(amount)",
						$_POST['metermaid_meter_id'],
						$amount_int,
						$_POST['metermaid_supplement_date'],
						$_POST['metermaid_supplement_note']
					) );

					?>
					<div class="updated">
						<p>The supplement has been added.</p>
					</div>
					<?php
				}
			}

			if ( empty( $meter ) ) {
				?><h1>Meter Not Found</h1><?php
			} else {
				?>
				<h1>Meter Details: <?php echo esc_html( $meter->display_name() ); ?> <span>(<a href="<?php echo esc_url( remove_query_arg( 'meter' ) ); ?>">Back to all meters</a>)</span></h1>

				<?php self::add_reading_form( $meter->id ); ?>

				<?php self::add_supplement_form( $meter->id ); ?>

				<?php $meter->year_chart(); ?>
				<?php $meter->ytd_chart(); ?>

				<?php if ( $meter->is_parent() ) { ?>
					<?php $meter->children_chart(); ?>
				<?php } ?>

				<?php

				$supplements = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM " . $wpdb->prefix . "metermaid_supplements WHERE meter_id=%d ORDER BY supplement_date DESC",
					$meter->id
				) );
				$children_readings = $meter->children_readings();
				$meter_readings = $meter->readings();

				?>
				<table class="wp-list-table widefat striped">
					<thead>
						<th></th>
						<th>Date</th>
						<th>Reading</th>
						<th>Real Reading</th>
						<?php if ( $meter->is_parent() ) { ?>
							<th>Children Reading</th>
						<?php } ?>
						<th>gpd Since Last (At least <?php echo esc_html( METERMAID::get_option( 'minimum_rate_interval' ) ); ?> days)</th>
						<th>Gallons Since Last</th>
					</thead>
					<tbody>
						<?php

						foreach ( $meter_readings as $idx => $reading ) {
							?>
							<tr>
								<td>
									<?php if ( $reading->id ) { ?>
										<form method="post" action="" onsubmit="return confirm( 'Are you sure you want to delete this reading?' );">
											<input type="hidden" name="metermaid_action" value="delete_reading" />
											<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-reading' ) ); ?>" />
											<input type="hidden" name="reading_id" value="<?php echo esc_attr( $reading->id ); ?>" />
											<input type="submit" value="Delete" />
										</form>
									<?php } ?>
								</td>
								<td><?php echo esc_html( $reading->reading_date ); ?></td>
								<td><?php echo esc_html( number_format( $reading->reading, 0 ) ); ?></td>
								<td><?php echo esc_html( number_format( $reading->real_reading, 0 ) ); ?></td>
								<?php if ( $meter->is_parent() ) { ?>
									<td>
										<?php

										if ( isset( $children_readings[ $reading->reading_date ] ) ) {
											echo esc_html( number_format( $children_readings[ $reading->reading_date ] ), 0 );

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
																echo '<span title="The child meters of this meter read higher than expected. Either they are overreporting, or this meter is underreporting." class="metermaid-surplus">(+' . esc_html( number_format( $difference, 0 ) ) . ' / ' . esc_html( $difference_percent ) . '%; ' . number_format( $difference_per_day, 0 ) . ' gpd)</span>';
															} else if ( $difference < 0 ) {
																echo '<span title="The child meters of this meter read lower than expected. Either they are underreporting, or this meter is overreporting." class="metermaid-deficit">(' . esc_html( number_format( $difference, 0 ) ) . ' / ' . esc_html( $difference_percent ) . '%; ' . number_format( $difference_per_day, 0 ) . ' gpd)</span>';
															} else {
																echo '<span title="" class="metermaid-balanced">(0%)</span>';
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
									<?php echo esc_html( self::gpd( $reading, $meter->readings(), METERMAID::get_option( 'minimum_rate_interval' ) ) ); ?>
								</td>
								<td>
									<?php

									if ( isset( $meter_readings[ $idx + 1 ] ) ) {
										echo esc_html( number_format( $reading->real_reading - $meter_readings[ $idx + 1 ]->real_reading, 0 ) );
									}

									?>
								</td>
							</tr>
							<?php
						}

						?>
					</tbody>
				</table>
				<h2>Supplemented Water</h2>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th></th>
							<th>Date</th>
							<th>Supplement</th>
							<th>Note</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $supplements as $supplement ) { ?>
							<tr>
								<td>
									<form method="post" action="" onsubmit="return confirm( 'Are you sure you want to delete this supplement?' );">
										<input type="hidden" name="metermaid_action" value="delete_supplement" />
										<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-supplement' ) ); ?>" />
										<input type="hidden" name="supplement_id" value="<?php echo esc_attr( $supplement->metermaid_supplement_id ); ?>" />
										<input type="submit" value="Delete" />
									</form>
								</td>
								<td><?php echo esc_html( $supplement->supplement_date ); ?></td>
								<td><?php echo number_format( $supplement->amount, 0 ); ?></td>
								<td><?php echo esc_html( $supplement->note ); ?></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<?php

				if ( $meter->is_parent() ) {
					echo '<h2>Child Meters</h2>';
					echo '<table class="widefat striped wp-list-table">';
					echo '<thead><tr><th></th><th>Child Meter</th></td></thead>';
					echo '<tbody>';
					$children = $meter->children;

					foreach ( $children as $child_id ) {
						$child = new METERMAID_METER( $child_id );

						echo '<tr><td></td><td><a href="' . add_query_arg( 'meter', $child->id ) . '">' . esc_html( $child->display_name() ) . '</a></td></tr>';
					}

					echo '</tbody></table>';
				}
			}

			?>
		</div>
		<?php
	}

	public static function meters() {
		global $wpdb;

		$meter_rows = $wpdb->get_results(
			"SELECT m.*, r.parent_meter_id AS is_parent FROM " . $wpdb->prefix . "metermaid_meters m LEFT JOIN " . $wpdb->prefix . "metermaid_relationships r ON m.metermaid_meter_id=r.parent_meter_id GROUP BY m.metermaid_meter_id ORDER BY is_parent DESC, m.name ASC"
		);

		$all_meters = array();

		foreach ( $meter_rows as $meter_row ) {
			$all_meters[] = new METERMAID_METER( $meter_row );
		}

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
				if ( $meter->is_parent() ) {
					$last_was_parent = true;
				} else if ( $last_was_parent ) {
					?><option value="">--</option><?php
					$last_was_parent = false;
				}

				?>
				<option value="<?php echo esc_attr( $meter->id ); ?>"><?php echo esc_html( $meter->display_name() ); ?></option>
			<?php } ?>
		</select>
		<?php
	}

	public static function add_reading_form( $meter_id = null ) {
		?>
		<form method="post" action="">
			<h2>Add Reading</h2>

			<input type="hidden" name="metermaid_action" value="add_reading" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-reading' ) ); ?>" />

			<?php if ( $meter_id ) { ?>
				<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter_id ); ?>" />
			<?php } ?>


			<table class="form-table">
				<?php if ( ! $meter_id ) { ?>
					<tr>
						<th scope="row">
							Meter
						</th>
						<td>
							<?php METERMAID::meter_list_selection( 'metermaid_meter_id' ); ?>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<th scope="row">
						Date
					</th>
					<td>
						<input type="date" name="metermaid_reading_date" value="<?php echo esc_html( current_datetime()->format( 'Y-m-d' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						Reading
					</th>
					<td>
						<input type="text" name="metermaid_reading" value="" />
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="Add Reading" />
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
			if ( $_reading->reading_date > date( "Y-m-d", strtotime( $reading->reading_date ) - ( 24 * 60 * 60 * ( $minimum_days ) ) ) ) {
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

	public static function add_settings_form() {
		?>
		<form method="post" action="">
			<h2>Settings</h2>

			<input type="hidden" name="metermaid_action" value="update_settings" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-update-settings' ) ); ?>" />

			<table class="form-table">
				<tr>
					<th scope="row">
						Unit of measurement
					</th>
					<td>
						<select name="metermaid_unit_of_measurement">
							<?php foreach ( METERMAID::$units_of_measurement as $unit => $unit_meta ) { ?>
								<option value="<?php echo esc_attr( $unit ); ?>" <?php if ( $unit == METERMAID::get_option( 'unit_of_measurement' ) ) { ?> selected="selected"<?php } ?>><?php echo esc_html( $unit_meta['plural'] ); ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						Minimum rate interval (in days)
					</th>
					<td>
						<input type="number" name="metermaid_minimum_rate_interval" value="<?php echo esc_attr( METERMAID::get_option( 'minimum_rate_interval' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="Update Settings" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	public static $units_of_measurement = array(
		'gallon' => array(
			'singular' => 'Gallon',
			'plural' => 'Gallons',
			'rate_abbreviation' => 'gpd',
		)
	);

	public static $defaults = array(
		'unit_of_measurement' => 'gallon',
		'minimum_rate_interval' => 7,
	);

	public static function get_option( $option_name ) {
		$default_value = METERMAID::$defaults[ $option_name ] ?? '';

		return get_option( 'metermaid_' . $option_name, $default_value );
	}

	public static function add_supplement_form( $meter_id ) {
		?>
		<form method="post" action="">
			<h2>Add Supplement</h2>

			<input type="hidden" name="metermaid_action" value="add_supplement" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-add-supplement' ) ); ?>" />
			<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter_id ); ?>" />


			<table class="form-table">
				<tr>
					<th scope="row">
						Date
					</th>
					<td>
						<input type="date" name="metermaid_supplement_date" value="<?php echo esc_html( current_datetime()->format( 'Y-m-d' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						Amount
					</th>
					<td>
						<input type="text" name="metermaid_supplement_amount" value="" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						Note
					</th>
					<td>
						<textarea name="metermaid_supplement_note"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input class="button button-primary" type="submit" value="Add Supplement" />
					</td>
				</tr>
			</table>
		</form>
		<?php
	}
}

add_action( 'init', array( 'METERMAID', 'init' ) );
add_action( 'plugins_loaded', array( 'METERMAID', 'db_setup' ) );

register_activation_hook( __FILE__, array( 'METERMAID', 'sql' ) );