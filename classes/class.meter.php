<?php

class METERMAID_METER {
	public $id;
	public $name;
	public $location;

	private $_readings = null;
	private $_children = null;
	private $_parents = null;

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
	public function readings() {
		global $wpdb;

		$readings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s ORDER BY reading_date DESC",
			$this->id
		) );



		return $readings;
	}

	public function __get( $key ) {
		global $wpdb;

		if ( 'children' == $key || 'parents' == $key ) {
			if ( 'children' == $key && ! is_null( $this->_children ) ) {
				return $this->_children;
			} else if ( 'parents' == $key && ! is_null( $this->_parents ) ) {
				return $this->parents;
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
		} else if ( 'readings' == $key ) {
			if ( ! is_null( $this->_readings ) ) {
				return $this->_readings;
			}

			$readings = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE meter_id=%s ORDER BY reading_date DESC",
				$this->id
			) );

			$this->_readings = array();

			foreach ( $readings as $reading ) {
				$this->_readings[] = new METERMAID_READING( $reading );
			}

			return $this->_readings;
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
		$readings = $this->readings;

		if ( count( $readings ) < 2 ) {
			return;
		}

		$chart_id = 'metermaid-year-chart-' . $this->id;

		$readings = array_reverse( $readings );

		$first_year = date( "Y", strtotime( $readings[1]->reading_date ) ); // The first date at which we can determine gpd.

		$data = array();

		$header_row = array( "Date" );

		for ( $year = $first_year; $year <= date( "Y" ); $year++ ) {
			$header_row[] = (string) $year;
		}

		$data[] = $header_row;

		for ( $i = 0; $i <= 365; $i++ ) {
			$date_label = date( "F j", strtotime( "January 1, " . $year ) + ( $i * 60 * 60 * 24 ) );
			$data_row = array_fill( 0, count( $header_row ), null );
			$data_row[0] = $date_label;
			$data[ $date_label ] = $data_row;
		}

		$trailing_average_duration = 14; // Number of days minimum to calculate gpd average.

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

			$gallons = $reading->reading - $previous_reading->reading;
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
					title: 'Gallons Per Day',
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
		$header_row[] = $this->name;
		$header_row[] = "Children";

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

		$trailing_average_duration = 160; // Number of days minimum to calculate gpd average.

		$meters_to_chart = array();
		$meters_to_chart[] = $this;

		$readings = array_reverse( $this->readings );

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

			$gallons = $reading->reading - $previous_reading->reading;
			$gpd = round( $gallons / $days_since_previous_reading );

			$row_key = date( "F j", strtotime( $reading->reading_date ) );
			$data[ $row_key ][1] += $gpd;
		}

		$child_readings = array();

		foreach ( $child_objects as $idx => $meter ) {
			$readings = array_reverse( $meter->readings );

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

				$gallons = $reading->reading - $previous_reading->reading;
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
					title: 'Gallons Per Day (Children)',
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

}