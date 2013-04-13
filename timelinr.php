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

class Timelinr {

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {

		// Check if SF is loaded or not.
		// TODO: Give better feedback. Admin notification?
		if (!function_exists("sf_d")) return;

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

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

		$this->setup_simple_fields();

	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {
		// TODO:	Define activation functionality here
	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {
		// TODO:	Define deactivation functionality here
	} // end deactivate

	/**
	 * Fired when the plugin is uninstalled.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function uninstall( $network_wide ) {
		// TODO:	Define uninstall functionality here
	} // end uninstall

	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {

		// TODO: replace "timelinr-locale" with a unique value for your plugin
		$domain = 'timelinr-locale';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
        load_textdomain( $domain, WP_LANG_DIR.'/'.$domain.'/'.$domain.'-'.$locale.'.mo' );
        load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

	} // end plugin_textdomain

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

		wp_enqueue_script( 'timelinr-admin-script', plugins_url( 'timelinr/js/admin.js' ), array('jquery') );

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

		wp_enqueue_script( 'timelinr-plugin-script', plugins_url( 'timelinr/js/display.js' ), array('jquery'), null, true );
		// TODO: only queue when needed
		wp_enqueue_script( 'timelinejs-script', plugins_url( 'timelinr/js/storyjs-embed.js' ), array('jquery'), null, true );

	} // end register_plugin_scripts

	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	public function setup_simple_fields( )
	{

		simple_fields_register_field_group('test',
			array (
				'name' => 'Test field group',
				'description' => "Test field description",
				'repeatable' => 1,
				'fields' => array(
					array(
						'slug' => "my_text_field_slug",
						'name' => 'Test text',
						'description' => 'Text description',
						'type' => 'text'
					),
					array(
						'slug' => "my_textarea_field_slug",
						'name' => 'Test textarea',
						'description' => 'Textarea description',
						'type' => 'textarea',
						'type_textarea_options' => array('use_html_editor' => 1)
					),
					array(
						'slug' => "my_checkbox_field_slug",
						'name' => 'Test checkbox',
						'description' => 'Checkbox description',
						'type' => 'checkbox',
						'type_checkbox_options' => array('checked_by_default' => 1)
					),
					array(
						'slug' => "my_radiobutton_field_slug",
						'name' => 'Test radiobutton',
						'description' => 'Radiobutton description',
						'type' => 'radiobutton',
						'type_radiobutton_options' => array(
							array("value" => "Yes"),
							array("value" => "No")
						)
					),
					array(
						'slug' => "my_dropdown_field_slug",
						'name' => 'Test dropdown',
						'description' => 'Dropdown description',
						'type' => 'dropdown',
						'type_dropdown_options' => array(
							"enable_multiple" => 1,
							"enable_extended_return_values" => 1,
							array("value" => "Yes"),
							array("value" => "No")
						)
					),
					array(
						'slug' => "my_file_field_slug",
						'name' => 'Test file',
						'description' => 'File description',
						'type' => 'file'
					),
					array(
						'slug' => "my_post_field_slug",
						'name' => 'Test post',
						'description' => 'Post description',
						'type' => 'post',
						'type_post_options' => array("enabled_post_types" => array("post"))
					),
					array(
						'slug' => "my_taxonomy_field_slug",
						'name' => 'Test taxonomy',
						'description' => 'Taxonomy description',
						'type' => 'taxonomy',
						'type_taxonomy_options' => array("enabled_taxonomies" => array("category"))
					),
					array(
						'slug' => "my_taxonomyterm_field_slug",
						'name' => 'Test taxonomy term',
						'description' => 'Taxonomy term description',
						'type' => 'taxonomyterm',
						'type_taxonomyterm_options' => array("enabled_taxonomy" => "category")
					),
					array(
						'slug' => "my_color_field_slug",
						'name' => 'Test color selector',
						'description' => 'Color selector description',
						'type' => 'color'
					),
					array(
						'slug' => "my_date_field_slug",
						'name' => 'Test date selector',
						'description' => 'Date selector description',
						'type' => 'date',
						'type_date_options' => array('use_time' => 1)
					),
					array(
						'slug' => "my_date2_field_slug",
						'name' => 'Test date selector',
						'description' => 'Date v2 selector description',
						'type' => 'date_v2',
						"options" => array(
							"date_v2" => array(
								"show" => "on_click",
								"show_as" => "datetime",
								"default_date" => "today"
							)
						)
					),			
					array(
						'slug' => "my_user_field_slug",
						'name' => 'Test user selector',
						'description' => 'User selector description',
						'type' => 'user'
					)
				)
			)
		);

		// function simple_fields_register_post_connector($unique_name = "", $new_post_connector = array()) {
		simple_fields_register_post_connector('test_connector',
			array (
				'name' => "A test connector",
				'field_groups' => array(
					array(
						'slug' => 'test',
						'context' => 'normal',
						'priority' => 'high'
					)
				),
				'post_types' => array('post')
			)
		);

		/**
		 * Sets the default post connector for a post type
		 * 
		 * @param $post_type_connector = connector id (int) or slug (string) or string __inherit__
		 * 
		 */
		simple_fields_register_post_type_default('test_connector', 'post');

	}

} // end class

$timelinr = new Timelinr();