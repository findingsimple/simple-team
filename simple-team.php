<?php
/*
Plugin Name: Simple Team
Plugin URI: http://plugins.findingsimple.com
Description: Adds the "Team Member" CPT.
Version: 1.0
Author: Finding Simple ( Jason Conroy & Brent Shepherd)
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Simple_Team' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_Team
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple Team
 * @since 1.0
 */
function initialize_team(){
	Simple_Team::init();
}
add_action( 'init', 'initialize_team', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple Team
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_Team {

	static $text_domain;

	static $post_type_name;

	static $admin_screen_id;
	
	/**
	 * Initialise
	 */
	public static function init() {
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_team_text_domain', 'Simple_Team' );

		self::$post_type_name = apply_filters( 'simple_team_post_type_name', 'simple_team' );

		self::$admin_screen_id = apply_filters( 'simple_team_admin_screen_id', 'simple_team' );

		add_action( 'init', array( __CLASS__, 'register' ) );
		
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
	
		add_image_size( 'team-admin-thumb', 60, 60, false );
		
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles_and_scripts' ) );
				
		add_filter( 'manage_edit-' . self::$post_type_name . '_columns' , array( __CLASS__, 'add_thumbnail_column') , 10 );
		
		add_action( 'manage_' . self::$post_type_name . '_posts_custom_column' , array( __CLASS__, 'thumbnail_column_contents') , 10, 2 );

		add_filter( 'enter_title_here', __CLASS__ . '::change_default_title' );

		add_filter( 'admin_post_thumbnail_html', __CLASS__ . '::change_featured_image_metabox_text' );

		add_filter( 'gettext', __CLASS__ . '::change_featured_image_link_text' );

		add_action( 'add_meta_boxes_' . self::$post_type_name, __CLASS__ . '::rename_featured_image_metabox' );

		add_filter( 'image_size_names_choose', __CLASS__ . '::remove_image_size_options' );
		
	}

	/**
	 * Register the post type
	 */
	public static function register() {
		
		$labels = array(
			'name'               => __( 'Team Members', self::$text_domain ),
			'singular_name'      => __( 'Member', self::$text_domain ),
			'all_items'          => __( 'All Team Members', self::$text_domain ),
			'add_new_item'       => __( 'Add New Team Member', self::$text_domain ),
			'edit_item'          => __( 'Edit Team Member', self::$text_domain ),
			'new_item'           => __( 'New Team Member', self::$text_domain ),
			'view_item'          => __( 'View Team Member', self::$text_domain ),
			'search_items'       => __( 'Search Team Members', self::$text_domain ),
			'not_found'          => __( 'No team members found', self::$text_domain ),
			'not_found_in_trash' => __( 'No team members found in trash', self::$text_domain ),
			'menu_name'      	 => __( 'Team', self::$text_domain ),
		);
		$args = array(
			'description' => __( 'Information about team members.', self::$text_domain ),
			'labels' => $labels,
			'public' => false,
			'show_ui' => true, 
			'query_var' => true,
			'has_archive' => false,
			'rewrite' => array( 'slug' => 'team-member', 'with_front' => false ),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => null,
			'taxonomies' => array(''),
			'show_in_nav_menus' => false,
			'supports' => array('title', 'editor', 'thumbnail', 'excerpt')
		); 
		
		register_post_type( self::$post_type_name , $args );
	}

	/**
	 * Filter the "post updated" messages
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		global $post;

		$messages[ self::$post_type_name ] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __('Team member updated. <a href="%s">View</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			2 => __('Custom field updated.', self::$text_domain ),
			3 => __('Custom field deleted.', self::$text_domain ),
			4 => __('Team member updated.', self::$text_domain ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __('Team member restored to revision from %s', self::$text_domain ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('Team member published. <a href="%s">View</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			7 => __('Team member saved.', self::$text_domain ),
			8 => sprintf( __('Team member submitted. <a target="_blank" href="%s">Preview</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
			9 => sprintf( __('Team member scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>', self::$text_domain ),
			  // translators: Publish box date format, see http://php.net/date
			  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post->ID) ) ),
			10 => sprintf( __('Team member draft updated. <a target="_blank" href="%s">Preview</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
		);

		return $messages;
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugins
	 *
	 * @since 1.0
	 */
	public static function enqueue_admin_styles_and_scripts() {
				
		if ( is_admin() ) {
	
			wp_register_style( 'simple-team', self::get_url( '/css/simple-team-admin.css', __FILE__ ) , false, '1.0' );
			wp_enqueue_style( 'simple-team' );
		
		}
		
	}
	
	/**
	 * Add the member details meta box
	 *
	 * @wp-action add_meta_boxes
	 */
	public static function add_meta_box() {
		add_meta_box( 'member-details', __( 'Member Details', self::$text_domain  ), array( __CLASS__, 'do_details_meta_box' ), self::$post_type_name , 'normal', 'high' );
		add_meta_box( 'member-social', __( 'Social Details', self::$text_domain  ), array( __CLASS__, 'do_social_meta_box' ), self::$post_type_name , 'normal', 'high' );
	}

	/**
	 * Output the member details meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_details_meta_box( $object, $box ) {
	
		wp_nonce_field( basename( __FILE__ ), 'team-member' );

		?>

		<p>
			<label for='team-members-role'>
				<?php _e( 'Role:', self::$text_domain ); ?>
				<input type='text' id='team-members-role' name='team-members-role' value='<?php echo esc_attr( get_post_meta( $object->ID, '_team-members-role', true ) ); ?>' />
			</label>
		</p>
		<p>
			<label for='team-members-email'>
				<?php _e( 'Email:', self::$text_domain ); ?>
				<input type='text' id='team-members-email' name='team-members-email' value='<?php echo esc_attr( get_post_meta( $object->ID, '_team-members-email', true ) ); ?>' />
			</label>
		</p>

<?php
	}
	
	/**
	 * Output the member details meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_social_meta_box( $object, $box ) {
	
		wp_nonce_field( basename( __FILE__ ), 'team-member' );

		?>

		<p>
			<label for='team-members-twitter'>
				<?php _e( 'Twitter:', self::$text_domain  ); ?>
				<input type='text' id='team-members-twitter' name='team-members-twitter' value='<?php echo esc_attr( get_post_meta( $object->ID, '_team-members-twitter', true ) ); ?>' />
			</label>
		</p>
		<p>
			<label for='team-members-linkedin'>
				<?php _e( 'LinkedIn:', self::$text_domain  ); ?>
				<input type='text' id='team-members-linkedin' name='team-members-linkedin' value='<?php echo esc_attr( get_post_meta( $object->ID, '_team-members-linkedin', true ) ); ?>' />
			</label>
		</p>

<?php
	}	

	/**
	 * Save the member details metadata
	 *
	 * @wp-action save_post
	 * @param int $post_id The ID of the current post being saved.
	 */
	public static function save_meta( $post_id ) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['team-member'] ) || !wp_verify_nonce( $_POST['team-member'], basename( __FILE__ ) ) )
			return $post_id;

		$meta = array(
			'team-members-role',
			'team-members-email',
			'team-members-twitter',
			'team-members-linkedin'
		);

		foreach ( $meta as $meta_key ) {
			$new_meta_value = $_POST[$meta_key];

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, '_' . $meta_key , true );

			/* If there is no new meta value but an old value exists, delete it. */
			if ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, '_' . $meta_key , $meta_value );

			/* If a new meta value was added and there was no previous value, add it. */
			elseif ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, '_' . $meta_key , $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, '_' . $meta_key , $new_meta_value );
		}
	}

	
	/**
	 * Helper function to get the URL of a given file. 
	 * 
	 * As this plugin may be used as both a stand-alone plugin and as a submodule of 
	 * a theme, the standard WP API functions, like plugins_url() can not be used. 
	 *
	 * @since 1.0
	 * @return array $post_name => $post_content
	 */
	public static function get_url( $file ) {

		// Get the path of this file after the WP content directory
		$post_content_path = substr( dirname( str_replace('\\','/',__FILE__) ), strpos( __FILE__, basename( WP_CONTENT_DIR ) ) + strlen( basename( WP_CONTENT_DIR ) ) );

		// Return a content URL for this path & the specified file
		return content_url( $post_content_path . $file );
	}	
	
	/**
	 * Add a column to the manage pages page to display the team member thumbnail. 
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Team
	 */
	public static function add_thumbnail_column( $columns ) {
	
  		$columns_start = array_slice( $columns, 0, 1, true );
  		$columns_end   = array_slice( $columns, 1, null, true );

  		$columns = array_merge(
    		$columns_start,
    		array( 'thumbnail' => __( '', self::$text_domain ) ),
    		$columns_end
  		);
	
		return $columns;
		
	}	
	
	/**
	 * Add the team member thumbnail to the custom column on the manage page.
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Team
	 */
	function thumbnail_column_contents( $column_name, $post_id ) {
				
		if ( $column_name != 'thumbnail' )
			return;
				
		if ( function_exists('the_post_thumbnail') )
			echo '<a href="' . get_edit_post_link( $post_id ) . '" title="' . __( 'Edit Team Member', self::$text_domain ) . '">' . get_the_post_thumbnail( $post_id, 'team-admin-thumb' ) . '</a>';
					
	}

	/**
	 * Replaces the "Enter title here" text
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Team
	 * @since 1.0
	 */
	public static function change_default_title( $title ){
		$screen = get_current_screen();

		if  ( self::$post_type_name == $screen->post_type )
			$title = __( 'Enter Team Member Name', self::$text_domain );

		return $title;
	}
	
	/**
	 * Replaces the 'Featured Image' label with 'Team Member Avatar' on the Edit page for the simple_team post type.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Team
	 * @since 1.0
	 */
	public static function change_featured_image_metabox_text( $metabox_html ) {

		if ( get_post_type() == self::$post_type_name )
			$metabox_html = str_replace( 'featured image', esc_attr__( 'Team Member Avatar', self::$text_domain ), $metabox_html );

		return $metabox_html;
		
	}


	/**
	 * Changes the 'Use as featured image' link text on the media panel
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Team
	 * @since 1.0
	 */
	public static function change_featured_image_link_text( $text ) {
		global $post;

		if ( $text == 'Use as featured image' ) {

			if ( isset( $_GET['post_id'] ) )
				$calling_post_id = absint( $_GET['post_id'] );
			elseif ( isset( $_POST ) && count( $_POST ) )
				$calling_post_id = $post->post_parent;
			else
				$calling_post_id = 0;

			if ( get_post_type( $calling_post_id ) == self::$post_type_name )
				$text = __( "Use as the team member avatar", self::$text_domain );

		}

		return $text;
	}


	/**
	 * Renames the "Featured Image" metabox to "Team Member Avatar"
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Team
	 * @since 1.0
	 */
	public static function rename_featured_image_metabox() {

		remove_meta_box( 'postimagediv', self::$post_type_name, 'side' );

		add_meta_box( 'postimagediv', __( "Team Member Avatar", self::$text_domain ), 'post_thumbnail_meta_box', self::$post_type_name, 'side', 'low' );

	}	

	/**
	 * Remove admin thumbnail size from the list of available sizes in the media uploader
	 *
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Team
	 * @since 1.0
	 */	
	public static function remove_image_size_options( $sizes ){
	 
		unset($sizes['team-admin-thumb']);
		
		return $sizes;
	 
	}
	
	/**#@+
	* @internal Template tag for use in templates
	*/
	/**
	* Get the team member's LinkedIn url
	*
	* @param int $post_ID Post ID. Defaults to the current post's ID
	*/
	public static function get_linkedin( $post_ID = 0 ) {

		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, '_team-members-linkedin', true);

	}
	
	/**
	* Get the team member's Twitter url
	*
	* @param int $post_ID Post ID. Defaults to the current post's ID
	*/
	public static function get_twitter( $post_ID = 0 ) {

		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, '_team-members-twitter', true);

	}
	
	/**
	* Get the team member's role
	*
	* @param int $post_ID Post ID. Defaults to the current post's ID
	*/
	public static function get_role( $post_ID = 0 ) {

		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, '_team-members-role', true);

	}	
	
	/**
	* Get the team member's email
	*
	* @param int $post_ID Post ID. Defaults to the current post's ID
	*/
	public static function get_email( $post_ID = 0 ) {

		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta($post_ID, '_team-members-email', true);

	}

};

endif;