<?php

	/*

		Plugin Name: Disable Post Passwords
		Version: 1.0
		
		Author: Tom Lynch
		Author URI: http://tomlynch.co.uk
		
		Description: Disables post passwords for selected user groups.
		
		License: GPLv3
		
		Copyright (C) 2012 Tom Lynch

	    This program is free software: you can redistribute it and/or modify
	    it under the terms of the GNU General Public License as published by
	    the Free Software Foundation, either version 3 of the License, or
	    (at your option) any later version.
	
	    This program is distributed in the hope that it will be useful,
	    but WITHOUT ANY WARRANTY; without even the implied warranty of
	    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	    GNU General Public License for more details.
	
	    You should have received a copy of the GNU General Public License
	    along with this program.  If not, see <http://www.gnu.org/licenses/>.
		
	*/
	
	class DisablePostPasswords {
		var $admin_panel_hook;
	
		// Register actions and filters on class instanation
		function __construct() {
			// Automatically fill out the password for each post
			add_action( 'the_post', array( &$this, 'autofill_password' ) );
			
			// If site is a multisite installation
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			
				// Add an action link to the network plugins page
				add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'filter_plugin_action_links' ), 10, 2 );
			
				// Add a admin menu item to the network settings page
				add_action( 'network_admin_menu', array( &$this, 'register_network_admin_menu' ) );
			
			// If site is a standalone installation
			} else {
			
				// Add an ation link to the blog plugins page
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'filter_plugin_action_links' ), 10, 2 );
			
				// Add a admin menu item to the blog settings page
				add_action( 'admin_menu', array( &$this, 'register_admin_menu' ) );
			}
			
			// Add a contextual help menu to the admin page
			add_filter( 'contextual_help', array( &$this, 'register_contextual_help' ), 10, 2 );
		}
		
		function autofill_password() {
		
			// Get options
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$roles = get_site_option( 'disable-post-passwords-roles', $wp_roles->role_names, false );
				$logged_out_users = get_site_option( 'disable-post-passwords-loggedout', false, false );
			} else {
				$roles = get_option( 'disable-post-passwords-roles', $wp_roles->role_names );
				$logged_out_users = get_option( 'disable-post-passwords-loggedout', false );
			}
			
			// Check user is logged in if required
			if ( is_user_logged_in() || $logged_out_users ) {

				// Check user role is valid
				if ( $logged_out_users || $this->current_user_is( $roles ) ) {
				
					// Get post data
					$post = get_post( get_the_ID() );
					
					// If password is defined 
					if ( ! empty( $post->post_password ) )
					
						// Hash password and temporarily trick WordPress to think it is a cookie
						$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_hash_password( $post->post_password );
				}
			}
		}
		
		// Checks to see if any of defined roles apply to the current user
		function current_user_is( $roles ) {
			
			// Check $roles is an array
			if ( is_array( $roles ) ) {

				// Itterate over each role
				foreach ( $roles as $role ) {

					// Check the user can do this thing
					if ( current_user_can( $role ) )
						return true;
					
					// Check for special role
					if ( is_user_logged_in() && $role == 'loggedin' )
						return true;
				}
			}
				
			// Otherwise return false
			return false;
		}
		
		// Add a action link under on plugin page
		function filter_plugin_action_links( $links ) {
		
			// If the site is a multisite installation
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			
				// Tac a link to the settings page onto the start of the plugin's listing
				array_unshift( $links, '<a href="' . network_admin_url( 'settings.php' ) . '?page=disable-post-passwords">Settings</a>' );
				
			// If site is a standalone installation
			} else {
			
				// Tac a link to the settings page onto the start of the plugin's listing
				array_unshift( $links, '<a href="' . admin_url( 'options-general.php' ) . '?page=disable-post-passwords">Settings</a>' );
			
			}
			
			// Return the $links to further filtering and use
			return $links;
		}
		
		// Register plugin menu item
		function register_network_admin_menu() {
			$this->admin_panel_hook = add_submenu_page( 'settings.php', 'Disable Post Passwords', 'Post Passwords', 'manage_network_options', 'disable-post-passwords', array( &$this, 'register_options_page' ) );
		}
		
		function register_admin_menu() {
			$this->admin_panel_hook = add_options_page('Disable Post Passwords', 'Post Passwords', 'manage_options', 'disable-post-passwords', array( &$this, 'register_options_page' ) );
		}
		
		// Output plugin admin page
		function register_options_page() {
			global $wp_roles;

			// If site is multisite installation
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {

				// Check to see if there is data to store
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'disable-post-passwords' ) && current_user_can( 'manage_network_options' ) ) {

					// Roles
				
						// Compile a list of validated roles from submission
						if ( isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) )
							foreach ( $_POST['roles'] as $role => $value )
								if ( isset( $wp_roles->role_names[$role] ) || $role == 'loggedin' && $value == 'on' )
									$new_roles[] = $role;
						
						// Store option
						update_site_option( 'disable-post-passwords-roles', $new_roles );

					// Logged Out
					
						// Validate option
						$new_logged_out_users = $_POST['logged_out_users'] == 'on' ? true : false;
						
						// Store option
						update_site_option( 'disable-post-passwords-loggedout', $new_logged_out_users );
					
					// Mark as updated
					$done = true;
				}
				
				// Update settings
				$roles = get_site_option( 'disable-post-passwords-roles', $wp_roles->role_names, false );
				$logged_out_users = get_site_option( 'disable-post-passwords-loggedout', false, false );

			// If site is not multisite installation
			} else {

				// Check to see if there is data to store
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'disable-post-passwords' ) && current_user_can( 'manage_options' ) ) {

					// Roles
				
						// Compile a list of validated roles from submission
						if ( isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) )
							foreach ( $_POST['roles'] as $role => $value )
								if ( isset( $wp_roles->role_names[$role] ) && $value == 'on' )
									$new_roles[] = $role;
						
						// Store option
						update_option( 'disable-post-passwords-roles', $new_roles );


					// Logged Out
					
						// Validate option
						$new_logged_out_users = $_POST['logged_out_users'] == 'on' ? true : false;
						
						// Store option
						update_option( 'disable-post-passwords-loggedout', $new_logged_out_users );
					
					// Mark as updated
					$done = true;
				}
				
				// Update settings
				$roles = get_option( 'disable-post-passwords-roles', $wp_roles->role_names );
				$logged_out_users = get_option( 'disable-post-passwords-loggedout', false );
			}
			
			// Output page
			?>
				<div class="wrap">
					<div id="icon-options-general" class="icon32"></div>
					<h2>Disable Post Passwords</h2>
					<?php if ( ( ( function_exists( 'is_multisite' ) && is_multisite() ) && current_user_can( 'manage_network_options' ) ) || current_user_can( 'manage_options' ) ): ?>
						<?php if ( isset( $done ) ): ?>
							<div id="message" class="updated"><p>Options saved.</p></div>
						<?php endif ?>
						<?php if ( function_exists( 'is_multisite' ) && is_multisite() ): ?>
							<form method="post" action="settings.php?page=disable-post-passwords">
						<?php else: ?>
							<form method="post" action="options-general.php?page=disable-post-passwords">
						<?php endif ?>
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce( 'disable-post-passwords' ) ?>">
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row">Disable for roles</th>
										<td>
											<?php foreach ( $wp_roles->role_names as $role => $description ): ?>
												<label for="roles-<?php echo $role ?>"><input id="roles-<?php echo $role ?>" name="roles[<?php echo $role ?>]" type="checkbox" <?php echo is_array( $roles ) && in_array( $role, $roles ) ? 'checked="checked"' : null ?>/> <?php echo $description ?>s</label><br />
											<?php endforeach ?>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row">Disable for login status</th>
										<td>
											<label for="roles-loggedin"><input id="roles-loggedin" name="roles[loggedin]" type="checkbox" <?php echo is_array( $roles ) && in_array( 'loggedin', $roles ) ? 'checked="checked"' : null ?>/> All logged in users</label><br />
											<label for="logged_out_users"><input id="logged_out_users" name="logged_out_users" type="checkbox" <?php echo $logged_out_users ? 'checked="checked"' : null ?>/> All logged out users</label>
										</td>
									</tr>
								</tbody>
							</table>
							<p class="submit">
								<input type="submit" class="button-primary" value="Save Changes">
							</p>
						</form>
					<?php else: ?>
						<p>You do not have permission to use Disable Post Passwords.</p>
					<?php endif ?>
				</div>
			<?php
		}

		// Output contextual help for WordPress help drop down
		function register_contextual_help( $help, $screen ) {
			$new_help = '<p>This screen lets you specify which users have post passwords disabled.</p>
				<p>
					<strong>For more information:</strong>
				</p>
				<p>
					<a href="http://wordpress.org/extend/plugins/disable-post-passwords" target="_blank">Disable Post Passwords Homepage</a>
				</p>
				<p>
					<a href="http://wordpress.org/tags/disable-post-passwords" target="_blank">Disable Post Passwords Forum</a>
				</p>';
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				if ( $screen == $this->admin_panel_hook . '-network' )
					return $new_help;
			} else {
				if ( $screen == $this->admin_panel_hook )
					return $new_help;
			}
			return $help;
		}
	}
	
	// Instantiate DisablePostPasswords class
	$DisablePostPasswords = new DisablePostPasswords();
	
?>