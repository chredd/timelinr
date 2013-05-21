<?php
/*
Plugin Name: Timelinr
Plugin URI: -
Description: Schibsted Hack Day X. Dependencies: Simple-Fields.
Version: 1.0
Author: Chris Larsson
Author URI: http://christofferlarsson.se
Author Email: hej@christofferlarsson.se
License: -
*/

require 'FeedConverter.php';

class Timelinr {

	const TESTJSON = '';

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*
	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {

		// Check if SF is loaded or not.
		// TODO: Give better feedback. Admin notification?
		//if ( !function_exists( "sf_d" ) ) return;

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );

		// Add simple fields stuffs
		// TODO, do this.
		// Never mind
		//$this->setup_simple_fields();

		// Add shortcodes
		add_shortcode( 'timeline', array( $this, 'timeline_func' ) );

		// Load Simple Fields?
		if ( function_exists( "sf_d" ) ) {
			$this->setup_simple_fields();
		}

	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {
		// TODO: Define activation functionality here
	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {
		// TODO: Define deactivation functionality here
	} // end deactivate

	/**
	 * Fired when the plugin is uninstalled.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function uninstall( $network_wide ) {
		// TODO: Define uninstall functionality here
	} // end uninstall

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( 'timelinr-admin-styles', plugins_url( 'timelinr/css/admin.css' ) );

	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		wp_enqueue_script( 'timelinr-admin-script', plugins_url( 'timelinr/js/admin.js' ), array( 'jquery' ) );

	} // end register_admin_scripts

	/**
	 * Registers and enqueues plugin-specific styles.
	 */
	public function register_plugin_styles() {

		wp_enqueue_style( 'timelinr-plugin-styles', plugins_url( 'timelinr/css/display.css' ) );
		// TODO: Only queue when needed
		wp_enqueue_style( 'timelinr-plugin-styles', plugins_url( 'timelinr/css/display.css' ) );

	} // end register_plugin_styles

	/**
	 * Registers and enqueues plugin-specific scripts.
	 */
	public function register_plugin_scripts() {
		//wp_enqueue_script( 'timelinr-plugin-script', plugins_url( 'timelinr/js/display.js' ), array('jquery'), null, true );
		// TODO: only queue when needed
		wp_enqueue_script( 'timelinejs-script', plugins_url( 'timelinr/js/storyjs-embed.js' ), array( 'jquery' ), null, true );

	} // end register_plugin_scripts

	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	public function timeline_func( $atts ) {
		// Todo: refactor this function, breaking down the functions
		global $post;

		extract( shortcode_atts( array(
					'headline'       => null,
					'text'           => null,
					'cat'            => null,
					'category_name'  => null,
					'tag'            => null,
					'author'         => null,
					'from'           => null,
					'to'             => null,
					'source'         => null,
					'url'            => null,
					's'              => null,
					'monthnum'       => null,
					'year'           => null,
					'posts_per_page' => -1,
					'post_links'     => true,
					'height'         => '600',
					'start_at_end'   => 'false',
					'trim'           => 'words',
				), $atts ) );

		// Set some defaults
		$default_atts = array(
				'no_found_rows'  => true, 
				'posts_per_page' => -1,
				'post_links'     => true,
				'trim_words'     => 30,
			);
		$atts = array_merge($default_atts, $atts);

		$feedconverter = new FeedConverter( $atts );

		// Allowed keys for wp_query (used as trigger)
		$wp_query_keys = array(
				'cat',
				'category_name',
				'tag',
				'author',
				's',
				'monthnum',
				'year',
				'from',
				'to'
			);

		// Then fetch timeline data based on input

		// Setup base timeline array based on global post and input
		if ( ! $headline ) {
			$headline = $post->post_title;
		}
		if ( ! $text ) {
			$text = get_the_excerpt();
		}

		// Set base information for timeline

		$timeline = array(
			'headline' => $headline,
			'type' => 'default',
			'text' => $text,
			'startDate' => ''
		);

		// Add image to start slide?
		if ( has_post_thumbnail( ) ) {
			$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
			$timeline['asset'] = array(
				'media' => $image_url[0]
			);
		}

		// Fetch all feeds
		if ( $url ) {
			$urls = explode( ',', $url );
			$feeds = array();
			foreach ( $urls as $url ) {
				$feed = $feedconverter->fetch_feed( $url );
				$feeds = array_merge( $feeds, $feed );
			}
			$timeline['date'] = $feeds;
		}

		// WP_query!
		// TODO: this query should be cached via transients api. Base cache key on atts.
		if( count( array_intersect( $wp_query_keys, array_keys($atts) ) ) !== 0  ){
			$query = new WP_Query( $atts );
			$convert = $feedconverter->convert($query, 'wp_query');
			if( isset($timeline['date']) ) $timeline['date'] = array_merge($timeline['date'], $convert);
			else $timeline['date'] = $convert;
		}
		
		// Get me that JSON! (But first, place it in a timeline root node)
		$json = json_encode( array( 'timeline' => $timeline  ) );

		if( sizeof( $timeline['date'] ) == 0 ){ return 'No dates returned.'; }

		// Last of all return the timeline itself
		return $this->get_timeline( array( 'height' => $height, 'source' => $json, 'start_at_end' => $start_at_end ) );
		//return $this->get_timeline( array( 'start_at_end' => 'true', 'height' => 666, 'source' => plugins_url( 'timelinr/noje.json' ) ) );
	} // end timeline_func

	public function get_timeline( $args = array() ) {
		// Set some defaults
		$defaults = array(
			'width'         => '100%',
			'height'        => '100%',
			'lang'          => 'sv',
			'hash_bookmark' => 'false',
			'embed_id'      => 'timeline-embed',
			'type'          => 'timeline',
			'start_at_end' =>  'false',
			'maptype'		=> 'ROADMAP',
			'font'			=> 'DroidSerif-DroidSans',
			'css'			=> 'http://timelinr.local/wp-content/plugins/timelinr/css/themes/dark.css',
			'source'        => 'https://docs.google.com/spreadsheet/pub?key=0AiWUhxLpQgUXdEwtOEZVZU1lcllGVHJRbjlsYTJ1VGc&output=html'
		);
		$args = array_merge( $defaults, $args );

		ob_start(); ?>
		<div id="<?php echo $args['embed_id'] ?>" class="timelinr-container"></div>
		<script type="text/javascript">
			var source = JSON.parse("<?php echo addslashes( $args['source'] ) ?>");
			//var test = "<?php echo $args['source']?>"
			var timeline_config = {
				type: "<?php echo $args['type'] ?>",
				lang: "<?php echo $args['lang'] ?>",
				hash_bookmark: <?php echo $args['hash_bookmark'] ?>,
				width: "<?php echo $args['width'] ?>",
				height: "<?php echo $args['height'] ?>",
				start_at_end: <?php echo $args['start_at_end'] ?>,
				font: '<?php echo $args['font'] ?>',
				css: 'http://timelinr.local/wp-content/plugins/timelinr/css/themes/dark.css',
				source: source,
				maptype: "<?php echo $args['maptype'] ?>",
				embed_id: "<?php echo $args['embed_id'] ?>"
			}
		</script>

		<?php
		$output = ob_get_clean();
		return $output;
	} // end get_timeline

	private function setup_simple_fields()
	{

		if ( !function_exists( "simple_fields_field_googlemaps_register" ) ) {
			return;
		}

		simple_fields_register_field_group('timelinr_gmap',
			array (
				'name' => 'Timeline map',
				'description' => "Map for the timeline",
				'repeatable' => 1,
				'fields' => array(
					array(
						'slug' => "timelinr_maptitle",
						'name' => 'Title',
						'description' => 'The title for the position',
						'type' => 'text'
					),
					array(
						"type" => "googlemaps",
						"slug" => "timelinr_map",
						"name" => "Timelinr map",
						"options" => array(
							"defaultZoomLevel" => 10,
							"defaultMapTypeId" => "ROADMAP", // ROADMAP | SATELLITE | HYBRID | TERRAIN
							"defaultLocationLat" => 59.32893,
							"defaultLocationLng" => 18.06491,
						)
					),
					array(
						'slug' => "timelinr_use_map",
						'name' => 'Use map as illustration',
						'description' => 'Replaces featured image',
						'type' => 'checkbox',
						'type_checkbox_options' => array('checked_by_default' => 1)
					),
				)
			)
		);

		// function simple_fields_register_post_connector($unique_name = "", $new_post_connector = array()) {
		simple_fields_register_post_connector('timelinr_map_connector',
			array (
				'name' => "Timlinr Map Connector",
				'field_groups' => array(
					array(
						'slug' => 'timelinr_gmap',
						'context' => 'normal',
						'priority' => 'high'
					),
				),
				// post_types can also be string, if only one post type is to be connected
				'post_types' => array('post')
			)
		);

		/**
		 * Sets the default post connector for a post type
		 * 
		 * @param $post_type_connector = connector id (int) or slug (string) or string __inherit__
		 * 
		 */
		simple_fields_register_post_type_default('timelinr_map_connector', 'post');
	}


} // end class

// LET'S DO THIS
$timelinr = new Timelinr();
