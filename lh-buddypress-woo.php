<?php
/**
 * Plugin Name: LH Buddypress Woo
 * Description: Add WooCommerce My Account area to BuddyPress account
 * Version: 1.00
 * Author: Peter Shaw
 * Author URI: https://shawfactor.com/
 * License: GPLv2
 * Text Domain: lh_bp_woo
 * Domain Path: /languages
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


    /**
     * Override WooCommerce default "is_add_payment_method_page" method so that it returns true if we're on the BuddyPress equivalent
     */
     
    if( ! function_exists('is_add_payment_method_page') ) {
        function is_add_payment_method_page() {
            global $wp;
            
            if( isset( $wp->query_vars['add-payment-method'] ) ) 
                return true;
    
            return ( get_the_ID() && (get_the_ID() ==  wc_get_page_id( 'myaccount' ) ) && isset( $wp->query_vars['add-payment-method'] ) );
        }
    }

if (!class_exists('LH_buddypress_woocommerce_plugin')) {


class LH_buddypress_woocommerce_plugin {

    private static $instance;
         
        static function return_plugin_namespace(){
    
    return 'lh_bp_woo';
    
    }

static function return_review_activity_type(){
    
    return self::return_plugin_namespace().'-review_activity';
    
    
}

static function return_order_activity_type(){
    
    return self::return_plugin_namespace().'-order_activity';
    
}

static function plugin_name(){
    
    return 'LH Buddypress Woocommerce';
    
    
}

static function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(plugin_basename( __FILE__ ).' - '.print_r($log, true));
            } else {
                error_log(plugin_basename( __FILE__ ).' - '.$log);
            }
        }
    }
    
static function bump_activity_date($activity_id){
    

    
global $wpdb;
    
$sql = "Update ".buddypress()->activity->table_name." SET date_recorded = '".bp_core_current_time()."' WHERE id = '".$activity_id."' and component = 'blogs'";

return $wpdb->query($sql);
    

    
    
    
    
}
    
static function maybe_update_activity($post_object){
    
    
    if ($post_object->post_type == 'product'){
    
    $user_object = wp_get_current_user();
    
    	// Get the Activity id
	$args = array(
		'item_id'           => get_current_blog_id(),
		'user_id'			=> $user_object->ID,
		'secondary_item_id'	=> $post_object->ID,
		'component' 		=> 'blogs',
		'type' 				=> self::return_plugin_namespace().'-product_update',
	);
	

	
	$activity_id = bp_activity_get_activity_id( $args );
	
	    if (is_numeric($activity_id)){
		    
            self::bump_activity_date($activity_id);
            return $activity_id;
		    
		    
	    } else {
		    
	        return false;
		    
        }
    
    
    }
    
    return false;
}

static function do_add_activity($post_object){
    

if ($post_object->post_type == 'product'){
     
    if (empty($user_object)){
    
        $user_object = get_user_by( 'ID', $post_object->post_author);
    
    }
    
    $user_object = wp_get_current_user();



    $userlink = bp_core_get_userlink( $user_object->ID );      
    $string = sprintf( __( '%s updated', self::return_plugin_namespace() ), $userlink );
    $string .= sprintf( __( ' <a href="%s">%s</a>', self::return_plugin_namespace() ), get_the_permalink($post_object->ID), get_the_title($post_object->ID) );
    $string .= sprintf( __( ' on <a href="%s">%s</a> ', self::return_plugin_namespace() ), get_bloginfo('url'), get_bloginfo('name') ); 

    $original_string = $string;
    
    	//Create BP Activity
	$bp_activity_id = bp_activity_add(array(
        'action' => apply_filters( self::return_plugin_namespace().'_do_add_update_activity', $string, $original_string, $post_object, $user_object ),
		'component' 		=> 'blogs',
		'type' 				=> self::return_plugin_namespace().'-product_update',
		'primary_link' 		=> get_the_permalink($post_object->ID),
		'user_id' 			=> $user_object->ID,
		'item_id' 			=> get_current_blog_id(),
		'secondary_item_id'		=> $post_object->ID,
     ));
     
    return $bp_activity_id;

    
}



}
    
