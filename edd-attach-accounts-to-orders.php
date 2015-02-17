<?php

/**
 * Plugin Name:         Easy Digital Downloads Attach Accounts to Orders
 * Plugin URI:          http://www.chriscct7.com
 * Description:         Attach users to orders
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 *
 * Contributors:        chriscct7
 *
 * Version:             2.0
 * Requires at least:   3.9
 * Tested up to:        4.2
 *
 * Text Domain:         edd_ead
 * Domain Path:         /languages/
 *
 * @category            Plugin
 * @copyright           Copyright � 2015 Chris Christoff
 * @author              Chris Christoff
 */

// todo: Suppress emails on setups where admin emailed on new user creation

function aato_register_page() {
	add_submenu_page( null, __( 'EDD Attach Accounts to Orders', 'edd_ead' ), __( 'EDD Attach Accounts to Orders', 'edd_ead' ), 'install_plugins', 'aato-attach', 'aato_attachment_screen' );
}
add_action( 'admin_menu', 'aato_register_page', 10 );

function aato_attachment_screen() {
	$step        = isset( $_GET['step'] )         ? absint( $_GET['step'] )         : 1;
	$orders      = $step == 1                     ? 0                               : ($step - 1) * 10;
	$fixed       = isset( $_GET['fixed'] )        ? absint( $_GET['fixed'] )        : 0;
	$create      = isset( $_GET['create_users'] ) ? absint( $_GET['create_users'] ) : 0;
	$created     = isset( $_GET['created'] )      ? absint( $_GET['created'] )      : 0;
	$counts      = wp_count_posts( 'edd_payment' );
	$total       = isset( $counts->publish )      ? $counts->publish                : 1;
	$total_steps = round( ( $total / 10 ), 0 );
	?>
	<div class="wrap">
		<h2><?php _e( 'EDD Attach Accounts to Orders', 'edd_ead' ); ?></h2>
		<div id="edd-upgrade-status">
			<p><?php _e( 'The account to order attachment process is running, please be patient. This could take several minutes to complete.', 'edd_ead' ); ?></p>
			<p><strong><?php printf( __( 'Step %d of approximately %d running', 'edd_ead' ), $step, $total_steps ); ?></p>
			<p><strong><?php printf( __( '%d orders analyzed', 'edd_ead' ), $orders ); ?></p>
			<p><strong><?php printf( __( '%d accounts attached to orders so far', 'edd_ead' ), $fixed ); ?></p>
			<?php if ( $create ){ ?> 
			<p><strong><?php printf( __( '%d accounts created so far', 'edd_ead' ), $created ); ?></p>
			<?php } ?>
		</div>
		<script type="text/javascript">
			document.location.href = "index.php?page=aato-attach&edd_action=<?php echo $_GET['aato_page']; ?>&step=<?php echo absint( $_GET['step'] ); ?>&fixed=<?php echo absint( $_GET['fixed'] ); ?>&create_users=<?php echo absint( $_GET['create_users'] ); ?>&created=<?php echo absint( $_GET['created'] ); ?>";
		</script>
	</div>
<?php	
}


function attach_accounts_to_orders_notice() {
	if ( ! ( isset( $_GET['page'] ) && $_GET['page'] == 'aato-attach' ) ) { 
		printf(
			 __( '<div class="updated"><p>' . __( 'Attach Accounts to Orders and ', 'edd_ead' ) .' <a href="%s"> make accounts </a> or ', 'edd_ead' ),
			esc_url( add_query_arg( array( 'page' => 'aato-attach', 'edd_action' => 'attach_accounts_to_orders', 'create_users' => '1' ), admin_url() ) )
		);
		printf(
			 __( ' <a href="%s"> do not make accounts </a> when the user doesn\'t have an account already. ' . '</p></div>', 'edd_ead' ),
			esc_url( add_query_arg( array( 'page' => 'aato-attach', 'edd_action' => 'attach_accounts_to_orders', 'create_users' => '0' ), admin_url() ) )
		);
	}
}
add_action( 'admin_notices', 'attach_accounts_to_orders_notice' );


