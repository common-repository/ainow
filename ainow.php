<?php 
/*
Plugin Name: AINow
Description: This plugin will help you to suggest your visitors only the blog posts which they are actually attracted by, without the need you to do anything different from producing quality content. You WRITE, we CLASIFY! :-)
Version: 1.0
Author: GeroNikolov
Author URI: http:// dloober.com
License: GPLv2
*/

/*
*	LEGEND:
*	- AINOW_UUID:
*		- AINOW stays for Artificial Intelligence Knowledge.
*		- UUID stays for Unique User ID.
*	- Interests:
*		- The interest is get from the pages / posts which the user is visiting.
*		The name of the interest is based on the page / post title.
 */

class AI_NOW {
	function __construct() {
		// Setup PHP Core functions
		add_action( 'init', array( $this, 'init_ainow_core' ) );

		// Add scripts and styles for the Front-end part
		add_action( 'wp_enqueue_scripts', array( $this, 'add_front_JS' ), "1.0.0", "true" );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_front_CSS' ) );

		// Add scripts and styles for the Back-end part
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_JS' ), "1.0.0", "true" );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_CSS' ) );

		// Register AJAX call for UUID generating method
		add_action( 'wp_ajax_ainow_uuid_maker', array( $this, 'ainow_uuid_maker' ) );
		add_action( 'wp_ajax_nopriv_ainow_uuid_maker', array( $this, 'ainow_uuid_maker' ) );	

		// Register AJAX call for UUID setup in the $_SESSION
		add_action( 'wp_ajax_ainow_setup_uuid_global', array( $this, 'ainow_setup_uuid_global' ) );
		add_action( 'wp_ajax_nopriv_ainow_setup_uuid_global', array( $this, 'ainow_setup_uuid_global' ) );	

		// Register AJAX call for UUID setup in the $_SESSION
		add_action( 'wp_ajax_ainow_load_more_posts', array( $this, 'ainow_load_more_posts' ) );
		add_action( 'wp_ajax_nopriv_ainow_load_more_posts', array( $this, 'ainow_load_more_posts' ) );	

		// Setup AINOW information depending on the previewed page
		add_action( 'wp_footer', array( $this, 'ainow_setup_page' ) );	

		// Register the shortcodes
		add_action( 'init', array( $this, 'register_shortcodes' ) );

		// Register the Dashboad statistics page
		add_action( 'admin_menu', array( $this, 'ainow_statistics_page' ) );
	}

	// Register Dashboard statistics page function
	function ainow_statistics_page() {
		add_options_page( 'AINOW Statistics', 'AINOW Statistics', 'manage_options', 'ainow-statistics', array( $this, 'ainow_statistics' ) );
	}

	// Register statistics page function
	function ainow_statistics() { include( plugin_dir_path( __FILE__ ) . 'ainow_statistics.php'); }

	// Register shortcodes function
	function register_shortcodes() {
		add_shortcode( 'ainow_list', array( $this, 'register_ainow_list' ) );
	}

	// Setup PHP Core functions
	function init_ainow_core() {
		$this->create_ainow_users();		
		if( !session_id() ) { session_start(); }
	}

	// Setup AINOW information depending on the previewed page
	function ainow_setup_page() { 
		$_SESSION[ "AINOW_USER_ONPAGEID" ] = get_the_ID(); // Set the currently previewed page ID for global usage
		$_SESSION[ "AINOW_USER_ONPAGETITLE" ] = get_the_title( $_SESSION[ "AINOW_USER_ONPAGEID" ] ); // Set the currently previewed page TITLE for global usage                                                                                                                                                               
	}

	// Create AINOW Users table
	function create_ainow_users() {
		global $wpdb;

		$ainow_table = $wpdb->prefix ."ainow_users";

		if( $wpdb->get_var( "SHOW TABLES LIKE '$ainow_table'" ) != $ainow_table ) { // Create the AINOW_Users table only if it doesn't exists!
			$charset_collate = $wpdb->get_charset_collate();

			$sql_ = "
			CREATE TABLE $ainow_table (
				id INT NOT NULL AUTO_INCREMENT,
				user_uid LONGTEXT,
				user_interests LONGTEXT,
				user_interests_hits INT,
				user_ip LONGTEXT,
				PRIMARY KEY(id)
			) $charset_collate;
			";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql_ );
		}
	}

	// Register Front JS
	function add_front_JS() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'ainow-front-js', plugins_url( '/assets/scripts/front.js' , __FILE__ ), array(), '1.0', true );
		?>
		<script type="text/javascript">/*LOAD THE AJAXURL FOR FRONT*/ var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";</script>
		<?php
	}

	// Register Front CSS
	function add_front_CSS() {
		wp_enqueue_style( 'ainow-front-css', plugins_url( '/assets/css/front.css', __FILE__ ), array(), '1.0', 'screen' );
	}

	// Register Admin JS
	function add_admin_JS() {
		wp_enqueue_script( 'ainow-admin-js', plugins_url( '/assets/scripts/admin.js' , __FILE__ ), array('jquery'), '1.0', true );		
	}

	// Register Admin CSS
	function add_admin_CSS( $hook ) {
		wp_enqueue_style( 'ainow-admin-css', plugins_url( '/assets/css/admin.css', __FILE__ ), array(), '1.0', 'screen' );
	}

	// Tracking function --> Used to collect information for the current logged user & train the algorithm
	function ainow_tracking() {
		$unique_user_id = $_SESSION[ "AINOW_UUID" ];

		if ( get_post_type( $_SESSION[ "AINOW_USER_ONPAGEID" ] ) == "post" ) {
			$_USER_ONPAGETITLE = $_SESSION[ "AINOW_USER_ONPAGETITLE" ];		

			// Stay in touch with the Database
			global $wpdb;

			$ainow_table = $wpdb->prefix ."ainow_users";

			$sql_ = "SELECT * FROM $ainow_table WHERE user_uid='$unique_user_id' AND user_interests='$_USER_ONPAGETITLE'";
			$registered_user_interest = $wpdb->get_results( $sql_, OBJECT )[ 0 ];
			
			if ( isset( $registered_user_interest ) && !empty( $registered_user_interest ) ) { // There is already an interest like that, so we are going to add one hit more
				$registered_user_interest_hits = $registered_user_interest->user_interests_hits + 1;

				$wpdb->update(
						$ainow_table,
						array(
								"user_interests_hits" => $registered_user_interest_hits
							),
						array(
								"user_uid" => $unique_user_id,
								"user_interests" => $_USER_ONPAGETITLE
							)
					);
			} else { // There isn't interest like that so we are going to add it with one hit
				$wpdb->insert(
						$ainow_table,
						array(							
								"user_uid" => $unique_user_id,
								"user_interests" => $_USER_ONPAGETITLE,
								"user_interests_hits" => 1,
								"user_ip" => $_SERVER[ "REMOTE_ADDR" ]
							)
					);
			}
		}
	}

	// Setup the already built UUID into the $_SESSION
	function ainow_setup_uuid_global() {		
		if ( empty( $_SESSION[ "AINOW_UUID" ] ) || !isset( $_SESSION[ "AINOW_UUID" ] ) ) {			
			$unique_user_id = $_POST[ "data" ];
			$_SESSION[ "AINOW_UUID" ] = $unique_user_id;		
		}

		// Start tracking
		$this->ainow_tracking();

		die();
	}

	// Build UUID function handler --> Used to generate the new users UUID
	function ainow_uuid_maker() {		
		global $wpdb;

		$this->create_ainow_users();
		$ainow_table = $wpdb->prefix ."ainow_users";

		$sql_ = "SELECT id FROM $ainow_table ORDER BY ID DESC LIMIT 1";
		$last_id = $wpdb->get_results( $sql_, OBJECT )[ 0 ];

		if ( empty( $last_id ) || !isset( $last_id ) || $last_id == 0 ) { $last_id = 1; }
		else { $last_id = $last_id->id; }

		$unique_user_id = $last_id ."@". $_SERVER[ "HTTP_HOST" ];

		// Setup UUID handler
		$_SESSION[ "AINOW_UUID" ] = $unique_user_id;
		echo $unique_user_id;			

		// Start tracking
		$this->ainow_tracking();
		
		die();
	}

	// Add post ID to the stack function
	function add_posts( $posts_stack, $posts_ ) {
		foreach ( $posts_ as $post_ ) { if ( !in_array( $post_->id, $posts_stack ) ) { array_push( $posts_stack, $post_->id ); } }
		return $posts_stack;
	}

	// AINOW List shortcode implementation function
	function register_ainow_list( $atts ) {
		extract( 
			shortcode_atts( 
				array( 
					'load_btn_text' => 'Load More'
				), $atts 
			)
		);

		//***The real deal starts here***//
		global $wpdb;
		$_HTML_RESULT = "<div id='ainow-posts-list'>"; // This is the end result which the shortcode will return once it finish with the calculations		
		$unique_user_id = $_SESSION[ "AINOW_UUID" ];

		$ainow_table = $wpdb->prefix ."ainow_users";		
		$sql_ = "
		SELECT user_interests FROM $ainow_table 
		WHERE user_uid='$unique_user_id' 
		ORDER BY user_interests_hits DESC LIMIT 5"; // Get the first five interests
		$top_user_interests = $wpdb->get_results( $sql_, OBJECT );				

		$_SESSION[ "AINOW_USER_INTERESTS_OFFSET" ] = 0; // Set the default User Interests offset to 0 since it will be incremented in the loop when posts are gathered
		$_SESSION[ "AINOW_ALREADY_LISTED_IDs" ] = array(); // Set the default IDs handler

		$wp_posts_table = $wpdb->prefix ."posts"; // Set the WP posts table
		$posts_stack = array(); // This is going to handle all from the posts in the current interests

		// Collect posts for each of the interests
		foreach ( $top_user_interests as $interest_ ) {			
			$interest_ = $interest_->user_interests; // Switch the object to just string for easier manipulations			
			                                        			                                         
			if ( substr_count( $interest_, ' ' ) > 1 ) { 
				$key_words = NULL;
				preg_match_all( '/[A-Za-z0-9\.]+(?: [A-Za-z0-9\.]+)?/', $interest_, $key_words );
				$interest_ = $key_words;
			} // Split the sentence on key word combinations	

			if ( !is_array( $interest_ ) ) {
	 			$sql_ = "SELECT id FROM $wp_posts_table WHERE post_content LIKE '%$interest_%' AND post_status='publish' AND post_type='post' ORDER BY post_date DESC LIMIT 5";
	 			$posts_ = $wpdb->get_results( $sql_, OBJECT );
	 			if ( !empty( $posts_ ) && isset( $posts_ ) ) { $posts_stack = $this->add_posts( $posts_stack, $posts_ ); }	 
	 		} else {
	 			$current_interest = 0;
	 			$count_interests = count( $interest_[0] );
	 			$sql_ = "SELECT id FROM $wp_posts_table WHERE ( ";
 				foreach ( $interest_[0] as $key_word ) {
 					preg_replace("/[^A-Za-z0-9\s+]/", '', $key_word );
 					if ( !empty( trim( $key_word ) ) && null !== trim( $key_word ) ) {	
			 			$sql_ .= "post_content LIKE '%$key_word%'";			 			
			 			if ( $current_interest < $count_interests - 1 ) { $sql_ .= " OR "; }
			 		}
			 		$current_interest += 1;		 			
	 			}
	 			$sql_ .= " ) AND ( post_status='publish' AND post_type='post' ) ". $this->not_in_list() ." ORDER BY post_date DESC LIMIT 5";

	 			$posts_ = $wpdb->get_results( $sql_, OBJECT );

 				if ( !empty( $posts_ ) && isset( $posts_ ) ) { 					
 					$posts_stack = $this->add_posts( $posts_stack, $posts_ ); 					
 				}
	 		}

	 		if ( !empty( $posts_stack ) ) { $this->add_ids_to_ignore( $posts_stack ); } // Copy the current IDs to the ignore list 
 			$_SESSION[ "AINOW_USER_INTERESTS_OFFSET" ] += 1; // Add 1 for each of the interests which passed away

 			if ( count( $posts_stack ) >= 5 ) { break; } // In case there are too much posts (>= 5) we should stop adding posts, because this may cause slow response, big loading time and in rare cases this may crashed the server SQL (if the server is too weak)
      	}	          
		
      	// We should add more posts to the user attention in case there are fewer suggestions
		if ( empty( $posts_stack ) || !isset( $posts_stack ) || ( count( $posts_stack ) < 5 ) ) {		
			$args = array(
				'posts_per_page'   => 5,
				'offset'           => 0,				
				'orderby'          => 'date',
				'order'            => 'DESC',
				'post_type'        => 'post',				
				'post_status'      => 'publish',	
				'post__not_in'    => $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ], 			
				'suppress_filters' => true 
			);
			$query_ = query_posts( $args );

			foreach ( $query_ as $post_ ) { array_push( $posts_stack, $post_->ID ); }			

			wp_reset_query();
		}		

		// Add the current post IDs to exclude list
		if ( !empty( $posts_stack ) ) { $this->add_ids_to_ignore( $posts_stack ); }

		// Build the posts from the stack		
		foreach ( $posts_stack as $post_ ) {				
			$post_id = $post_;	
			if ( get_post_status( $post_id ) !== FALSE ) {
				$post_title = get_the_title( $post_id );
				$post_url = get_permalink( $post_id );
				$post_content = wp_trim_words( get_post_field( 'post_content', $post_id ), 25, "..." );
				$post_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' )[0];

				$_HTML_RESULT .= "
				<a href='$post_url' class='post-anchor'>
					<div id='post-$post_id' class='post-container'>
						<div class='featured-image' style='background-image: url($post_image);'></div>
						<div id='post-content'>
							<h1 class='post-title'>$post_title</h1>
							<div class='post-content'>$post_content</div>
						</div>
					</div>
				</a>
				";
			}
		}

		$_HTML_RESULT .= "</div><button id='ainow-load-more-posts'>". $load_btn_text ."</button>"; // Close the listing <div>
		return $_HTML_RESULT; 
	}

	// Generates not_in_list query function
	function not_in_list() {
		$list_lenght = count( $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ] );
		$list_pointer = 0;		
		$not_in_query = "";
		if ( !empty( $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ] ) ) {
			$not_in_query = "AND id NOT IN (";
			foreach ( $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ] as $post_id ) {
				$not_in_query .= "'$post_id'";
				$list_pointer += 1;
				if ( $list_pointer < $list_lenght ) { $not_in_query .= ","; }
			}
			$not_in_query .= ")";
		}		

		return $not_in_query;
	}

	// Add IDs to the ignore list
	function add_ids_to_ignore( $ids_ ) { 
		foreach ( $ids_ as $id_ ) { 
			if ( !in_array( $id_, $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ] ) ) { array_push( $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ], $id_ ); }
		}
	}

	// Load More Posts function
	function ainow_load_more_posts() {
		global $wpdb;		
		$unique_user_id = $_SESSION[ "AINOW_UUID" ];

		$ainow_table = $wpdb->prefix ."ainow_users";		
		$sql_ = "
		SELECT user_interests FROM $ainow_table 
		WHERE user_uid='$unique_user_id' 
		ORDER BY user_interests_hits DESC LIMIT 5 
		OFFSET ". $_SESSION[ 'AINOW_USER_INTERESTS_OFFSET' ]; // Get the first five interests skipping the previous listed interests		                                                                                                                                                                                 
		$top_user_interests = $wpdb->get_results( $sql_, OBJECT );

		$wp_posts_table = $wpdb->prefix ."posts"; // Set the WP posts table
		$posts_stack = array(); // This is going to handle all from the posts in the current interests

		// Collect posts for each of the interests
		foreach ( $top_user_interests as $interest_ ) {			
			$interest_ = $interest_->user_interests; // Switch the object to just string for easier manipulations			
			                                        			                                         
			if ( substr_count( $interest_, ' ' ) > 1 ) { 
				$key_words = NULL;
				preg_match_all( '/[A-Za-z0-9\.]+(?: [A-Za-z0-9\.]+)?/', $interest_, $key_words );
				$interest_ = $key_words;
			} // Split the sentence on key word combinations

			if ( !is_array( $interest_ ) ) {
	 			$sql_ = "
	 			SELECT id FROM $wp_posts_table 
	 			WHERE post_content 
	 			LIKE ( '%$interest_%' AND post_status='publish' AND post_type='post' ) ". $this->not_in_list() ."
	 			ORDER BY post_date DESC LIMIT 5";
	 			$posts_ = $wpdb->get_results( $sql_, OBJECT );
	 			if ( !empty( $posts_ ) && isset( $posts_ ) ) { $posts_stack = $this->add_posts( $posts_stack, $posts_ ); }	 
	 		} else {
 				foreach ( $interest_ as $key_word ) { 				
 					$count_interests = count( $interest_[0] );
		 			$sql_ = "SELECT id FROM $wp_posts_table WHERE ( ";
	 				foreach ( $interest_[0] as $key_word ) {
	 					preg_replace("/[^A-Za-z0-9\s+]/", '', $key_word );
	 					if ( !empty( trim( $key_word ) ) && null !== trim( $key_word ) ) {	
				 			$sql_ .= "post_content LIKE '%$key_word%'";			 			
				 			if ( $current_interest < $count_interests - 1 ) { $sql_ .= " OR "; }
				 		}
				 		$current_interest += 1;		 			
		 			}
		 			$sql_ .= " ) AND ( post_status='publish' AND post_type='post' ) ". $this->not_in_list() ." ORDER BY post_date DESC LIMIT 5";

	 				$posts_ = $wpdb->get_results( $sql_, OBJECT );
	 				
	 				if ( !empty( $posts_ ) && isset( $posts_ ) ) { 
	 					if ( count( $posts_stack ) >= 5 ) { break; } // Break the collecting in case there are too much suggestions
	 					else { $posts_stack = $this->add_posts( $posts_stack, $posts_ ); }
	 				}
	 			}
	 		}

	 		if ( !empty( $posts_stack ) ) { $this->add_ids_to_ignore( $posts_stack ); } // Copy the newly generated IDs to the ignore list
 			$_SESSION[ "AINOW_USER_INTERESTS_OFFSET" ] += 1; // Add 1 for each of the interests which passed away

 			if ( count( $posts_stack ) >= 5 ) { break; } // In case there are too much posts (>= 5) we should stop adding posts, because this may cause slow response, big loading time and in rare cases this may crashed the server SQL (if the server is too weak)
      	}

      	if ( !empty( $posts_stack ) ) { $this->add_ids_to_ignore( $posts_stack ); }

      	// We should add more posts to the user attention in case there are fewer suggestions
		if ( empty( $posts_stack ) || !isset( $posts_stack ) || count( $posts_stack ) < 5 ) {	
			$args = array(
				'posts_per_page'   => 5,
				'offset'           => 0,				
				'orderby'          => 'date',
				'order'            => 'DESC',
				'post_type'        => 'post',				
				'post_status'      => 'publish',	
				"post__not_in"	   => $_SESSION[ "AINOW_ALREADY_LISTED_IDs" ],
				'suppress_filters' => true 
			);
			$query_ = query_posts( $args );

			foreach ( $query_ as $post_ ) { array_push( $posts_stack, $post_->ID ); }

			wp_reset_query();			
		}
		
		if ( !empty( $posts_stack ) ) { $this->add_ids_to_ignore( $posts_stack ); }

		// Build the posts from the stack
		$_HTML_RESULT = "";		
		foreach ( $posts_stack as $post_ ) {				
			$post_id = $post_;	
			if ( get_post_status( $post_id ) !== FALSE ) {
				$post_title = get_the_title( $post_id );
				$post_url = get_permalink( $post_id );
				$post_content = wp_trim_words( get_post_field( 'post_content', $post_id ), 25, "..." );
				$post_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' )[0];

				$_HTML_RESULT .= "
				<a href='$post_url' class='post-anchor'>
					<div id='post-$post_id' class='post-container'>
						<div class='featured-image' style='background-image: url($post_image);'></div>
						<div id='post-content'>
							<h1 class='post-title'>$post_title</h1>
							<div class='post-content'>$post_content</div>
						</div>
					</div>
				</a>
				";
			}
		}

		// Return response
		echo $_HTML_RESULT;
		die();
	}
};

$call_ai_now = new AI_NOW();
?>