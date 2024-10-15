<?php

class METERMAID_READING {
	public $id;
	public $meter_id;
	public $reading;
	public $real_reading;
	public $reading_date;
	public $added_by;

	private $_meter;

	public function __construct( $reading_id_or_row ) {
		global $wpdb;

		if ( is_numeric( $reading_id_or_row ) ) {
			$reading_id_or_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_reading_id=%s LIMIT 1", $reading_id_or_row ) );

			if ( ! $reading_id_or_row ) {
				return false;
			}
		}

		$this->id = $reading_id_or_row->metermaid_reading_id ?? null;
		$this->meter_id = $reading_id_or_row->metermaid_meter_id;
		$this->reading = $reading_id_or_row->reading;
		$this->real_reading = $reading_id_or_row->real_reading;
		$this->reading_date = date( 'Y-m-d', strtotime( $reading_id_or_row->reading_date ) );
		$this->added_by = $reading_id_or_row->added_by;
	}

	/**
	 * Hide some expensive calls behind a cached __get().
	 */
	public function __get( $key ) {
		global $wpdb;

		if ( 'meter' == $key ) {
			$this->_meter = new METERMAID_METER( $this->meter_id );
			return $this->_meter;
		}

		return null;
	}

	/**
	 * A nicer way than "if ( $reading->id )" to check for whether this object represents a found reading, or if the instantiation failed.
	 *
	 * Call $reading(), like a function, to run this method.
	 */
	public function __invoke() {
		return !! $this->id;
	}
}