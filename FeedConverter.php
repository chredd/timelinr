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
			if ( !is_wp_error( $remote = wp_remote_get( $url, array('timeout' => 10) ) ) ) {
				//print_r( $remote['body'] );
				set_transient( $cache_key, $remote, 300 );
			} else {
				die('could not fetch');
			}
		}

		return $data = FeedConverter::convert( $remote['body'], 'feed' );
	}

	static public function convert( $data = null, $type = 'feed' ) {

		if ( !$data ) { return; }

		$dates = array();

		// Convert feeds to correct JSON
		if ( 'feed' === $type ) {
			$data = str_replace('media:', 'media_', $data);
			$xml = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS );
			foreach ( $xml->channel->item as $index => $item ) {

				if( strlen( (string) $item->title) < 2 )  continue;
				
				// TODO: fix this mess!
				// Should use something else instead of simplexml
				$host = parse_url( $item->link );
				$text = strip_tags( $item->description );
				//Huh?
				$text = str_replace("&#160;", "", $text);
				$text = wp_trim_words( htmlentities( $text ), 30) . '<br><a href="' . (string)$item->link . '" target="_blank">'. str_replace("www.", "", $host['host'] ) .' / Läs artikeln →</a>';
				$date = array(
					'startDate' => date( "Y,n,j,H,s" , strtotime( $item->pubDate ) ),
					'headline' =>  ( htmlentities( $item->title ) ),
					'text' =>  ( $text )
				);

				//print_r($date);


				// TODO: checking and adding images should be a help function
				// Find images in Description
				preg_match( '/(<img[^>]+>)/i', $item->description, $matches );
				if ( $matches[0] ) {
					preg_match( '/(alt|title|src)=("[^"]*")/i', $matches[0], $img );
					if ( $img[0] ) {
						$date['asset'] = array(
							'media' => str_replace("\"", "", $img[2]),
							'credit' => '',
							'caption' => ''
						);
					}
				}

				// Check for enclosure
				if( $item->enclosure && $item->enclosure->attributes()->url ){
					$attr = $item->enclosure->attributes();
					$date['asset'] = array(
						'media' => (string)$attr['url'],
						'credit' => '',
						'caption' => ''
					);
				}

				// Check for media:content
				//print_r($item);
	
				if( $item->media_content ){
					$attr = $item->media_content->attributes();
					$date['asset'] = array(
						'media' => (string)$attr['url'],
						'credit' => '',
						'caption' => ''
					);
				}

				// Add the item
				$dates[] = ( $date );
			} 
			
		} // end of feed
		else if( 'wp_query' === $type ){
			foreach ($data->posts as $post) {
				
				$text = strip_shortcodes ( strip_tags( $post->post_content ) );
				$text = wp_trim_words( trim( $text ), 30) . '<br><a href="'. get_permalink( $post->ID ) .'">Läs inlägget →</a>';
				$date = array(
					'startDate' => date( "Y,n,j" , strtotime( $post->post_date ) ),
					'headline' =>  ( $post->post_title ) ,
					'text' => $text 
				);

				// Add image if post thumbnail
				if ( has_post_thumbnail( $post->ID ) ) {
					$image_id = get_post_thumbnail_id( $post->ID );
					$image = get_post($image_id);
					$image_url = wp_get_attachment_image_src( $image_id, 'large' );
					$date['asset'] = array(
						'media' => $image_url[0],
						'caption' => $image->post_excerpt
					);
				}

				// Add the item
				$dates[] = ( $date );
			}
		} // end of wp_query

		return ( array_values($dates) ) ;

	}
}
