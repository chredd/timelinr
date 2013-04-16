<?php

/**
 *
 */
class FeedConverter {
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	function __construct() {
		// Do some stuffs in the constructor
	}

	static public function fetch( $url=null ) {
		if ( !$url ) return;

		$cache_key = md5( $url );
		$remote = get_transient( $cache_key );

		if ( false === $remote ) {
			//echo 'Fetching...';
			if ( !is_wp_error( $remote = wp_remote_get( $url ) ) ) {
				//print_r( $remote['body'] );
				set_transient( $cache_key, $remote, 300 );
			}
		}

		$data = FeedConverter::convert( $remote['body'], 'feed' );
	}

	static public function convert( $data = null, $type = 'feed' ) {

		if ( !$data ) { return; }

		$dates = array( 'date' => null );

		// Convert feeds to correct JSON
		if ( 'feed' === $type ) {
			$xml = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
			foreach ( $xml->channel->item as $index => $item ) {
				//print_r( $item );
				$dates['date'][] = array(
					'startDate' => $item->pubDate,
					'headline' =>  $item->title,
					'text' =>  $item->description
				);
			}
			//print_r( json_encode( $dates ) );
		}
	}
}
