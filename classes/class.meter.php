<?php

class METERMAID_METER {
	public $id;
	public $system_id;
	public $name;
	public $status;
	public $contact_name;
	public $contact_email;
	public $contact_phone;

	private $_readings = null;
	private $_supplements = null;

	private $_children = null;
	private $_parents = null;
	private $_children_readings = null;
	private $_system = null;

	public function __construct( $meter_id_or_row ) {
		global $wpdb;

		if ( ! $meter_id_or_row ) {
			// It's possible this was called with a null meter ID because
			// it was an optional argument to a function, and we instantiate
			// a meter object to check if it exists no matter what.
			return;
		}

		if ( is_numeric( $meter_id_or_row ) ) {
			$meter_id_or_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_meters WHERE metermaid_meter_id=%s LIMIT 1", $meter_id_or_row ) );

			if ( ! $meter_id_or_row ) {
				return false;
			}
		}

		$this->id = $meter_id_or_row->metermaid_meter_id;
		$this->system_id = $meter_id_or_row->metermaid_system_id;
		$this->name = $meter_id_or_row->name;
		$this->status = $meter_id_or_row->status;
		$this->contact_name = $meter_id_or_row->contact_name;
		$this->contact_email = $meter_id_or_row->contact_email;
		$this->contact_phone = $meter_id_or_row->contact_phone;
	}

	/**
	 * A nicer way than "if ( $meter->id )" to check for whether this object represents a found meter, or if the instantiation failed.
	 *
	 * Call $meter(), like a function, to run this method.
	 */
	public function __invoke() {
		return !! $this->id;
	}