static function maybe_add_or_update_activity($post_object){
    
    

    
    if (self::maybe_update_activity($post_object)){
        
        return true;
        
    } else {
        
        self::do_add_activity($post_object);
        
    }
    
}
        

    /*
     * Use the BuddyPress "Account Settings" page (/members/username/settings/) instead of the WooCommerce "Edit Account" page (/my-account/edit-account)
     * The WooCommerce page doesn't have "Display name publicly as..."
     */
    public function customer_edit_account_url( $edit_account_url = "" ) {
        return bp_loggedin_user_domain() . "settings";
    }


    /*
     * Add WooCommerce "My Account" to BuddyPress profile
     * http://xd3v.com/create-a-premium-social-network-with-woocommerce/
     */
    public function bp_navigation() {
       
        
        if (!wp_doing_ajax() && !wp_is_json_request() && !defined('XMLRPC_REQUEST') && !defined('DOING_CRON') && !is_robots() && (bp_current_component() or is_user_logged_in())){
        
        $account_url = trailingslashit( buddypress()->loggedin_user->domain . 'account' );
        $secure_account_url = str_replace( 'http:', 'https:', $account_url );
        
        $wc_account_menu_items = $this->get_wc_account_menu_items();

        // Add top-level Account menu item
        $foobar = bp_core_new_nav_item(
            array(
                'name' => __( 'Account', 'buddypress-woocommerce' ), 
                'slug' => 'account',
                'default_subnav_slug' => 'view',
                'show_for_displayed_user' => false, 
                'position' => 30,
                'item_css_id' => 'account',
            )
        );
        

        

          
        $position = 0;
        foreach ( $wc_account_menu_items as $key => $item_title ) {
            $position += 10;
            if ( $key == 'dashboard') $key = 'view';
            if ( strpos( $key, 'my-membership-details') !== false ) $key = 'members-area'; // WooCommerce Memberships: Don't link directly to a "My Membership Details" area because it requires unique ID in URL

            bp_core_new_subnav_item(
                array(
                    'name' => __( $item_title, 'buddypress-woocommerce' ),
                    'slug' => $key,
                    'parent_url' => $secure_account_url,
                    'parent_slug' => 'account',
                    'screen_function' => array( $this, 'account_screens' ),
                    'show_for_displayed_user' => false,
                    'position' => $position,
                    'item_css_id' => 'account-' . $key,
                )
            );              
        }

        // Remove "Settings > Delete Account" 
        bp_core_remove_subnav_item( 'settings', 'delete-account' );
        
        }
    }

     /**
      * Get $key => $value array of WooCommerce Account menu items for BuddyPress Account menu
      */
     public function get_wc_account_menu_items() {
          // Start with the WooCommerce Account menu items
          $wc_account_menu_items = wc_get_account_menu_items();         

          // Add new items
          $wc_account_menu_items['add-payment-method'] = "Add Payment Method";

          // Remove items that are on other BuddyPress menus
          unset( $wc_account_menu_items['customer-logout'] );
          unset( $wc_account_menu_items['edit-account'] );
          
          return $wc_account_menu_items;
     }
     
     
    /**
     * These are the screen_functions used by our custom BuddyPress navigation items
     */
    function account_screens() {
        // Avoid 404 error in WooCommerce 3.6+
        add_filter( 'woocommerce_is_account_page', '__return_true' );

        //add_action( 'bp_template_title', array( $this, 'account_screen_title' ) );
        add_action( 'bp_template_content', array( $this, 'account_content' ) );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    function account_screen_title() {
        echo 'My Account';
    }
    function account_content() {
        wc_print_notices();
        do_action( 'woocommerce_account_content' );
    }


    /**
     * Point WooCommerce endpoints to BuddyPress My Account pages
     */
    public function get_endpoint_url( $url, $endpoint, $value, $permalink ) {		
        $base_path = bp_loggedin_user_domain() . "account/";
        $endpoint_path = $base_path . $endpoint . "/";
        $endpoint_value_path = $endpoint_path . $value;
        
        $wc_account_menu_items = $this->get_wc_account_menu_items();
        $wc_account_menu_items["delete-payment-method"] = "Delete Payment Method";
        $wc_account_menu_items["set-default-payment-method"] = "Set Default Payment Method";          

        if( $endpoint == "edit-account" ) {
            return $this->customer_edit_account_url();
        }
        elseif ( strpos( $endpoint, 'my-membership-details') !== false ) { // WooCommerce Memberships: Can't display "My Membership Details" area because of unique ID in URL
            return $url;
        }
        elseif ( array_key_exists( $endpoint, $wc_account_menu_items ) )  {
            if( $value )
                return $endpoint_value_path;
            else
                return $endpoint_path;         
        }
        else {
           return $url;
        }
        

    }
    
public function redirect_if_logged_in() { 


if ( is_singular() && is_user_logged_in()) { 
    

    
if (get_the_ID() && (get_the_ID() == wc_get_page_id( 'myaccount' ))) { 
    


        
        $account_url = trailingslashit( buddypress()->loggedin_user->domain . 'account' );
    
wp_redirect($account_url, 302, self::plugin_name());exit();


}
    
    
}


}

public function remove_external_plugin_support($post_types){
    
    unset($post_types['product']);
    
    
    return $post_types;
    
}

public function add_tracking(){
    
    add_post_type_support('product', 'buddypress-activity');
    
    bp_activity_set_post_type_tracking_args( 'product', array(
        'action_id'                         => 'new_blog_product',
        'bp_activity_admin_filter'          => __( 'New product created', self::return_plugin_namespace() ),
        'bp_activity_front_filter'          => __( 'Product', self::return_plugin_namespace() ),
        'bp_activity_new_post'              => __( '%1$s created a new <a href="%2$s">Product</a>', self::return_plugin_namespace() ),
        'bp_activity_new_post_ms'           => __( '%1$s created a new <a href="%2$s">product</a>, on the site %3$s', self::return_plugin_namespace() ),
        'component_id'                      => 'blogs',
        'contexts'                          => array( 'activity', 'member' ),
        'comment_action_id'                 => self::return_review_activity_type(),
        'bp_activity_comments_admin_filter' => __( 'New review posted', self::return_plugin_namespace() ),
        'bp_activity_comments_front_filter' => __( 'Product reviews', self::return_plugin_namespace() ),
        'bp_activity_new_comment'           => __( '%1$s reviewed the <a href="%2$s">product</a>', self::return_plugin_namespace() ),
        'bp_activity_new_comment_ms'        => __( '%1$s  reviewed the <a href="%2$s">product</a>, on the site %3$s', self::return_plugin_namespace() ),
        'position'                          => 100,
    ) );
    
    
    
    bp_activity_set_action(
        'blogs',
        self::return_plugin_namespace().'-product_update',
        __( 'Product update', self::return_plugin_namespace() ),
        'plugin_format_activity_action_plugin_action',
        __( 'Updated product', self::return_plugin_namespace() ),
        array( 'activity','member' )
    );
    
    
    add_action( 'post_updated', array($this,'handle_on_save'), 10, 3 ); 
    add_action( 'delete_post', array($this,'handle_on_delete_or_trash'), 10, 2 );  
    add_action( 'trashed_post', array($this,'handle_on_delete_or_trash'), 10, 1 ); 
    
    
}


public function handle_on_save($post_id, $post_after, $post_before){
    
    remove_action( 'post_updated', array($this,'handle_on_save'), 10, 3 ); 
    
	// if activity module is not active, why bother.
	if ( ! bp_is_active( 'activity' ) ) {
		
		return false;
    
	}

	if ( wp_is_post_revision( $post_id ) ) {
		
		return;
	
	}
	
	if ( wp_is_post_autosave( $post_id ) ) {
	    
	    return;
	    
	}
    
    
    if (empty($post_after) or ($post_after->post_type != 'product')){
        
        return;
        
    }
    
    
    //Need check for publish post status
    if ( 'publish' != $post_after->post_status && 'logged_in' != $post_after->post_status ) {
		
		return;
	
	}
	
	//check it's not a new post
    if( $post_after->post_modified_gmt == $post_after->post_date_gmt ){
		
		return;
    
	}
	
	$update_delay = 3600;
	$current_time = current_time( 'timestamp', 1 );
	$last_updated = get_post_modified_time( 'G', 1 );
	$published = strtotime( $post_after->post_date_gmt );
	$time_from_last_update = $current_time - $last_updated;
	$time_from_creation = $current_time - $published;
	
	if ( $time_from_last_update <= $update_delay || $time_from_creation <= $update_delay ) {
        
        return;
		
	}
	
	self::maybe_add_or_update_activity($post_after);
	
    
    
    
}

public function handle_on_delete_or_trash($post_id, $post_object = null ){
    

    
    
    if (empty($post_object)){
        
        $post_object = $post = get_post( $post_id );
        
        
    }
    
    
    
    
}

public function register_plugin_actions(){
    
        bp_activity_set_action(
        'blogs',
        self::return_review_activity_type(),
        __( 'Product Review', self::return_plugin_namespace() ),
        'plugin_format_activity_action_plugin_action',
        __( 'Product Reviews', self::return_plugin_namespace() ),
        array( 'activity','member' )
    );
    
        bp_activity_set_action(
        'blogs',
        self::return_order_activity_type(),
        __( 'Order placed', self::return_plugin_namespace() ),
        'plugin_format_activity_action_plugin_action',
        __( 'Orders placed', self::return_plugin_namespace() ),
        array( 'activity','member' )
    );
    
}

public function add_order_activities_and_notifications($order_id, $posted_data, $order ){
    
    
    
}

public function add_review_activities_and_notifications( $comment_id, $comment_data ) {
    
            if ($comment_data->comment_type != 'review'){
                return false;
            }
    
    		// Get the product data
			$product = get_post( $comment_data->comment_post_ID );

			if ( $product->post_type != 'product' ) {
				return false;
			}

    
    
    
}
    
     /*
      * Initialize the plugin hooks if WooCommerce and BuddyPress are active
      */
     public function plugin_init() {
         
         if ( class_exists( 'WooCommerce' ) ) {
         
            // Add WooCommerce navigation and subnavigation to BuddyPress
            add_action( 'bp_parse_query', array( $this, 'bp_navigation') );
          
            // Re-route WooCommerce Edit Account URL
            add_filter( 'woocommerce_customer_edit_account_url', array( $this, 'customer_edit_account_url' ),10,1 );

            // Re-route all WooCommerce URL endpoints to appropriate BuddyPress pages
            add_filter( 'woocommerce_get_endpoint_url', array( $this, 'get_endpoint_url' ), 10, 4 );  
          
            //redirect request for My Account to the Buddypress alternative
            add_action('template_redirect', array($this,'redirect_if_logged_in'));
            
            
            //this section handles activity actions for woocommerce
            
            //remove products from LH Buddypress Custom Posts and page activity tracking
            add_filter( 'lh_bp_cpp_a_get_applicable_publish_post_types_filter', array( $this, 'remove_external_plugin_support' ),10,1 );
            add_filter( 'lh_bp_cpp_a_get_applicable_update_post_types_filter', array( $this, 'remove_external_plugin_support' ),10,1 );
            
            
            
            //add post type tracking for products
            add_action( 'init', array($this,'add_tracking'), 1000000000 );

          
            //register new actions
            add_action( 'bp_register_activity_actions', array($this,'register_plugin_actions') );
          
            //add a notification when a order is created
            add_action( 'woocommerce_checkout_order_processed', array($this,'add_order_activities_and_notifications'), 10, 3 );
          
            //add an activity and notificationb when a review is written
            add_action( 'wp_insert_comment', array( $this, 'add_review_activities_and_notifications' ), 10, 2 );
          
         }
     }
     
   /**
     * Gets an instance of our plugin.
     *
     * using the singleton pattern
     */
    public static function get_instance(){
        if (null === self::$instance) {
            self::$instance = new self();
        }
 
        return self::$instance;
    }
    
    
    public function __construct() {
            
        //potentially load translations
        load_plugin_textdomain( self::return_plugin_namespace(), false, basename( dirname( __FILE__ ) ) . '/languages' );
        
        // Check for BuddyPress and initialize if it is active
        add_action( 'bp_include', array( $this, 'plugin_init' ) );

    }
    
    
}

$lh_buddypress_woocommerce_instance = LH_buddypress_woocommerce_plugin::get_instance();


}

?>
