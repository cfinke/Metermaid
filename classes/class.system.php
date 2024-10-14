<?php

class METERMAID_SYSTEM {
	public $id;
	public $name;
	public $unit;
	public $rate_interval;

	private $_meters = null;
	private $_readable_meters = null;

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

		if ( 'meters' == $key ) {
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

			$meters = $this->meters;

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

			$meters = $this->meters;

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
}