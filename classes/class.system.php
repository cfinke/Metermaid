<?php

class METERMAID_SYSTEM {
	public $id;
	public $name;
	public $unit;
	public $rate_interval;
	public $added_by;

	private $_all_meters = null;
	private $_readable_meters = null;
	private $_writeable_meters = null;

	public function __construct( $system_id_or_row = null ) {
		global $wpdb;

		if ( ! $system_id_or_row ) {
			return false;
		}

		if ( is_numeric( $system_id_or_row ) ) {
			$system_id_or_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_systems WHERE metermaid_system_id=%s LIMIT 1", $system_id_or_row ) );

			if ( ! $system_id_or_row ) {
				return false;
			}
		}

		$this->id = $system_id_or_row->metermaid_system_id;
		$this->name = $system_id_or_row->name;
		$this->unit = $system_id_or_row->unit;
		$this->rate_interval = $system_id_or_row->rate_interval;
		$this->added_by = $system_id_or_row->added_by;
	}

	/**
	 * A nicer way than "if ( $system->id )" to check for whether this object represents a found system, or if the instantiation failed.
	 *
	 * Call $system(), like a function, to run this method.
	 */
	public function __invoke() {
		return !! $this->id;
	}

	/**
	 * Hide some expensive calls behind a cached __get().
	 */
	public function __get( $key ) {
		global $wpdb;

		if ( 'all_meters' == $key ) {
			if ( ! is_null( $this->_meters ) ) {
				return $this->_meters;
			}

			$meter_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT m.*, r.parent_meter_id AS is_parent FROM " . $wpdb->prefix . "metermaid_meters m LEFT JOIN " . $wpdb->prefix . "metermaid_relationships r ON m.metermaid_meter_id=r.parent_meter_id WHERE m.metermaid_system_id=%s GROUP BY m.metermaid_meter_id ORDER BY is_parent DESC, m.name ASC",
				$this->id
			) );

			$this->_meters = array();

			foreach ( $meter_rows as $meter_row ) {
				$this->_meters[] = new METERMAID_METER( $meter_row );
			}

