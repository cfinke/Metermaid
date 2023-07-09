<?php

class METERMAID_METER {
	public $id;
	public $name;
	public $location;
	public $is_parent;

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

			$relationships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "metermaid_relationships WHERE m.parent_meter_id=%s OR m.child_meter_id=%s", $this->id, $this->id ) );

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
		if ( ! empty( $this->children ) ) {
			return true;
		}

		return false;
		}
}