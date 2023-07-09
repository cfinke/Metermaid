<?php

class METERMAID_READING {
	public $id;
	public $meter_id;
	public $reading;
	public $reading_date;

	public function __construct( $reading_id_or_row ) {
		global $wpdb;

		if ( is_numeric( $reading_id_or_row ) ) {
			$reading_id_or_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_readings WHERE metermaid_reading_id=%s LIMIT 1", $reading_id_or_row ) );

			if ( ! $reading_id_or_row ) {
				return false;
			}
		}

		$this->id = $reading_id_or_row->metermaid_reading_id;
		$this->meter_id = $reading_id_or_row->meter_id;
		$this->reading = $reading_id_or_row->reading;
		$this->reading_date = $reading_id_or_row->reading_date;
	}
}