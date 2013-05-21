<?php
//require 'vendor/autoload.php';
/**
 * TODO: This class should be renamed to something better.
 */
class FeedConverter {

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	private $log_name;
	private $log_file;
	private $current_dir;
	private $logger;
	private $atts;

	const SF_MAP_GROUP = 'timelinr_map_group';
	const SF_ASSET_GROUP = 'timelinr_asset_group';
	const SF_MAP_ID = 'timelinr_map';
	const SF_ASSET_ID = 'timelinr_asset';
	const SF_MAP_USE = 'timelinr_use_map';

	function __construct( $atts = array() ) {
		// Do some stuffs in the constructor

		// Disable logging for now. Readd when Composer is back in the project.
		// $this->setup_logging();
		// $this->log( 'Initializing FeedConverter object.' );

		if ( isset( $atts ) && ! empty( $atts ) && is_array( $atts ) ) {
			$this->atts = $atts;
		}
	}

	private function setup_logging() {
		$this->current_dir = dirname( __FILE__ );
		$this->log_name = 'timelinr';
		$this->log_file = $this->current_dir .  '/log/feed_converter.log';
		$this->logger = new Monolog\Logger( $this->log_name );
		$this->logger->pushHandler( new Monolog\Handler\StreamHandler( $this->log_file, Monolog\Logger::INFO ) );
	}

	private function log( $message = '', $type = 'Info' ) {
		// $add_type = 'add'.$type;
		// $this->logger->$add_type( $message );
		error_log( $type . ': ' . $message );
	}

	public function fetch_feed( $url=null ) {
		if ( !$url ) return;

		$cache_key = md5( $url );
		$remote = get_transient( $cache_key );

		if ( false === $remote ) {
			if ( !is_wp_error( $remote = wp_remote_get( $url, array( 'timeout' => 10 ) ) ) ) {
				set_transient( $cache_key, $remote, 300 );
				//$this->logger->addInfo( 'Feed fetched: ' . $url );
			} else {
				$this->log( 'Could not fetch', 'Info' );
				// Todo, log the wp_error as well.
			}
		}

		return $data = $this->convert( $remote['body'], 'feed' );
	}

	public function convert( $data = null, $type = 'feed' ) {

		if ( !$data ) { return; }

		$dates = array();

		// Convert feeds to correct JSON
		if ( 'feed' === $type ) {
			$data = str_replace( 'media:', 'media_', $data );
			$xml = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS );
			if ( !$xml->channel ) return;
			foreach ( $xml->channel->item as $index => $item ) {

				// We must have a title in order to continue.
				if ( strlen( (string) $item->title ) < 2 )  continue;

				// TODO: fix this mess!
				// Should use something else instead of simplexml, PHPQuery?
				$host = parse_url( $item->link );
				$text = strip_tags( $item->description );
				//Huh?
				$text = str_replace( "&#160;", "", $text );

				if ( isset( $this->atts['trim_words'] ) ) {
					$text = wp_trim_words( htmlentities( $text ), 30 );
				}
				$text .= '<br><a href="' . (string)$item->link . '" target="_blank">'. str_replace( "www.", "", $host['host'] ) .' / Läs artikeln →</a>';
				$date = array(
					'startDate' => date( "Y,n,j,H,s" , strtotime( $item->pubDate ) ),
					'headline' =>  ( htmlentities( $item->title ) ),
					'text' =>  ( $text )
				);

				if ( $asset = $this->get_image_from_feed( $item ) ) {
					$date['asset'] = $asset;
				}

				// Add the item
				$dates[] = ( $date );
			}

		} // end of feed
		else if ( 'wp_query' === $type ) {
				global $post;
				foreach ( $data->posts as $post ) {

					$classname = $this->get_item_classname( $post );
					$text = $post->post_excerpt;

					if ( empty( $text ) ) {
						$text = strip_shortcodes ( ( $post->post_content ) );
					}
					if ( isset( $this->atts['trim_words'] ) && is_numeric( $this->atts['trim_words'] && $this->atts['trim_words'] > 0 ) ) {
						$text = wp_trim_words( trim( $text ), $this->atts['trim_words'] );
					}
					if ( $this->atts['post_links'] ) {
						$text .= '<br><a href="'. get_permalink( $post->ID ) .'">Läs inlägget →</a>';
					}
					$date = array(
						'startDate' => date( "Y,n,j,H,s" , strtotime( $post->post_date ) ),
						'headline'  => $post->post_title,
						'text'      => $text,
						'classname' => $classname,
					);

					if ( 'true' === $this->atts['tags'] ) {
						$cats = get_the_category( $post->ID );
						$tag = $cats[0]->name;
						$date['tag'] = $tag;
					}

					// Add image if post thumbnail
					if ( has_post_thumbnail( $post->ID ) ) {
						$image_id = get_post_thumbnail_id( $post->ID );
						$image = get_post( $image_id );
						$image_url = wp_get_attachment_image_src( $image_id, 'large' );
						$date['asset'] = array(
							'media' => $image_url[0],
							'caption' => $image->post_excerpt
						);
					}

					// Assets from SF
					if ( $asset = $this->get_asset( $post ) ) {
						$date['asset'] = $asset;
					}

					// Map from SF
					if ( $asset = $this->get_map( $post ) ) {
						$date['asset'] = $asset;
					}

					// Add the item
					$dates[] = ( $date );
				}
			} // end of wp_query