	public function add_reading( $reading, $when, $user_id = null ) {
		global $wpdb;

		$reading_int = intval( str_replace( ',', '', $reading ) );
		$when = date( "Y-m-d", strtotime( $when ) );

		// @todo Don't allow one user to overwrite another user's reading.
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO " . $wpdb->prefix . "metermaid_readings SET metermaid_meter_id=%s, reading=%d, reading_date=%s, added=NOW(), added_by=%d ON DUPLICATE KEY UPDATE reading=VALUES(reading), added=NOW()",
			$this->id,
			$reading_int,
			$when,
			$user_id ?? get_current_user_id()
		) );

		$this->recalculate_real_readings();

		return true;
	}

	/**
	 * A list of meter readings for this meter.
	 */
	public function readings( $include_projected = false ) {
		global $wpdb;

		if ( ! is_null( $this->_readings ) ) {
			return $this->_readings;
		}

		$readings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_meter_id=%s ORDER BY reading_date DESC",
			$this->id
		) );

		if ( ! empty( $readings ) ) {
			if ( $this->status == METERMAID_STATUS_INACTIVE ) {
				// For inactive meters, set the current reading to the last known reading.
				$current_reading = clone $readings[0];
				$current_reading->reading_date = date( "Y-m-d" );
				$current_reading->metermaid_reading_id = null;

				array_unshift( $readings, $current_reading );
			}
		}

		// If there are any two identical readings on non-adjacent days, fill in the days between with that reading too,
		// if we requested $include_projected. Sometimes, like with a very inactive meter, this can result in hundreds
		// of identical readings in a reading table, which is overkill.
		$last_reading = INF;
		$last_reading_date = date( "Y-m-d", strtotime( "tomorrow" ) );

		$readings_to_add = array();

		foreach ( $readings as $reading ) {
			if ( $include_projected && ( $reading->real_reading == $last_reading ) ) {
				do {
					$new_reading_date = date( "Y-m-d", strtotime( $last_reading_date ) - ( 24 * 60 * 60 ) );

					if ( $new_reading_date > date( "Y-m-d", strtotime( $reading->reading_date ) + ( 24 * 60 * 60 ) ) ) {
						$new_reading = clone $reading;
						unset( $new_reading->metermaid_reading_id );
						$new_reading->reading_date = $new_reading_date;
						$readings_to_add[] = $new_reading;
						$last_reading_date = $new_reading_date;
					} else {
						break;
					}
				 } while ( true );
			}

			$last_reading = $reading->real_reading;
			$last_reading_date = $reading->reading_date;
		}

		if ( ! empty( $readings_to_add ) ) {
			foreach ( $readings_to_add as $reading_to_add ) {
				$readings[] = $reading_to_add;
			}

			usort( $readings, array( __CLASS__, 'sort_readings' ) );
		}

		$reading_objects = array();

		foreach ( $readings as $reading ) {
			$reading_objects[] = new METERMAID_READING( $reading );
		}

		$this->_readings = $reading_objects;

		return $reading_objects;
	}

	/**
	 * By default, sort the readings in reverse chronological order.
	 */
	public static function sort_readings( $a, $b ) {
		if ( $a->reading_date < $b->reading_date ) {
			return 1;
		} else if ( $a->reading_date > $b->reading_date ) {
			return -1;
		}

		return 0;
	}

	/**
	 * Hide some expensive calls behind a cached __get().
	 */
	public function __get( $key ) {
		global $wpdb;

		if ( 'children' == $key || 'parents' == $key ) {
			if ( 'children' == $key && ! is_null( $this->_children ) ) {
				return $this->_children;
			} else if ( 'parents' == $key && ! is_null( $this->_parents ) ) {
				return $this->_parents;
			}

			$relationships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_relationships WHERE parent_meter_id=%s OR child_meter_id=%s", $this->id, $this->id ) );

			$this->_children = array();
			$this->_parents = array();

			foreach ( $relationships as $relationship ) {
				if ( $relationship->parent_meter_id == $this->id ) {
					$this->_children[] = $relationship->child_meter_id;
				} else if ( $relationship->child_meter_id == $this->id ) {
					$this->_parents[] = $relationship->parent_meter_id;
				}
			}

			return $this->__get( $key );
		} else if ( 'system' == $key ) {
			if ( ! is_null( $this->_system ) ) {
				return $this->_system;
			}

			$this->_system = new METERMAID_SYSTEM( $this->system_id );
			return $this->_system;
		} else if ( 'supplements' == $key ) {
			if ( ! is_null( $this->_supplements ) ) {
				return $this->_supplements;
			}

			$this->_supplements = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM " . $wpdb->prefix . "metermaid_supplements WHERE metermaid_meter_id=%d ORDER BY supplement_date DESC",
				$this->id
			) );

			return $this->_supplements;
		}

		return null;
	}

	/**
	 * @return bool
	 */
	public function is_parent() {
		$children = $this->children;

		if ( ! empty( $children ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Output the year comparison chart.
	 */
	public function output_year_chart() {
		$readings = $this->readings();

		if ( count( $readings ) < 2 ) {
			return;
		}

		$chart_id = 'metermaid-year-chart-' . $this->id;

		$readings = array_reverse( $readings );

		$first_year = date( "Y", strtotime( $readings[1]->reading_date ) ); // The first date at which we can determine gpd.

		$data = array();

		$header_row = array( "Date" );

		for ( $year = $first_year; $year <= date( "Y" ); $year++ ) {
			$header_row[] = array( 'label' => (string) $year, 'type' => 'number' );
		}

		$data[] = $header_row;

		for ( $i = 0; $i <= 365; $i++ ) {
			$date_label = date( "F j", strtotime( "January 1, " . $year ) + ( $i * 60 * 60 * 24 ) );
			$data_row = array_fill( 0, count( $header_row ), null );
			$data_row[0] = $date_label;
			$data[ $date_label ] = $data_row;
		}

		$trailing_average_duration = $this->system->rate_interval; // Number of days minimum to calculate gpd average.

		foreach ( $readings as $idx => $reading ) {
			if ( $idx == 0 ) {
				continue;
			}

			$previous_reading = $reading;
			$previous_idx = $idx - 1;

			do {
				$previous_reading = $readings[ $previous_idx ];
				$days_since_previous_reading = ( strtotime( $reading->reading_date ) - strtotime( $previous_reading->reading_date ) ) / ( 24 * 60 * 60 );

				$previous_idx--;
			} while ( $previous_idx >= 0 && $days_since_previous_reading < $trailing_average_duration );

			$gallons = $reading->real_reading - $previous_reading->real_reading;
			$gpd = round( $gallons / $days_since_previous_reading );

			$row_key = date( "F j", strtotime( $reading->reading_date ) );
			$col_key = date( "Y", strtotime( $reading->reading_date ) ) - $first_year + 1;

			$data[ $row_key ][ $col_key ] = $gpd;

			if ( $row_key == "January 1" && $col_key > 1 ) {
				// Also treat this as December 31 from previous year so the chart lines go to the end.
				$data[ "December 31" ][ $col_key - 1 ] = $gpd;
			}
		}

		?>
		<script type="text/javascript">
			google.charts.load( 'current', { 'packages' : [ 'corechart' ] } );
			google.charts.setOnLoadCallback( drawChart );
			metermaid.registerTabBlocker( 'year_chart' );

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode(
							sprintf(
								__( '%1$s Per Day (over at least %2$d days)', 'metermaid' ),
								$this->system->measurement()['plural'],
								intval( $this->system->rate_interval )
							)
						); ?>,
					legend: { position: 'bottom' },
					interpolateNulls : true,
					vAxis : {
						viewWindow : {
							min: 0
						}
					}
				};

				var chart = new google.visualization.LineChart( document.getElementById( <?php echo json_encode( $chart_id ); ?> ) );
				chart.draw( data, options );

				metermaid.clearTabBlocker( 'year_chart' );
			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function output_children_chart() {
		$chart_id = 'metermaid-child-chart-' . $this->id;

		$data = array();

		$header_row = array( "Date" );
		$header_row[] = array( 'label' => $this->name, 'type' => 'number' );
		$header_row[] = array( 'label' => "Children", 'type' => 'number' );

		$child_objects = array();

		foreach ( $this->children as $child_id ) {
			$meter = new METERMAID_METER( $child_id );
			$child_objects[] = $meter;
		}

		$data[] = $header_row;

		for ( $i = 364; $i >= 0; $i-- ) {
			$date_label = date( "F j", strtotime( "-" . $i . " days" ) );
			$data_row = array_fill( 0, count( $header_row ), null );
			$data_row[0] = $date_label;
			$data[ $date_label ] = $data_row;
		}

		$trailing_average_duration = $this->system->rate_interval; // Number of days minimum to calculate gpd average.

		$meters_to_chart = array();
		$meters_to_chart[] = $this;

		$readings = array_reverse( $this->readings() );

		foreach ( $readings as $idx => $reading ) {
			if ( $idx == 0 ) {
				continue;
			}

			if ( $reading->reading_date < date( "Y-m-d", strtotime( "-364 days" ) ) ) {
				continue;
			}

			$previous_reading = $reading;
			$previous_idx = $idx - 1;

			do {
				$previous_reading = $readings[ $previous_idx ];
				$days_since_previous_reading = ( strtotime( $reading->reading_date ) - strtotime( $previous_reading->reading_date ) ) / ( 24 * 60 * 60 );

				$previous_idx--;
			} while ( $previous_idx >= 0 && $days_since_previous_reading < $trailing_average_duration );

			$gallons = $reading->real_reading - $previous_reading->real_reading;
			$gpd = round( $gallons / $days_since_previous_reading );

			$row_key = date( "F j", strtotime( $reading->reading_date ) );
			$data[ $row_key ][1] += $gpd;
		}

		$child_readings = array();

		foreach ( $child_objects as $idx => $meter ) {
			$readings = array_reverse( $meter->readings() );

			foreach ( $readings as $idx => $reading ) {
				if ( $idx == 0 ) {
					continue;
				}

				if ( $reading->reading_date < date( "Y-m-d", strtotime( "-364 days" ) ) ) {
					continue;
				}

				$previous_reading = $reading;
				$previous_idx = $idx - 1;

				do {
					$previous_reading = $readings[ $previous_idx ];
					$days_since_previous_reading = ( strtotime( $reading->reading_date ) - strtotime( $previous_reading->reading_date ) ) / ( 24 * 60 * 60 );

					$previous_idx--;
				} while ( $previous_idx >= 0 && $days_since_previous_reading < $trailing_average_duration );

				$gallons = $reading->real_reading - $previous_reading->real_reading;
				$gpd = round( $gallons / $days_since_previous_reading );

				$row_key = date( "F j", strtotime( $reading->reading_date ) );

				if ( ! isset( $child_readings[ $row_key ] ) ) {
					$child_readings[ $row_key ] = array();
				}

				$child_readings[ $row_key ][] = $gpd;
			}
		}

		foreach ( $child_readings as $row_key => $reading_values ) {
			if ( count( $reading_values ) == count( $child_objects ) ) {
				$data[ $row_key ][2] = array_sum( $reading_values );
			}
		}

		?>
		<script type="text/javascript">
			google.charts.load( 'current', { 'packages' : [ 'corechart' ] } );
			google.charts.setOnLoadCallback( drawChart );
			metermaid.registerTabBlocker( 'children_chart' );

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode(
							sprintf(
								__( '%1$s Per Day (Children, over at least %2$d days)', 'metermaid' ),
								$this->system->measurement()['plural'],
								intval( $this->system->rate_interval )
							)
						); ?>,
					legend: { position: 'bottom' },
					vAxis : {
						viewWindow : {
							min: 0
						}
					}
				};

				var chart = new google.visualization.LineChart( document.getElementById( <?php echo json_encode( $chart_id ); ?> ) );
				chart.draw( data, options );

				metermaid.clearTabBlocker( 'children_chart' );

			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function children_readings() {
		if ( is_null( $this->_children_readings ) ) {
			$this->_children_readings = array();

			if ( $this->is_parent() ) {
				$master_reading_dates = array();

				foreach ( $this->readings() as $reading ) {
					$master_reading_dates[] = $reading->reading_date;
				}

				$children = $this->children;

				$this->_children_readings = array();

				foreach ( $children as $child_id ) {
					$meter = new METERMAID_METER( $child_id );
					$readings = $meter->readings( true );

					foreach ( $readings as $reading ) {
						// We only care about dates on which we read the master meter too.
						if ( in_array( $reading->reading_date, $master_reading_dates ) ) {
							if ( ! isset( $this->_children_readings[ $reading->reading_date ] ) ) {
								$this->_children_readings[ $reading->reading_date ] = array();
							}

							$this->_children_readings[ $reading->reading_date ][] = $reading->real_reading;
						}
					}
				}

				foreach ( $this->_children_readings as $date => $children_readings ) {
					if ( count( $children_readings ) != count( $children ) ) {
						unset( $this->_children_readings[ $date ] );
					} else {
						$this->_children_readings[ $date ] = array_sum( $this->_children_readings[ $date ] );
					}
				}
			}

			krsort( $this->_children_readings );
		}

		return $this->_children_readings;
	}

	/**
	 * Grab the sum of all child meter readings, but only for days on which every meter was read.
	 */
	public function children_reading( $reading_date ) {
		$children_readings = $this->children_readings();

		return $children_readings[ $reading_date ] ?? false;
	}

	public function output_ytd_chart() {
		$readings = $this->readings();

		if ( empty( $readings ) ) {
			return;
		}

		$chart_id = 'metermaid-ytd-chart-' . $this->id;

		$readings = array_reverse( $readings );

		$first_year = date( "Y", strtotime( $readings[0]->reading_date ) );

		$data = array();

		$header_row = array( "Date" );

		for ( $year = $first_year; $year <= date( "Y" ); $year++ ) {
			$header_row[] = array( 'label' => (string) $year, 'type' => 'number' );
		}

		$data[] = $header_row;

		for ( $i = 0; $i <= 365; $i++ ) {
			$date_label = date( "F j", strtotime( "January 1, " . $year ) + ( $i * 60 * 60 * 24 ) );
			$data_row = array_fill( 0, count( $header_row ), null );
			$data_row[0] = $date_label;
			$data[ $date_label ] = $data_row;
		}

		$last_reading_year = 0;
		$year_beginning_reading = 0;
		$last_reading = null;

		foreach ( $readings as $idx => $reading ) {
			$this_reading_year = date( "Y", strtotime( $reading->reading_date ) );

			$row_key = date( "F j", strtotime( $reading->reading_date ) );
			$col_key = $this_reading_year - $first_year + 1;

			if ( $last_reading && $this_reading_year != $last_reading_year ) {
				for ( $years_ago = 1; $years_ago <= ( $this_reading_year - $last_reading_year ); $years_ago++ ) {
					if ( ! isset( $data[ 'December 31' ][ $col_key - $years_ago ] ) ) {
						// Add an estimated end value for the previous year to make the chart more readable.
						if ( isset( $data['January 1'][ $col_key - $years_ago ] ) ) {
							$data[ 'December 31' ][ $col_key - $years_ago ] = $this->estimate_real_reading_at_date( ( $this_reading_year - $years_ago ) . '-12-31' ) - $this->estimate_real_reading_at_date( ( $this_reading_year - $years_ago ) . '-01-01' );
						}
					}
				}
			}

			if ( $this_reading_year !== $last_reading_year ) {
				$year_beginning_reading = $this->estimate_real_reading_at_date( $this_reading_year . '-01-01' );
				$data[ 'January 1' ][ $col_key ] = 0;
			}

			$gallons = $reading->real_reading - $year_beginning_reading;

			$data[ $row_key ][ $col_key ] = $gallons;
			$last_reading = $reading;
			$last_reading_year = $this_reading_year;
		}

		?>
		<script type="text/javascript">
			google.charts.load( 'current', { 'packages' : [ 'corechart' ] } );
			google.charts.setOnLoadCallback( drawChart );
			metermaid.registerTabBlocker( 'ytd_chart' );

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode(
							sprintf(
								__( '%1$s YTD', 'metermaid' ),
								$this->system->measurement()['plural']
							)
						); ?>,
					legend: { position: 'bottom' },
					interpolateNulls : true,
					vAxis : {
						viewWindow : {
							min: 0
						}
					}
				};

				var chart = new google.visualization.LineChart( document.getElementById( <?php echo json_encode( $chart_id ); ?> ) );
				chart.draw( data, options );

				metermaid.clearTabBlocker( 'ytd_chart' );
			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function recalculate_real_readings() {
		global $wpdb;

		$all_readings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_meter_id=%s ORDER BY reading_date ASC",
			$this->id
		) );

		$rollover_amount = 0;
		$last_reading = null;

		foreach ( $all_readings as $reading ) {
			$real_reading = $reading->reading;

			if ( $last_reading ) {
				if ( $reading->reading < $last_reading->reading ) {
					// There's been a rollover. If the last reading was 947,123, and the new one is 11,432, then
					// the real reading is actually 1,000,000 higher. So we check the number of digits in the last
					// number and do 10^x with that.
					//
					// If the readings are very far apart, then this can fail, since you could record a reading of 99,999
					// and then years later a reading of 54,000. This code would think the max reading is 99,999 and set
					// the rollover value to 100,000, but the meter just wasn't recorded during the time it went from 100,000 to 999,999.
					//
					// @todo Check the largest reading ever taken (in terms of digits) and use that for the rollover calculation.
					$rollover_amount += pow( 10, strlen( $last_reading->reading ) );
				}
			}

			$real_reading = $reading->reading + $rollover_amount;

			if ( $real_reading != $reading->real_reading ) {
				$wpdb->query( $wpdb->prepare( "UPDATE " . $wpdb->prefix . "metermaid_readings SET real_reading=%d WHERE metermaid_reading_id=%d",
					$real_reading,
					$reading->metermaid_reading_id
				) );
			}

			$last_reading = $reading;
		}
	}

	public function gallons_ytd( $reading = null ) {
		$readings = $this->readings();

		if ( count( $readings ) <= 1 ) {
			return null;
		}

		if ( $reading ) {
			$current_reading = $reading;
		} else {
			$current_reading = $readings[1];
		}

		$last_reading = $current_reading;

		for ( $i = 1; $i < count( $readings ); $i++ ) {
			if ( date( "Y", strtotime( $readings[$i]->reading_date ) ) < ( date( "Y", strtotime( $current_reading->reading_date ) ) ) ) {
				// Figure out the rate of use between the two dates around January 1 and use that to
				// estimate what the meter would have read on January 1.
				$days_between_closest_to_january_dates = round(
					(
						strtotime( $last_reading->reading_date ) - strtotime( $readings[$i]->reading_date )
					) / 60 / 60 / 24 );

				$days_since_january_1 = date( "z", strtotime( $last_reading->reading_date ) );
				$total_gallons_then = $last_reading->real_reading - $readings[$i]->real_reading;
				$total_gallons_to_subtract = ( $total_gallons_then * round( ( $days_between_closest_to_january_dates - $days_since_january_1 ) / $days_between_closest_to_january_dates ) );
				$gallons = $current_reading->real_reading - $readings[$i]->real_reading;

				return $gallons - $total_gallons_to_subtract;
			} else {
				$last_reading = $readings[$i];
			}
		}

		return null;
	}

	public function gallons_last_year() {
		$this_january_real_reading = $this->estimate_real_reading_at_date( date( "Y" ) . "-01-01" );

		$last_january_real_reading = $this->estimate_real_reading_at_date( ( date( "Y" ) - 1 ) . "-01-01" );

		if ( $this_january_real_reading !== false && $last_january_real_reading !== false ) {
			return $this_january_real_reading - $last_january_real_reading;
		}

		if ( $this_january_real_reading !== false ) {
			// If we didn't get a reading from last January but we did from this one, then all of the gallons happened in that year.
			return $this_january_real_reading;
		}

		return false;
	}

	/**
	 * $date must be Y-m-d
	 */
	public function estimate_real_reading_at_date( $date ) {
		$date_time = strtotime( $date );

		$readings = $this->readings();

		$last_reading = null;

		foreach ( $readings as $reading ) {
			if ( $reading->reading_date == $date ) {
				// We lucked out and found the exact date.
				return $reading->real_reading;
			}

			if ( $reading->reading_date < $date ) {
				// This is where the magic happens.
				if ( ! $last_reading ) {
					// The most recent reading was still before this date.
					// We could extrapolate based on the next reading X days out...
					return false;
				} else {
					$reading_before = $reading;
					$reading_after = $last_reading;

					$days_before = round( ( $date_time - strtotime( $reading_before->reading_date ) ) / 60 / 60 / 24 );

					$after_time = strtotime( $reading_after->reading_date );

					$days_after = round( ( $after_time - $date_time ) / 60 / 60 / 24 );

					$gallon_difference = $reading_after->real_reading - $reading_before->real_reading;
					$estimated_gallons = $reading_before->real_reading + ( $gallon_difference * ( $days_before / ( $days_before + $days_after ) ) );

					return round( $estimated_gallons );
				}
			}

			$last_reading = $reading;
		}

		return false;
	}

	public function estimate_real_reading_on_this_date_last_year() {
		// "This date" is the date of the last meter reading.
		$readings = $this->readings();

		if ( count( $readings ) <= 1 ) {
			return null;
		}

		$date = $readings[0]->reading_date;
		$date_parts = explode( "-", $date );
		$last_year_today = $date_parts[0] - 1 . "-" . $date_parts[1] . "-" . $date_parts[2];
		$last_year_january = $date_parts[0] - 1 . "-01-01";

		$last_year_today_real_reading = $this->estimate_real_reading_at_date( $last_year_today );
		$last_january_real_reading = $this->estimate_real_reading_at_date( $last_year_january );

		if ( $last_year_today_real_reading !== false && $last_january_real_reading !== false ) {
			return $last_year_today_real_reading - $last_january_real_reading;
		}

		if ( $last_year_today_real_reading !== false ) {
			// If we didn't get a reading from last January but we did from this one, then all of the gallons happened in that year.
			return $last_year_today_real_reading;
		}

		return false;

	}

	public static function statuses() {
		return array(
			METERMAID_STATUS_ACTIVE => __( 'Active', 'metermaid' ),
			METERMAID_STATUS_INACTIVE => __( 'Inactive', 'metermaid' ),
		);
	}
}