function attach_accounts_to_orders() {

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ){
		set_time_limit( 0 );
	}

	$step     = isset( $_GET['step'] )         ? absint( $_GET['step'] )         : 1;
	$offset   = $step == 1                     ? 0                               : $step * 10;
	$fixed    = isset( $_GET['fixed'] )        ? absint( $_GET['fixed'] )        : 0;
	$create   = isset( $_GET['create_users'] ) ? absint( $_GET['create_users'] ) : 0;
	$created  = isset( $_GET['created'] )      ? absint( $_GET['created'] )      : 0;

    $posts = new WP_Query(
    			array(
    				'post_type' => 'edd_payment',
					'posts_per_page' => '10',
					'post_status' => 'publish',
					'meta_query' => array(
				        'relation' => 'OR',
				        array(
				            'key' => '_edd_payment_user_id',
				            'value' => '-1',
				            'compare' => '='
				        ),
				        array(
				            'key' => '_edd_payment_user_id',
				            'value' => '0',
				            'compare' => '='
				        ),
				        array(
				            'key' => '_edd_payment_user_id',
				            'value' => null,
				            'compare' => '='
				        )
			   		),
			    	'offset' => $offset,
 					'fields' => 'ids'
 				)
 			);

    $posts = $posts->posts;

	if( $posts && count( $posts ) > 0 ) {
		foreach( $posts as $id ) {
			// do the upgrade routine
			$meta = get_post_meta($id);
            
            // get the value of the user email
            $email = isset( $meta['_edd_payment_user_email'][0] ) ? $meta['_edd_payment_user_email'][0] : false;
            if ( $email && aato_validate_email_address( $email ) ) {
            	if ( get_user_by('email', $email) ){
            		// there is a user account for this person already
            		aato_attach_existing_user( $id, $email );
            		$fixed++;
            	}
            	else{
            		// there is not a user account for this person already, and we can create users, let's attach them
            		if ( $create_users ){
            			aato_attach_new_user( $id, $email );
            			$created++;
            			$fixed++;
            		}
            	}

            }
		}

		// Keys found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'         => 'aato-attach',
			'edd_upgrade'  => 'attach_accounts_to_orders',
			'step'         => $step,
			'fixed'        => $fixed,
			'create_users' => $create,
			'created'      => $created,
		), admin_url( 'index.php' ) );
		wp_redirect( $redirect ); exit;

	} else {
		// No more orders found, say we're done
		add_action( 'admin_notices', 'aato_were_done_folks' );
	}

}
add_action( 'edd_attach_accounts_to_orders', 'attach_accounts_to_orders' );

function aato_attach_existing_user( $id, $email ){
    // user exists with that email
    $user = get_user_by('email', $email);
    
    // add correct id to order
    update_post_meta($id, '_edd_payment_user_id', $user->ID);
    $metaunser = unserialize($meta['_edd_payment_meta'][0]);
    $metaunser['user_id'] = $user->ID;

     //set uid in order to id
    $metasecondid = unserialize($metaunser['user_info']);
    $metasecondid['id'] = $user->ID;

     //set uid in order to id
    $metasecondid = serialize($metasecondid);
    $metaunser['user_info'] = $metasecondid;
    update_post_meta($id, '_edd_payment_meta', $metaunser);
}

function aato_attach_new_user( $id, $email ){
    // First we need a unique username
    $username = aato_generate_username($email);
    
    // Second we need a unique password
    $random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);
    
    // Then create user:
    $user = wp_create_user($username, $random_password, $email);
    
    // And then notify the user of their new account
    $emailtouser = wp_new_user_notification($username, $random_password);
    
    // Now insert the correct data
    $user = get_user_by('email', $email);
    update_post_meta($id, '_edd_payment_user_id', "$user->ID");
    $metaunser = unserialize($meta['_edd_payment_meta'][0]);
    $metaunser['user_id'] = $user->ID;

     //set uid in order to id
    $metasecondid = unserialize($metaunser['user_info']);
    $metasecondid['id'] = $user->ID;

     //set uid in order to id
    $metasecondid = serialize($metasecondid);
    $metaunser['user_info'] = $metasecondid;
    update_post_meta($id, '_edd_payment_meta', $metaunser);
}

function aato_were_done_folks() {
	echo '<div class="updated"><p>' . __( 'All done attaching accounts to orders! You should deactive this plugin now', 'edd_ead' ) . '</p></div>';
}

function aato_validate_email_address( $email ) {
    
    //Perform a basic syntax-Check
    //If this check fails, there's no need to continue
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    //extract host
    list($user, $host) = explode("@", $email);
    
    //check, if host is accessible
    if (!checkdnsrr($host, "MX") && !checkdnsrr($host, "A")) {
        return false;
    }
    return true;
}

function aato_generate_username( $email ) {
    
    // Lets remove everything after the @
    // Example: chriscct7@some_email.com becomes chriscct7
    $username = array_shift(explode('@', $email));
    if (!username_exists($username)) {
        // if right off the bat we have a good username, return it
        return $username;
    }
    $counter = 1;
    
    // We need a copy of the username so that the username doesn't become:
    // username123456789101112.....
    $copy = $username;
    do {
        $username = $copy;
        $username = $username . $counter;
        $counter++;
    } while (username_exists($username));
     // While a user exists with this username, run the loop again
    return $username;
}