		return array_values( $dates );

	}

	private function get_image_from_feed( $item ) {
		$asset = null;
		// Look for image in desc
		preg_match( '/(<img[^>]+>)/i', $item->description, $matches );
		if ( $matches[0] ) {
			preg_match( '/(alt|title|src)=("[^"]*")/i', $matches[0], $img );
			if ( $img[0] ) {
				$asset = array(
					'media' => str_replace( "\"", "", $img[2] ),
					'credit' => '',
					'caption' => ''
				);
			}
		}

		// Check for enclosure
		if ( $item->enclosure && $item->enclosure->attributes()->url ) {
			$attr = $item->enclosure->attributes();
			$asset = array(
				'media' => (string)$attr['url'],
				'credit' => '',
				'caption' => ''
			);
		}

		// Check for media:content

		if ( $item->media_content ) {
			$attr = $item->media_content->attributes();
			$asset = array(
				'media' => (string)$attr['url'],
				'credit' => '',
				'caption' => ''
			);
		}

		return null !== $asset ? $asset : false ;
	}

	private function get_item_classname( $post ) {
		if ( !$post ) return;

		$classname = ' ';
		$categories = get_the_category( $post->ID );
		if ( $categories ) {
			foreach ( $categories as $category ) {
				$classname .= 'category-' . $category->slug . ' ';
			}
		}

		$posttags = get_the_tags( $post->ID );
		if ( $posttags ) {
			foreach ( $posttags as $tag ) {
				$classname .= 'tag-' . $tag->slug . ' ';
			}

		}

		return $classname;
	}

	private function get_map( $post ) {

		if ( !$post ) return;
		if ( !function_exists( "simple_fields_field_googlemaps_register" ) ) return;

		// http://maps.google.com/maps?q=New+York,+NY&hl=en&ll=40.721242,-73.987427&spn=0.164187,0.365295&sll=40.722673,-73.993263&sspn=0.082092,0.182648&oq=New+Y&hnear=New+York&t=m&z=11
		$map_group = simple_fields_fieldgroup( self::SF_MAP_GROUP, $post->ID );
		if ( !empty( $map_group[ self::SF_MAP_ID ]['lat'] ) ) {

			if ( true != $map_group[ self::SF_MAP_USE ] ) return false;
			// Return true if we don't the map as illustration

			$map = $map_group[ self::SF_MAP_ID ];
			// Handle empty zoom level
			if ( empty( $map['preferred_zoom'] ) ) $map['preferred_zoom'] = 11;
			// Maybe we should use static maps instead
			$gmaps_string = 'http://maps.google.com/maps?q=&hl=sv&q='. $map['lat'] .','. $map['lng'] .'&sll='. $map['lat'] .','. $map['lng'] .'&z='. $map['preferred_zoom'];
			$asset = array(
				'media' => $gmaps_string,
				'credit' => '',
				'caption' => ''
			);
			return $asset;
		}

		return false;

	}

	private function get_asset( $post ) {

		if ( !$post ) return;
		if ( !function_exists( "sf_d" ) ) return;

		$asset_group = simple_fields_fieldgroup( self::SF_ASSET_GROUP, $post->ID );
		if ( !empty( $asset_group ) ) {
			$asset = array(
				'media' => $asset_group,
				'credit' => '',
				'caption' => ''
			);
			return $asset;
		}

		return false;

	}

	/**
	 * trims text to a space then adds ellipses if desired
	 * @param string $input text to trim
	 * @param int $length in characters to trim to
	 * @param bool $ellipses if ellipses (...) are to be added
	 * @param bool $strip_html if html tags are to be stripped
	 * @return string 
	 */
	function trim_text($input, $length, $ellipses = true, $strip_html = true) {
		//strip tags, if desired
		if ($strip_html) {
			$input = strip_tags($input);
		}

		//no need to trim, already shorter than trim length
		if (strlen($input) <= $length) {
			return $input;
		}

		//find last space within length
		$last_space = strrpos(substr($input, 0, $length), ' ');
		$trimmed_text = substr($input, 0, $last_space);

		//add ellipses (...)
		if ($ellipses) {
			$trimmed_text .= '...';
		}

		return $trimmed_text;
	}

}
