<?php
require 'vendor/autoload.php';
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

	function __construct() {
		// Do some stuffs in the constructor
		$this->setup_logging();
		$this->log( 'Initializing FeedConverter object.' );
	}

	private function setup_logging() {
		return false; //temp disable
		$this->current_dir = dirname( __FILE__ );
		$this->log_name = 'timelinr';
		$this->log_file = $this->current_dir .  '/log/feed_converter.log';
		$this->logger = new Monolog\Logger( $this->log_name );
		$this->logger->pushHandler( new Monolog\Handler\StreamHandler( $this->log_file, Monolog\Logger::INFO ) );
	}

	private function log( $message = '', $type = 'Info' ) {
		return false; //temp disable
		$add_type = 'add'.$type;
		$this->logger->$add_type( $message );
	}

	public function fetch_feed( $url=null ) {
		if ( !$url ) return;

		$cache_key = md5( $url );
		$remote = get_transient( $cache_key );

		if ( false === $remote ) {
			//echo 'Fetching...';
			if ( !is_wp_error( $remote = wp_remote_get( $url, array( 'timeout' => 10 ) ) ) ) {
				set_transient( $cache_key, $remote, 300 );
				$this->logger->addInfo( 'Feed fetched: ' . $url );
			} else {
				$this->log( 'Could not fetch', 'Error' );
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

				if ( strlen( (string) $item->title ) < 2 )  continue;

				// TODO: fix this mess!
				// Should use something else instead of simplexml, PHPQuery?
				$host = parse_url( $item->link );
				$text = strip_tags( $item->description );
				//Huh?
				$text = str_replace( "&#160;", "", $text );
				$text = wp_trim_words( htmlentities( $text ), 30 ) . '<br><a href="' . (string)$item->link . '" target="_blank">'. str_replace( "www.", "", $host['host'] ) .' / Läs artikeln →</a>';
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
				foreach ( $data->posts as $post ) {

					$classname = $this->get_classname( $post );
					$text = strip_shortcodes ( strip_tags( $post->post_content ) );
					$text = wp_trim_words( trim( $text ), 30 ) . '<br><a href="'. get_permalink( $post->ID ) .'">Läs inlägget →</a>';
					$date = array(
						'startDate' => date( "Y,n,j" , strtotime( $post->post_date ) ),
						'headline'  => $post->post_title,
						'text'      => $text,
						'classname' => $classname
					);

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

					// Add the item
					$dates[] = ( $date );
				}
			} // end of wp_query

		$this->log( 'Converted type: ' . $type );
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

	private function get_classname( $post ) {
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
}