			return $this->_meters;
		} else if ( 'readable_meters' == $key ) {
			if ( ! is_null( $this->_readable_meters ) ) {
				return $this->_readable_meters;
			}

			$meters = $this->all_meters;

			$this->_readable_meters = array();

			foreach ( $meters as $meter ) {
				if ( current_user_can( 'metermaid-view-meter', $meter->id ) ) {
					$this->_readable_meters[] = $meter;
				}
			}

			return $this->_readable_meters;
		} else if ( 'writeable_meters' == $key ) {
			if ( ! is_null( $this->_writeable_meters ) ) {
				return $this->_writeable_meters;
			}

			$meters = $this->all_meters;

			$this->_writeable_meters = array();

			foreach ( $meters as $meter ) {
				if ( current_user_can( 'metermaid-add-reading', $meter->id ) ) {
					$this->_writeable_meters[] = $meter;
				}
			}

			return $this->_writeable_meters;
		}

		return null;
	}

	public function measurement() {
		$default_unit = 'gallon';

		return METERMAID::$units_of_measurement[ $this->unit ] ?? METERMAID::$units_of_measurement[ $default_unit ];
	}

	public function display_meter_table( $show_delete = false ) {
		global $wpdb;

		$meters = $this->readable_meters;

		if ( ! empty( $meters ) ) {
			?>
			<table class="wp-list-table widefat striped">
				<thead>
					<th></th>
					<th><?php echo esc_html( __( 'Meter', 'metermaid' ) ); ?></th>
					<th><?php echo esc_html( __( 'Last Reading', 'metermaid' ) ); ?></th>
					<th><?php echo esc_html( __( 'Last Reading Date', 'metermaid' ) ); ?></th>
					<th><?php echo esc_html( sprintf( __( '%s YTD', 'metermaid' ), $this->measurement()['plural'] ) ); ?></th>
					<th><?php echo esc_html( sprintf( __( '%1$s in %2$s', 'metermaid' ), $this->measurement()['plural'], date( "Y" ) - 1 ) ); ?></th>
					<th>
						<?php echo esc_html( sprintf( __( '%s All Time', 'metermaid' ), strtoupper( $this->measurement()['rate_abbreviation'] ) ) ); ?>
					</th>
				</thead>
				<tbody>
					<?php $last_was_parent = false; ?>
					<?php

					foreach ( $meters as $meter ) {
						if ( $meter->is_parent() ) {
							$last_was_parent = true;
						} else if ( $last_was_parent ) {
							?><tr><td colspan="100"><hr /></td></tr><?php
							$last_was_parent = false;
						}

						$readings = $wpdb->get_results( $wpdb->prepare(
							"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_meter_id=%s ORDER BY reading_date DESC",
							$meter->id
						) );

						?>
						<tr>
							<td>
								<?php if ( $show_delete && current_user_can( 'metermaid-delete-meter', $meter->id ) ) { ?>
									<form method="post" action="" onsubmit="return confirm( metermaid_i18n.meter_delete_confirm );">
										<input type="hidden" name="metermaid_action" value="delete_meter" />
										<input type="hidden" name="metermaid_nonce" value="<?php echo esc_attr( wp_create_nonce( 'metermaid-delete-meter' ) ); ?>" />
										<input type="hidden" name="metermaid_meter_id" value="<?php echo esc_attr( $meter->id ); ?>" />
										<input type="submit" class="button button-secondary" value="<?php echo esc_attr( __( 'Delete Meter', 'metermaid' ) ); ?>" />
									</form>
								<?php } ?>
							</td>
							<td><a href="<?php echo esc_url( add_query_arg( 'metermaid_meter_id', $meter->id ) ); ?>"><?php echo esc_html( $meter->name ?: __( '[Unnamed]' ) ); ?></a></td>
							<td>
								<?php if ( ! empty( $readings ) ) { ?>
									<?php echo esc_html( number_format( $readings[0]->reading, 0 ) ); ?>
								<?php } ?>
							</td>
							<td>
								<?php if ( ! empty( $readings ) ) { ?>
									<?php echo esc_html( date( get_option( 'date_format' ), strtotime( $readings[0]->reading_date ) ) ); ?>
								<?php } ?>
							</td>
							<td>
								<?php

								$gallons_ytd = $meter->gallons_ytd();
								$gallons_last_year_at_this_time = $meter->estimate_real_reading_on_this_date_last_year();

								echo esc_html( number_format( $gallons_ytd ?? 0 ) );

								if ( $gallons_ytd && $gallons_last_year_at_this_time ) {
									$tooltip_text = sprintf(
										__( 'Versus %1$s %2$s last year at this point', 'metermaid' ),
										number_format( $gallons_last_year_at_this_time ),
										$this->measurement()['plural']
									);

									if ( $gallons_ytd < $gallons_last_year_at_this_time ) {
										$percent_decrease = round( ( ( $gallons_last_year_at_this_time - $gallons_ytd ) / $gallons_last_year_at_this_time ) * 100 );

										if ( $percent_decrease >= 3 ) {

											echo ' <span class="metermaid-surplus" title="' . esc_attr( $tooltip_text ) . '">(-' . $percent_decrease . '%)</span>';
										}
									} else {
										$percent_increase = round( ( ( $gallons_ytd - $gallons_last_year_at_this_time ) / $gallons_last_year_at_this_time ) * 100 );

										if ( $percent_increase >= 3 ) {
											echo ' <span class="metermaid-deficit" title="' . esc_attr( $tooltip_text ) . '">(+' . $percent_increase . '%)</span>';
										}
									}
								}

								?>
							</td>
							<td><?php echo esc_html( number_format( $meter->gallons_last_year() ?? 0 ) ); ?></td>
							<td>
								<?php if ( count( $readings ) > 1 ) { ?>
									<?php echo esc_html(
										number_format(
											round(
												( $readings[0]->real_reading - $readings[ count( $readings ) - 1 ]->real_reading ) // total gallons
												/
												(
													(
														  strtotime( $readings[0]->reading_date )
														- strtotime( $readings[ count( $readings ) - 1 ]->reading_date )
													)
													/ ( 24 * 60 * 60 )
												) // total days between first and last readings
											),
											0
										)
									); ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php

		}
	}
}