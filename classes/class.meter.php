<?php

class METERMAID_METER {
	public $id;
	public $name;
	public $location;
	public $inactive;

	private $_readings = null;
	private $_children = null;
	private $_parents = null;
	private $_children_readings = null;

	public function __construct( $meter_id_or_row ) {
		global $wpdb;

		if ( is_numeric( $meter_id_or_row ) ) {
			$meter_id_or_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_meters WHERE metermaid_meter_id=%s LIMIT 1", $meter_id_or_row ) );

			if ( ! $meter_id_or_row ) {
				return false;
			}
		}

		$this->name = $meter_id_or_row->name;
		$this->location = $meter_id_or_row->location;
		$this->id = $meter_id_or_row->metermaid_meter_id;
		$this->inactive = $meter_id_or_row->inactive;
	}

	/**
	 * Include the location (if set) when displaying the meter name.
	 */
	public function display_name() {
		return $this->name . ( $this->location ? ' (' . $this->location . ')' : '' );
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
			"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s ORDER BY reading_date DESC",
			$this->id
		) );

		if ( ! empty( $readings ) ) {
			if ( $this->inactive ) {
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

	public static function sort_readings( $a, $b ) {
		if ( $a->reading_date < $b->reading_date ) {
			return 1;
		} else if ( $a->reading_date > $b->reading_date ) {
			return -1;
		}

		return 0;
	}

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
		}

		return null;
	}

	public function is_parent() {
		$children = $this->children;

		if ( ! empty( $children ) ) {
			return true;
		}

		return false;
	}

	public function year_chart() {
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

		$trailing_average_duration = METERMAID::get_option( 'minimum_rate_interval' ); // Number of days minimum to calculate gpd average.

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

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode( METERMAID::measurement()['plural'] . ' Per Day (over at least ' . METERMAID::get_option( 'minimum_rate_interval' ) . ' days)' ); ?>,
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
			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function children_chart() {
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

		$trailing_average_duration = METERMAID::get_option( 'minimum_rate_interval' ); // Number of days minimum to calculate gpd average.

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

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode( METERMAID::measurement()['plural'] . ' Per Day (Children, over at least ' . intval( METERMAID::get_option( 'minimum_rate_interval' ) ) . '> days)' ); ?>,
					legend: { position: 'bottom' },
					vAxis : {
						viewWindow : {
							min: 0
						}
					}
				};

				var chart = new google.visualization.LineChart( document.getElementById( <?php echo json_encode( $chart_id ); ?> ) );
				chart.draw( data, options );
			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function children_readings() {
		if ( is_null( $this->_children_readings ) ) {
			$this->_children_readings = array();

			$master_reading_dates = array();

			foreach ( $this->readings() as $reading ) {
				$master_reading_dates[] = $reading->reading_date;
			}

			if ( $this->is_parent() ) {
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

							$this->_children_readings[ $reading->reading_date ][] = $reading->reading;
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

	public function ytd_chart() {
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

			if ( $last_reading && $this_reading_year == $last_reading_year + 1 ) {
				if ( date( "z", strtotime( $reading->reading_date ) ) <= 10 ) {
					// If the first reading of the year is within the first ten days, consider it the last reading of the previous year.
					$data[ 'December 31' ][ $col_key - 1 ] = $reading->real_reading - $year_beginning_reading;
				}
			}

			if ( $this_reading_year !== $last_reading_year ) {
				$year_beginning_reading = $reading->real_reading;
			}

			if ( $this_reading_year == $last_reading_year + 1 ) {
				// If the last reading of the last year is within the last ten days, and this reading is not within the first ten days,
				// consider that one the first reading of this year.
				if ( $last_reading && date( "z", strtotime( $last_reading->reading_date ) ) >= 355 ) {
					$year_beginning_reading = $last_reading->real_reading;
					$data[ 'January 1' ][ $col_key ] = $last_reading->real_reading - $year_beginning_reading;
				}
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

			function drawChart() {
				// Create the data table.
				var data = google.visualization.arrayToDataTable( <?php echo json_encode( array_values( $data ) ); ?> );

				var options = {
					title: <?php echo json_encode( METERMAID::measurement()['plural'] . ' YTD' ); ?>,
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
			  }
		</script>
		<div id="<?php echo esc_attr( $chart_id ); ?>" style="height: 500px;"></div>
		<?php
	}

	public function recalculate_real_readings() {
		global $wpdb;

		$all_readings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s ORDER BY reading_date ASC",
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
}