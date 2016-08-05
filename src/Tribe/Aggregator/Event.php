<?php

class Tribe__Events__Aggregator__Event {
	public $data;

	public function __construct( $data = array() ) {
		// maybe translate service data to and Event array
		if ( is_object( $data ) && ! empty( $item->title ) ) {
			$data = self::translate_service_data( $data );
		}

		$this->data = $data;
	}

	public static function translate_service_data( $item ) {
		$event = array();
		$item = (object) $item;

		$field_map = array(
			'title' => 'post_title',
			'description' => 'post_content',
			'start_date' => 'EventStartDate',
			'start_hour' => 'EventStartHour',
			'start_minute' => 'EventStartMinute',
			'start_meridian' => 'EventStartMeridian',
			'end_date' => 'EventEndDate',
			'end_hour' => 'EventEndHour',
			'end_minute' => 'EventEndMinute',
			'end_meridian' => 'EventEndMeridian',
			'timezone' => 'EventTimezone',
			'url' => 'EventURL',
			'all_day' => 'EventAllDay',
			'facebook_id' => 'EventFacebookID',
			'meetup_id' => 'EventMeetupID',
			'_uid' => 'uid',
		);

		$venue_field_map = array(
			'venue' => 'Venue',
			'address' => 'Address',
			'city' => 'City',
			'province' => 'Province',
			'state' => 'State',
			'stateprovince' => 'StateProvince',
			'province' => 'Province',
			'zip' => 'Zip',
			'phone' => 'Phone',
		);

		$organizer_field_map = array(
			'organizer' => 'Organizer',
			'phone' => 'Phone',
			'email' => 'Email',
			'website' => 'Website',
		);

		foreach ( $field_map as $origin_field => $target_field ) {
			if ( ! isset( $item->$origin_field ) ) {
				continue;
			}

			$event[ $target_field ] = $item->$origin_field;
		}

		if ( ! empty( $item->venue ) ) {
			$event['Venue'] = array();
			foreach ( $venue_field_map as $origin_field => $target_field ) {
				if ( ! isset( $item->venue->$origin_field ) ) {
					continue;
				}

				$event['Venue'][ $target_field ] = $item->venue->$origin_field;
			}
		}

		if ( ! empty( $item->organizer ) ) {
			$event['Organizer'] = array();
			foreach ( $organizer_field_map as $origin_field => $target_field ) {
				if ( ! isset( $item->organizer->$origin_field ) ) {
					continue;
				}

				$event['Organizer'][ $target_field ] = $item->organizer->$origin_field;
			}
		}

		return $event;
	}

	/**
	 * Fetch all existing unique IDs from the provided list that exist in meta
	 *
	 * @param string $key Meta key
	 * @param array $values Array of meta values
	 *
	 * @return array
	 */
	public function get_existing_ids( $origin, $values ) {
		global $wpdb;

		$fields = Tribe__Events__Aggregator__Record__Abstract::$unique_id_fields;

		if ( empty( $fields[ $origin ] ) ) {
			return array();
		}

		$key = "_{$fields[ $origin ]['target']}";

		// sanitize values
		foreach ( $values as &$value ) {
			$value = preg_replace( '/[^a-zA-Z0-9]/', '', $value );
		}

		$sql = "
			SELECT
				meta_value,
				post_id
			FROM
				{$wpdb->postmeta}
			WHERE
				meta_key = %s
				AND meta_value IN ('" . implode( "','", $values ) ."')
		";

		return $wpdb->get_results( $wpdb->prepare( $sql, $key ), OBJECT_K );
	}
}