<?php
/*
 +---------------------------------------------------------------------+
 | NinjaFirewall (WP Edition)                                          |
 |                                                                     |
 | (c) NinTechNet - https://nintechnet.com/                            |
 +---------------------------------------------------------------------+
 | This program is free software: you can redistribute it and/or       |
 | modify it under the terms of the GNU General Public License as      |
 | published by the Free Software Foundation, either version 3 of      |
 | the License, or (at your option) any later version.                 |
 |                                                                     |
 | This program is distributed in the hope that it will be useful,     |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of      |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the       |
 | GNU General Public License for more details.                        |
 +---------------------------------------------------------------------+ i18n+ / sa / s1:h0
*/

if (! defined( 'NFW_ENGINE_VERSION' ) ) { die( 'Forbidden' ); }

// ---------------------------------------------------------------------
// Check for HTTPS. This function is also available in firewall.php
// and is used here only if the firewall is not loaded.

if (! function_exists( 'nfw_is_https' ) ) {

	function nfw_is_https() {
		// Can be defined in the .htninja:
		if ( defined('NFW_IS_HTTPS') ) { return; }

		if ( ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 ) ||
			( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
			( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ) {
			define('NFW_IS_HTTPS', true);
		} else {
			define('NFW_IS_HTTPS', false);
		}
	}
	nfw_is_https();
}
// ---------------------------------------------------------------------
// Start a PHP session.

function nfw_session_start() {

	if (! headers_sent() ) {

		if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
			if (! session_id() ) {
				nfw_ini_set_cookie();
				session_start();
			}
		} else {
			if ( session_status() !== PHP_SESSION_ACTIVE ) {
				nfw_ini_set_cookie();
				session_start();
			}
		}
	}
}

// ---------------------------------------------------------------------

function nfw_ini_set_cookie() {

	if ( defined('NFW_IS_HTTPS') && NFW_IS_HTTPS == true ) {
		@ini_set('session.cookie_secure', 1);
	}

	@ini_set('session.cookie_httponly', 1);
	@ini_set('session.use_only_cookies', 1);
}

// ---------------------------------------------------------------------
// Check whether the user is whitelisted (.htninja etc).

function nfw_is_whitelisted() {

	if ( defined('NFW_UWL') && NFW_UWL == true ) {
		return true;
	}
}

// ---------------------------------------------------------------------
// Write session to disk to prevent cURL time-out which may occur with
// WordPress (since 4.9.2, see https://core.trac.wordpress.org/ticket/43358),
// or plugins such as "Health Check".

add_filter( 'pre_http_request', 'nf_pre_http_request', 10, 3 );

function nf_pre_http_request( $preempt, $r, $url ) {

	// NFW_DISABLE_SWC can be defined in wp-config.php (undocumented):
	if (! defined('NFW_DISABLE_SWC') && isset( $_SESSION ) ) {
		if ( function_exists( 'get_site_url' ) ) {
			$s_url = get_site_url();
			if ( strpos( $url, $s_url ) === 0 ) {
				@session_write_close();
			}
		}
	}
	return false;
}

// ---------------------------------------------------------------------
// Return backtrace verbosity.

function nfw_verbosity( $nfw_options ) {

	if (! isset( $nfw_options['a_61'] ) || $nfw_options['a_61'] == 1 ) {
		// Medium verbosity:
		return 0;

	} elseif ( $nfw_options['a_61'] == -1 ) {
		// Disabled:
		return false;

	} elseif ( $nfw_options['a_61'] == 2 ) {
		// High verbosity:
		return  1;
	}

	// Low verbosity:
	return 2;
}

// ---------------------------------------------------------------------
// Allow/disallow account creation.

function nfw_account_creation( $user_login ) {

	$nfw_options = nfw_get_option( 'nfw_options' );

	if ( current_user_can('create_users') || empty( $nfw_options['disallow_creation'] ) ||
		empty( $nfw_options['enabled'] ) ) {

		// Do nothing:
		return $user_login;
	}

	$subject = __('Blocked user account creation', 'ninjafirewall');
	nfw_log2( "WordPress: {$subject}", "Username: {$user_login}", 3, 0);

	nfw_get_blogtimezone();

	// Alert the admin:
	if ( is_multisite() && $nfw_options['alert_sa_only'] == 2 ) {
		$recipient = get_option('admin_email');
	} else {
		$recipient = $nfw_options['alert_email'];
	}
	$subject = '[NinjaFirewall] ' . $subject;
	$message = __('NinjaFirewall has blocked an attempt to create a user account:', 'ninjafirewall') . "\n\n";
	// Show current blog, not main site (multisite):
	$message.= __('Blog:', 'ninjafirewall') .' '. home_url('/') . "\n";
	$message.= __('Username:', 'ninjafirewall') ." {$user_login} (blocked)\n";
	$message.= __('User IP:', 'ninjafirewall') .' '. NFW_REMOTE_ADDR . "\n";
	$message.= 'SCRIPT_FILENAME: ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
	$message.= 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n";
	$message.= __('Date:', 'ninjafirewall') .' '. date_i18n('F j, Y @ H:i:s') . ' (UTC '. date('O') . ")\n\n";

	// Attach PHP backtrace:
	$verbosity = nfw_verbosity( $nfw_options );
	if ( $verbosity !== false ) {
		$nftmpfname = NFW_LOG_DIR .'/nfwlog/backtrace_'. uniqid() .'.txt';
		$dbg = debug_backtrace( $verbosity );
		array_shift( $dbg );
		file_put_contents( $nftmpfname, print_r( $dbg, true ) );
		$message.= __('A PHP backtrace has been attached to this message for your convenience.', 'ninjafirewall') . "\n\n";
	}

	$message.= 	'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
				'Support forum: http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

	$message .= sprintf(
			__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
			'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

	if ( empty( $nftmpfname ) ) {
		wp_mail( $recipient, $subject, $message );

	} else {
		// Attach backtrace and delete temp file:
		wp_mail( $recipient, $subject, $message, '', $nftmpfname );
		unlink( $nftmpfname );
	}

	// Block it:
	$_SESSION = array();
	@session_destroy();
	wp_die(
		'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
		'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
		$nfw_options['ret_code']
	);

}

add_filter( 'pre_user_login' , 'nfw_account_creation' );

// ---------------------------------------------------------------------
// Clean/delete cache folder & temp files (hourly cron job).

function nfw_garbage_collector() {

	$path = NFW_LOG_DIR . '/nfwlog/cache/';
	$now = time();
	// Make sure the cache folder exists, i.e, we have been
	// through the whole installation process:
	if (! is_dir( $path ) ) {
		return;
	}

	// Don't do anything if the cache folder was cleaned up less than 10 minutes ago:
	$gc = $path . 'garbage_collector.php';
	if ( file_exists( $gc ) ) {
		$nfw_mtime = filemtime( $gc ) ;
		if ( $now - $nfw_mtime < 10*60 ) {
			return;
		}
		unlink( $gc );
	}
	touch( $gc );

	// Fetch options:
	$nfw_options = nfw_get_option( 'nfw_options' );

	// ------------------------------------------------------------------
	// If nfw_options is corrupted (e.g., failed update etc) we try to restore it
	// from a backup file otherwise we restore it from the default settings.
	if ( nfw_validate_option( $nfw_options ) === false ) {

		$glob = glob( $path .'backup_*.php' );
		$valid_option = 0;
		// Make sure we have a backup file
		while ( is_array( $glob ) && ! empty( $glob[0] ) ) {
			$content = array();
			$last_file = array_pop( $glob );
			$content = @explode("\n:-:\n", file_get_contents( $last_file ) . "\n:-:\n");
			$content[0] = json_decode( $content[0], true );

			if ( nfw_validate_option( $content[0] ) === true ) {
				// We can use that backup to restore our options:
				$valid_option = 1;
				break;

			// Delete this corrupted backup file:
			} else {
				nfw_log_error( sprintf( 'Backup file is corrupted, deleting it (%s)', $last_file ) );
				unlink( $last_file );
			}
		}

		// Restore the last good backup:
		if (! empty( $valid_option ) ) {
			nfw_update_option( 'nfw_options', $content[0] );
			nfw_log_error( sprintf( '"nfw_options" is corrupted, restoring from last known good backup file (%s)', $last_file ) );

		// Restore the default settings if no backup file was found
		// (this action will also restore the firewall rules):
		} else {
			require_once __DIR__ .'/install_default.php';
			nfw_log_error( '"nfw_options" is corrupted, restoring default values (no valid backup found)' );
			nfw_load_default_conf();
		}

		$nfw_options = nfw_get_option( 'nfw_options' );
	}

	// ------------------------------------------------------------------

	// Check if we must delete old firewall logs:
	if (! empty( $nfw_options['auto_del_log'] ) ) {
		$auto_del_log = (int) $nfw_options['auto_del_log'] * 86400;
		// Retrieve the list of all logs:
		$glob = glob( NFW_LOG_DIR . '/nfwlog/firewall_*.php' );
		if ( is_array( $glob ) ) {
			foreach( $glob as $file ) {
				$lines = array();
				$lines = file( $file, FILE_SKIP_EMPTY_LINES );
				foreach( $lines as $k => $line ) {
					if ( preg_match( '/^\[(\d{10})\]/', $line, $match ) ) {
						if ( $now - $auto_del_log > $match[1] ) {
							// This line is too old, remove it:
							unset( $lines[$k] );
						}
					} else {
						// Not a proper firewall log line:
						unset( $lines[$k] );
					}
				}
				if ( empty( $lines ) ) {
					// No lines left, delete the file:
					unlink( $file );
				} else {
					// Save the last preserved lines to the log:
					$fh = fopen( $file,'w' );
					fwrite( $fh, "<?php exit; ?>\n" );
					foreach( $lines as $line ) {
						fwrite( $fh, $line );
					}
					fclose( $fh );
				}
			}
		}
	}

	// File Guard temp files:
	$glob = glob( $path . "fg_*.php" );
	if ( is_array( $glob ) ) {
		foreach( $glob as $file ) {
			$nfw_ctime = filectime( $file );
			// Delete it, if it is too old :
			if ( $now - $nfw_options['fg_mtime'] * 3660 > $nfw_ctime ) {
				unlink( $file );
			}
		}
	}

	// Live Log:
	$nfw_livelogrun = $path . 'livelogrun.php';
	if ( file_exists( $nfw_livelogrun ) ) {
		$nfw_mtime = filemtime( $nfw_livelogrun );
		// If the file was not accessed for more than 100s, we assume
		// the admin has stopped using live log from WordPress
		// dashboard (refresh rate is max 45 seconds):
		if ( $now - $nfw_mtime > 100 ) {
			unlink( $nfw_livelogrun );
		}
	}
	// If the log was not modified for the past 10mn, we delete it as well:
	$nfw_livelog = $path . 'livelog.php';
	if ( file_exists( $nfw_livelog ) ) {
		$nfw_mtime = filemtime( $nfw_livelog ) ;
		if ( $now - $nfw_mtime > 600 ) {
			unlink( $nfw_livelog );
		}
	}

	// ------------------------------------------------------------------

	// NinjaFirewall's configuration backup. We create a new one daily:
	$glob = glob( $path .'backup_*.php' );
	if ( is_array( $glob ) && ! empty( $glob[0] ) ) {
		rsort( $glob );
		// Check if last backup if older than one day:
		if ( preg_match( '`/backup_(\d{10})_.+\.php$`', $glob[0], $match ) ) {
			if ( $now - $match[1] > 86400 ) {
				// Backup the configuration:
				$nfw_rules = nfw_get_option( 'nfw_rules' );
				if ( file_exists( $path .'bf_conf.php' ) ) {
					$bd_data = json_encode( file_get_contents( $path .'bf_conf.php' ) );
				} else {
					$bd_data = '';
				}
				$data = json_encode( $nfw_options ) ."\n:-:\n". json_encode($nfw_rules) ."\n:-:\n". $bd_data;
				$file = uniqid( 'backup_'. time() .'_', true) . '.php';
				@file_put_contents( $path . $file, $data, LOCK_EX );
				array_unshift( $glob, $path . $file );
			}
		}
		// Keep the last 5 backup only (value can be defined
		// in the wp-config.php):
		if ( defined('NFW_MAX_BACKUP') ) {
			$num = (int) NFW_MAX_BACKUP;
		} else {
			$num = 5;
		}
		$old_backup = array_slice( $glob, $num );
		foreach( $old_backup as $file ) {
			unlink( $file );
		}
	} else {
		// Create first backup:
		$nfw_rules = nfw_get_option( 'nfw_rules' );
		if ( empty( $nfw_rules ) ) {
			return;
		}
		if ( file_exists( $path .'bf_conf.php' ) ) {
			$bd_data = json_encode( file_get_contents( $path .'bf_conf.php' ) );
		} else {
			$bd_data = '';
		}
		$data = json_encode( $nfw_options ) ."\n:-:\n". json_encode($nfw_rules) ."\n:-:\n". $bd_data;
		$file = uniqid( 'backup_'. time() .'_', true) . '.php';
		@file_put_contents( $path . $file, $data, LOCK_EX );
	}

}

// ---------------------------------------------------------------------
// Write potential errors to a specific log.

function nfw_log_error( $message ) {

	$log = NFW_LOG_DIR . '/nfwlog/error_log.php';

	if (! file_exists( $log ) ) {
		@file_put_contents( $log, "<?php exit; ?>\n", LOCK_EX );
	}
	@file_put_contents( $log, date_i18n('[d/M/y:H:i:s O]') . " $message\n", FILE_APPEND | LOCK_EX );

}

// ---------------------------------------------------------------------

function nfw_get_blogtimezone() {

	$tzstring = get_option( 'timezone_string' );
	if (! $tzstring ) {
		$tzstring = ini_get( 'date.timezone' );
		if (! $tzstring ) {
			$tzstring = 'UTC';
		}
	}
	date_default_timezone_set( $tzstring );
}

// ---------------------------------------------------------------------

function nfw_select_ip() {
	// Ensure we have a proper and single IP (a user may use the .htninja file
	// to redirect HTTP_X_FORWARDED_FOR, which may contain more than one IP,
	// to REMOTE_ADDR):
	if (strpos($_SERVER['REMOTE_ADDR'], ',') !== false) {
		$nfw_match = array_map('trim', @explode(',', $_SERVER['REMOTE_ADDR']));
		foreach($nfw_match as $nfw_m) {
			if ( filter_var($nfw_m, FILTER_VALIDATE_IP) )  {
				define( 'NFW_REMOTE_ADDR', $nfw_m);
				break;
			}
		}
	}
	if (! defined('NFW_REMOTE_ADDR') ) {
		define('NFW_REMOTE_ADDR', htmlspecialchars($_SERVER['REMOTE_ADDR']) );
	}
}

// ---------------------------------------------------------------------

function nfw_admin_notice(){

	if (nf_not_allowed( 0, __LINE__ ) ) { return; }

	if (! defined('NF_DISABLED') ) {
		is_nfw_enabled();
	}

	if (! file_exists(NFW_LOG_DIR . '/nfwlog') ) {
		@mkdir( NFW_LOG_DIR . '/nfwlog', 0755);
		@touch( NFW_LOG_DIR . '/nfwlog/index.html' );
		@file_put_contents(NFW_LOG_DIR . '/nfwlog/.htaccess', "Order Deny,Allow\nDeny from all", LOCK_EX);
		if (! file_exists(NFW_LOG_DIR . '/nfwlog/cache') ) {
			@mkdir( NFW_LOG_DIR . '/nfwlog/cache', 0755);
			@touch( NFW_LOG_DIR . '/nfwlog/cache/index.html' );
			@file_put_contents(NFW_LOG_DIR . '/nfwlog/cache/.htaccess', "Order Deny,Allow\nDeny from all", LOCK_EX);
		}
	}
	if (! file_exists(NFW_LOG_DIR . '/nfwlog') ) {
		echo '<div class="error notice is-dismissible"><p><strong>' . __('NinjaFirewall error', 'ninjafirewall') . ' :</strong> ' .
			sprintf( __('%s directory cannot be created. Please review your installation and ensure that %s is writable.', 'ninjafirewall'), '<code>'. htmlspecialchars(NFW_LOG_DIR) .'/nfwlog/</code>',  '<code>/wp-content/</code>') . '</p></div>';
	}
	if (! is_writable(NFW_LOG_DIR . '/nfwlog') ) {
		echo '<div class="error notice is-dismissible"><p><strong>' . __('NinjaFirewall error', 'ninjafirewall') . ' :</strong> ' .
			sprintf( __('%s directory is read-only. Please review your installation and ensure that %s is writable.', 'ninjafirewall'), '<code>'. htmlspecialchars(NFW_LOG_DIR) .'/nfwlog/</code>', '<code>/nfwlog/</code>') . '</p></div>';
	}

	if (! NF_DISABLED) {
		return;
	}

	if (isset($_GET['page']) && preg_match('/^(?:NinjaFirewall|nfsubopt)$/', $_GET['page']) ) {
		return;
	}

	$nfw_options = nfw_get_option('nfw_options');
	if ( empty($nfw_options['ret_code']) && NF_DISABLED != 11 ) {
		return;
	}

	if (! empty($GLOBALS['err_fw'][NF_DISABLED]) ) {
		$msg = $GLOBALS['err_fw'][NF_DISABLED];
	} else {
		$msg = __('unknown error', 'ninjafirewall') . ' #' . NF_DISABLED;
	}
	echo '<div class="error notice is-dismissible"><p><strong>' . __('NinjaFirewall fatal error:', 'ninjafirewall') . '</strong> ' . $msg .
		'. ' . __('Review your installation, your site is not protected.', 'ninjafirewall') . '</p></div>';
}

add_action('all_admin_notices', 'nfw_admin_notice');

// ---------------------------------------------------------------------

function nfw_send_loginemail( $user_login, $whoami ) {

	$nfw_options = nfw_get_option( 'nfw_options' );

	if ( ( is_multisite() ) && ( $nfw_options['alert_sa_only'] == 2 ) ) {
		$recipient = get_option('admin_email');
	} else {
		$recipient = $nfw_options['alert_email'];
	}

	$subject = '[NinjaFirewall] ' . __('Alert: WordPress console login', 'ninjafirewall');
	// Show current blog, not main site (multisite):
	$url = __('-Blog:', 'ninjafirewall') .' '. home_url('/') . "\n\n";
	if (! empty( $whoami ) ) {
		$whoami = " ($whoami)";
	}
	$message = __('Someone just logged in to your WordPress admin console:', 'ninjafirewall') . "\n\n".
				__('-User:', 'ninjafirewall') .' '. $user_login . $whoami . "\n" .
				__('-IP:', 'ninjafirewall') .' '. NFW_REMOTE_ADDR . "\n" .
				__('-Date:', 'ninjafirewall') .' '. ucfirst(date_i18n('F j, Y @ H:i:s')) . ' (UTC '. date('O') . ")\n" .
				$url .
				'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
				__('Support forum', 'ninjafirewall') . ': http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

	$message .= sprintf(
				__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
				'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

	wp_mail( $recipient, $subject, $message );

}

// ---------------------------------------------------------------------			s1:h0

function nfw_query( $query ) {

	if ( isset($_SESSION['nfw_goodguy']) || nfw_is_whitelisted() ) {
		return;
	}

	$nfw_options = nfw_get_option( 'nfw_options' );
	// Return if not enabled, or if we are accessing the dashboard (e.g., /wp-admin/edit.php):
	if ( empty($nfw_options['enum_archives']) || empty($nfw_options['enabled']) || is_admin() ) {
		return;
	}
	if ( $query->is_main_query() && $query->is_author() ) {
		if ( $query->get('author_name') ) {
			$tmp = 'author_name=' . $query->get('author_name');
		} elseif ( $query->get('author') ) {
			$tmp = 'author=' . $query->get('author');
		} else {
			$tmp = 'author';
		}
		$_SESSION = array();
		@session_destroy();
		$query->set('author_name', '0');
		nfw_log2('User enumeration scan (author archives)', $tmp, 2, 0);
		wp_safe_redirect( home_url('/') );
		exit;
	}
}

add_action('pre_get_posts','nfw_query');

// ---------------------------------------------------------------------			s1:h0

// WP >= 4.7:
function nfwhook_rest_authentication_errors( $res ) {

	// Whitelisted user?
	if ( nfw_is_whitelisted() || isset($_SESSION['nfw_goodguy']) ) {
		return $res;
	}

	if (! defined('NF_DISABLED') ) {
		is_nfw_enabled();
	}
	if ( NF_DISABLED ) { return $res; }

	$nfw_options = nfw_get_option( 'nfw_options' );

	if (! empty( $nfw_options['no_restapi']) ) {
		nfw_log2( 'WordPress: Blocked access to the WP REST API', $_SERVER['REQUEST_URI'], 2, 0);
		return new WP_Error( 'nfw_rest_api_access_restricted', __('Forbidden access', 'ninjafirewall'), array('status' => $nfw_options['ret_code']) );
	}

	return $res;
}
add_filter( 'rest_authentication_errors', 'nfwhook_rest_authentication_errors' );

// ---------------------------------------------------------------------			s1:h0

function nfwhook_rest_request_before_callbacks( $res, $hnd, $req ) {

	// Whitelisted user?
	if ( nfw_is_whitelisted() || isset($_SESSION['nfw_goodguy']) ) {
		return $res;
	}

	if (! defined('NF_DISABLED') ) {
		is_nfw_enabled();
	}
	if ( NF_DISABLED ) { return $res; }

	$nfw_options = nfw_get_option( 'nfw_options' );

	if (! empty( $nfw_options['enum_restapi']) ) {

		if ( strpos( $req->get_route(), '/wp/v2/users' ) !== false && ! current_user_can('list_users') ) {
			nfw_log2('User enumeration scan (WP REST API)', $_SERVER['REQUEST_URI'], 2, 0);
			return new WP_Error('nfw_rest_api_access_restricted', __('Forbidden access', 'ninjafirewall'), array('status' => $nfw_options['ret_code']) );
		}
	}
	return $res;
}
add_filter('rest_request_before_callbacks', 'nfwhook_rest_request_before_callbacks', 999, 3);

// ---------------------------------------------------------------------

function nfw_authenticate( $user ) {

	$nfw_options = nfw_get_option( 'nfw_options' );

	if ( empty( $nfw_options['enum_login']) || empty($nfw_options['enabled']) ) {
		return $user;
	}

	if ( is_wp_error( $user ) ) {
		if ( preg_match( '/^(?:in(?:correct_password|valid_username)|authentication_failed)$/', $user->get_error_code() ) ) {
			$user = new WP_Error( 'denied', sprintf( __( '<strong>ERROR</strong>: Invalid username or password.<br /><a href="%s">Lost your password</a>?', 'ninjafirewall' ), wp_lostpassword_url() ) );
			add_filter('shake_error_codes', 'nfw_err_shake');
		}
	}
	return $user;
}

add_filter( 'authenticate', 'nfw_authenticate', 90, 3 );

function nfw_err_shake( $shake_codes ) {
	$shake_codes[] = 'denied';
	return $shake_codes;
}

// ---------------------------------------------------------------------

function nfw_check_emailalert() {

	$nfw_options = nfw_get_option( 'nfw_options' );

	if ( ( is_multisite() ) && ( $nfw_options['alert_sa_only'] == 2 ) ) {
		$recipient = get_option('admin_email');
	} else {
		$recipient = $nfw_options['alert_email'];
	}

	global $current_user;
	$current_user = wp_get_current_user();

	list( $a_1, $a_2, $a_3 ) = explode( ':', NFW_ALERT . ':' );

	if (! empty($nfw_options['a_' . $a_1 . $a_2]) ) {
		$alert_array = array(
			'1' => array (
				'0' => __('Plugin', 'ninjafirewall'), '1' => __('uploaded', 'ninjafirewall'),	'2' => __('installed', 'ninjafirewall'), '3' => __('activated', 'ninjafirewall'),
				'4' => __('updated', 'ninjafirewall'), '5' => __('deactivated', 'ninjafirewall'), '6' => __('deleted', 'ninjafirewall'), 'label' => __('Name', 'ninjafirewall')
			),
			'2' => array (
				'0' => __('Theme', 'ninjafirewall'), '1' => __('uploaded', 'ninjafirewall'), '2' => __('installed', 'ninjafirewall'), '3' => __('activated', 'ninjafirewall'),
				'4' => __('deleted', 'ninjafirewall'), 'label' => __('Name', 'ninjafirewall')
			),
			'3' => array (
				'0' => 'WordPress', '1' => __('upgraded', 'ninjafirewall'),	'label' => __('Version', 'ninjafirewall')
			)
		);

		if ( substr_count($a_3, ',') ) {
			$alert_array[$a_1][0] .= 's';
			$alert_array[$a_1]['label'] .= 's';
		}
		$subject = __('[NinjaFirewall] Alert:', 'ninjafirewall') . ' ' . $alert_array[$a_1][0] . ' ' . $alert_array[$a_1][$a_2];
		if ( is_multisite() ) {
			$url = __('-Blog :', 'ninjafirewall') .' '. network_home_url('/') . "\n\n";
		} else {
			$url = __('-Blog :', 'ninjafirewall') .' '. home_url('/') . "\n\n";
		}
		$message = __('NinjaFirewall has detected the following activity on your account:', 'ninjafirewall') . "\n\n".
			'-' . $alert_array[$a_1][0] . ' ' . $alert_array[$a_1][$a_2] . "\n" .
			'-' . $alert_array[$a_1]['label'] . ' : ' . $a_3 . "\n\n" .
			__('-User :', 'ninjafirewall') .' '. $current_user->user_login . ' (' . $current_user->roles[0] . ")\n" .
			__('-IP   :', 'ninjafirewall') .' '. NFW_REMOTE_ADDR . "\n" .
			__('-Date :', 'ninjafirewall') .' '. ucfirst( date_i18n('F j, Y @ H:i:s O') ) ."\n" .
			$url .
			'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
			__('Support forum:', 'ninjafirewall') . ' http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

		$message .= sprintf(
			__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
			'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

		wp_mail( $recipient, $subject, $message );

		if (! empty($nfw_options['a_41']) ) {
			nfw_log2(
				$alert_array[$a_1][0] . ' ' . $alert_array[$a_1][$a_2] . ' by '. $current_user->user_login,
				$alert_array[$a_1]['label'] . ': ' . $a_3,
				6,
				0
			);
		}

	}
}

// ---------------------------------------------------------------------

function nf_check_dbdata() {

	$nfw_options = nfw_get_option( 'nfw_options' );

	// Don't do anything if NinjaFirewall is disabled or DB monitoring option is off :
	if ( empty( $nfw_options['enabled'] ) || empty($nfw_options['a_51']) ) { return; }

	// Don't run more than once every minute:
	if ( get_transient( 'nfw_db_check' ) !== false ) {
		return;
	}

	if ( is_multisite() ) {
		global $current_blog;
		$db_hash = NFW_LOG_DIR .'/nfwlog/cache/db_hash.'. $current_blog->site_id .'-'. $current_blog->blog_id .'.php';
	} else {
		global $blog_id;
		$db_hash = NFW_LOG_DIR .'/nfwlog/cache/db_hash.'. $blog_id .'.php';
	}

	$adm_users = nf_get_dbdata();
	if (! $adm_users) {
		set_transient( 'nfw_db_check', 1, 60 );
		return;
	}

	// Sort by ID to prevent false alerts:
	usort( $adm_users, 'nfw_sort_by_id' );

	if (! file_exists( $db_hash ) ) {
		// We don't have any hash yet, let's create one and quit
		// (md5 is faster than sha1 or crc32 with long strings) :
		@file_put_contents( $db_hash, md5( serialize( $adm_users) ), LOCK_EX );
		set_transient( 'nfw_db_check', 1, 60 );
		return;
	}

	$old_hash = trim ( file_get_contents( $db_hash ) );
	if (! $old_hash ) {
		@file_put_contents( $db_hash, md5( serialize( $adm_users) ), LOCK_EX );
		set_transient( 'nfw_db_check', 1, 60 );
		return;
	}

	// Compare both hashes:
	if ( $old_hash == md5( serialize( $adm_users ) ) ) {
		set_transient( 'nfw_db_check', 1, 60 );
		return;

	} else {
		// Create or update 60-second transient:
		set_transient( 'nfw_db_check', 1, 60 );

		// Save the new hash:
		$tmp = @file_put_contents( $db_hash, md5( serialize( $adm_users ) ), LOCK_EX );
		if ( $tmp === FALSE ) {
			return;
		}

		nfw_get_blogtimezone();
		// Send an email to the admin:
		if ( ( is_multisite() ) && ( $nfw_options['alert_sa_only'] == 2 ) ) {
			$recipient = get_option('admin_email');
		} else {
			$recipient = $nfw_options['alert_email'];
		}

		$subject = __('[NinjaFirewall] Alert: Database changes detected', 'ninjafirewall');
		$message = __('NinjaFirewall has detected that one or more administrator accounts were modified in the database:', 'ninjafirewall') . "\n\n";
		// Even if this is a multisite install, we display
		// the requested blog, not the main site:
		$message .=__('Blog:', 'nfwplus') .' '. home_url('/') . "\n";
		$message.= __('Date:', 'ninjafirewall') .' '. date_i18n('F j, Y @ H:i:s') . ' (UTC '. date('O') . ")\n\n";
		$message.= sprintf(__('Total administrators : %s', 'ninjafirewall'), count($adm_users) ) . "\n\n";
		foreach( $adm_users as $adm ) {
			$message.= 'Admin ID: ' . $adm->ID . "\n";
			$message.= '-user_login: ' . $adm->user_login . "\n";
			$message.= '-user_nicename: ' . $adm->user_nicename . "\n";
			$message.= '-user_email: ' . $adm->user_email . "\n";
			$message.= '-user_registered: ' . $adm->user_registered . "\n";
			$message.= '-display_name: ' . $adm->display_name . "\n\n";
		}
		$message.=  __('If you cannot see any modifications in the above fields, it is possible that the administrator password was changed.', 'ninjafirewall'). "\n\n";
		$message.= __('This notification can be turned off from NinjaFirewall "Event Notifications" page.', 'ninjafirewall') . "\n\n";
		$message.= 	'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
						'Support forum: http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

		$message .= sprintf(
					__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
					'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

		wp_mail( $recipient, $subject, $message );

		// Log event if required:
		if (! empty($nfw_options['a_41']) ) {
			nfw_log2('Database changes detected', 'administrator account', 4, 0);
		}
	}
}

// ---------------------------------------------------------------------

function nf_get_dbdata() {

	return get_users(
		array( 'role' => 'administrator',
			'fields' => array(
				'ID', 'user_login', 'user_pass', 'user_nicename',
				'user_email', 'user_registered', 'display_name'
			)
		)
	);
}

// ---------------------------------------------------------------------

function nfw_sort_by_id( $a, $b ) {

  return strcmp( $a->ID, $b->ID );
}

// ---------------------------------------------------------------------

function nfw_get_option( $option ) {

	if ( is_multisite() ) {
		return get_site_option( $option );
	} else {
		return get_option( $option );
	}
}

// ---------------------------------------------------------------------

function nfw_update_option( $option, $new_value ) {

	if ( is_multisite() ) {
		update_site_option( $option, $new_value );
	}
	return update_option( $option, $new_value );
}

// ---------------------------------------------------------------------

function nfw_delete_option( $option ) {

	if ( is_multisite() ) {
		delete_site_option( $option );
	}
	return delete_option( $option );
}

// ---------------------------------------------------------------------
// Make sure nfw_options is valid.

function nfw_validate_option( $value ) {

	if (! isset( $value['enabled'] ) || ! isset( $value['blocked_msg'] ) ||
		! isset( $value['logo'] ) || ! isset( $value['ret_code'] ) ||
		! isset( $value['scan_protocol'] ) || ! isset( $value['get_scan'] ) ) {

		// Data is corrupted:
		return false;
	}

	return true;
}

// ---------------------------------------------------------------------

function nfwhook_update_user_meta( $user_id, $meta_key, $meta_value, $prev_value ) {

	nfwhook_user_meta( $meta_key, $meta_value, $prev_value );

}
add_filter('update_user_meta', 'nfwhook_update_user_meta', 1, 4);

// ---------------------------------------------------------------------

function nfwhook_add_user_meta( $user_id, $meta_key, $meta_value ) {

	nfwhook_user_meta( $user_id, $meta_key, $meta_value );

}
add_filter('add_user_meta', 'nfwhook_add_user_meta', 1, 3);

// ---------------------------------------------------------------------

function nfwhook_user_meta( $id, $key, $value ) {

	if (! defined('NF_DISABLED') ) {
		is_nfw_enabled();
	}
	// Note: "NFW_DISABLE_PRVESC2" is the only way to disable this feature.
	if ( NF_DISABLED || defined('NFW_DISABLE_PRVESC2') ) { return; }

	global $wpdb;

	if ( is_array( $key ) ) {
		$key = serialize( $key );
	}
	// The original prefix as defined in wp-config.php
	if ( strpos( $key, "{$wpdb->base_prefix}capabilities") !== FALSE && ! current_user_can('edit_users') ) {
		if ( is_array( $value ) ) {
			$value = serialize( $value );
		}
		if ( strpos( $value, 's:13:"administrator"' ) === FALSE ) { return; }
		$subject = __('Blocked privilege escalation attempt', 'ninjafirewall');

		$user_info = get_userdata( $id );
		if (! empty( $user_info->user_login ) ) {
			nfw_log2( 'WordPress: ' . $subject, "Username: {$user_info->user_login}, ID: $id", 3, 0);
		} else {
			nfw_log2( 'WordPress: ' . $subject, "$key: $value", 3, 0);
		}

		$nfw_options = nfw_get_option( 'nfw_options' );

		// Alert the admin if needed:
		if (! empty( $nfw_options['a_53'] ) ) {

			nfw_get_blogtimezone();

			if ( is_multisite() && $nfw_options['alert_sa_only'] == 2 ) {
				$recipient = get_option('admin_email');
			} else {
				$recipient = $nfw_options['alert_email'];
			}
			$subject = '[NinjaFirewall] ' . $subject;
			$message = __('NinjaFirewall has blocked an attempt to gain administrative privileges:', 'ninjafirewall') . "\n\n";
			// Show current blog, not main site (multisite):
			$message.= __('Blog:', 'ninjafirewall') .' '. home_url('/') . "\n";
			$message.= __('Username:', 'ninjafirewall') .' '. $user_info->user_login . " (ID: $id)\n";
			$message.= __('User IP:', 'ninjafirewall') .' '. NFW_REMOTE_ADDR . "\n";
			$message.= 'SCRIPT_FILENAME: ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
			$message.= 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n";
			$message.= __('Date:', 'ninjafirewall') .' '. date_i18n('F j, Y @ H:i:s') . ' (UTC '. date('O') . ")\n\n";

			// Attach PHP backtrace:
			$verbosity = nfw_verbosity( $nfw_options );
			if ( $verbosity !== false ) {
				$nftmpfname = NFW_LOG_DIR .'/nfwlog/backtrace_'. uniqid() .'.txt';
				$dbg = debug_backtrace( $verbosity );
				array_shift( $dbg );
				file_put_contents( $nftmpfname, print_r( $dbg, true ) );
				$message.= __('A PHP backtrace has been attached to this message for your convenience.', 'ninjafirewall') . "\n\n";
			}

			$message.= __('This notification can be turned off from NinjaFirewall "Event Notifications" page.', 'ninjafirewall') . "\n\n";
			$message.= 	'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
						'Support forum: http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

			$message .= sprintf(
						__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
						'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

			if ( empty( $nftmpfname ) ) {
				wp_mail( $recipient, $subject, $message );

			} else {
				// Attach backtrace and delete temp file:
				wp_mail( $recipient, $subject, $message, '', $nftmpfname );
				unlink( $nftmpfname );
			}
		}

		// Block it:
		$_SESSION = array();
		@session_destroy();
		wp_die(
			'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
			'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
			$nfw_options['ret_code']
		);
	}
}
// ---------------------------------------------------------------------			s1:h0

function nfw_login_form_hook() {

	if (! empty( $_SESSION['nfw_bfd'] ) ) {
		echo '<p class="message" id="nfw_login_msg">'. __('NinjaFirewall brute-force protection is enabled and you are temporarily whitelisted.', 'ninjafirewall' ) . '</p><br />';
	}
}
add_filter( 'login_message', 'nfw_login_form_hook');

// ---------------------------------------------------------------------

function nfw_rate_notice( $nfw_options ) {

	// Display a one-time notice after two weeks of use:
	$now = time();
	if (! empty( $nfw_options['rate_notice'] ) && $nfw_options['rate_notice'] < $now ) {

		echo '<div class="notice-info notice is-dismissible"><p>'.	sprintf(
			__('Hey, it seems that you\'ve been using NinjaFirewall for some time. If you like it, please take <a href="%s">the time to rate it</a>. It took thousand of hours to develop it, but it takes only a couple of minutes to rate it. Thank you!', 'ninjafirewall'),
			'https://wordpress.org/support/view/plugin-reviews/ninjafirewall?rate=5#postform'
			) .'</p></div>';

		// Clear the reminder flag:
		unset( $nfw_options['rate_notice'] );
		// Update options:
		nfw_update_option( 'nfw_options', $nfw_options );
	}

}

// ---------------------------------------------------------------------			s1:h1

function nfw_session_debug() {

	// Make sure NinjaFirewall is running :
	if (! defined('NF_DISABLED') ) {
		is_nfw_enabled();
	}
	if ( NF_DISABLED ) { return; }

	$show_session_icon = 0;
	$current_user = wp_get_current_user();
	// Check users first:
	if ( defined( 'NFW_SESSION_DEBUG_USER' ) ) {
		$users = explode( ',', NFW_SESSION_DEBUG_USER );
		foreach ( $users as $user ) {
			if ( trim( $user ) == $current_user->user_login ) {
				$show_session_icon = 1;
				break;
			}
		}
	// Check capabilities:
	} elseif ( defined( 'NFW_SESSION_DEBUG_CAPS' ) ) {
		$caps = explode( ',', NFW_SESSION_DEBUG_CAPS );
		foreach ( $caps as $cap ) {
			if (! empty( $current_user->caps[ trim( $cap ) ] ) ) {
				$show_session_icon = 1;
				break;
			}
		}
	}

	if ( empty( $show_session_icon ) ) { return; }

	// Check if the user whitelisted?
	if ( empty( $_SESSION['nfw_goodguy'] ) ) {
		// No:
		$font = 'ff0000';
	} else {
		// Yes:
		$font = '00ff00';
	}

	global $wp_admin_bar;
	$wp_admin_bar->add_menu( array(
		'id'    => 'nfw_session_dbg',
		'title' => "<font color='#{$font}'>NF</font>",
	) );

}

// Check if the session debug option is enabled:
if ( defined( 'NFW_SESSION_DEBUG_USER' ) || defined( 'NFW_SESSION_DEBUG_CAPS' ) ) {
	add_action( 'admin_bar_menu', 'nfw_session_debug', 500 );
}

// ---------------------------------------------------------------------

function nf_monitor_options( $value, $option, $old_value ) {

	// Admin check is done in nfw_load_optmon().

	// Similarly to https://core.trac.wordpress.org/ticket/38903, an integer will
	// trigger a DB UPDATE query even if it matches the character stored in the DB
	// (e.g.: 0 vs '0'). We must not block that, hence will use '===' only on arrays
	// and objects (and that will prevent "Nesting level too deep" error as well):
	if ( is_array( $value ) || is_object( $value ) ) {
		if ( $value === $old_value ) {
			return $value;
		}
	} else {
		// Simple comparison operator for integers and strings:
		if ( $value == $old_value ) {
			return $value;
		}
	}

	$nfw_options = nfw_get_option( 'nfw_options' );

	if ( empty( $nfw_options['enabled'] ) || empty( $nfw_options['disallow_settings'] ) ) {
		return $value;
	}

	// User-defined exclusion list (undocumented), NF options/rules (which are protected
	// by the firewall):
	if ( ( defined('NFW_OPTMON_EXCLUDE') && strpos( NFW_OPTMON_EXCLUDE, $option ) !== false ) ||
		$option === 'nfw_options' || $option === 'nfw_rules' ) {

		return $value;
	}

	global $wpdb;
	$monitor = array(
		'admin_email',
		'blog_public',
		'blogdescription',
		'blogname',
		'comment_moderation',
		'comment_registration',
		'default_role',
		'home',
		'mailserver_login',
		'siteurl',
		'template',
		'stylesheet',
		'users_can_register'
	);
	$monitor2 = array (
		"{$wpdb->base_prefix}user_roles"
	);
	if ( is_multisite() ) {
		// E.g.: wp_2_user_roles
		global $current_blog;
		$monitor2[] = "{$wpdb->prefix}{$current_blog->blog_id}_user_roles";
	}

	// Not what we are looking for? Scan it anyway:
	if (! in_array( $option, $monitor ) && ! in_array( $option, $monitor2 ) ) {

		return $value;

		// Options can be an array or object:
		if ( is_array( $value ) || is_object( $value ) ) {
			$tmp = serialize( $value );
		} else {
			$tmp = $value;
		}

		$regex_list = array(
			'(?i)<script.*?>.+?</script',
			'(?i)<meta.+?\bhttp-equiv\s*=\s*[\'"]refresh[\'"]'
		);
		foreach( $regex_list as $regex ) {
			if ( preg_match( "`({$regex})`", $tmp, $match ) ) {
				break;
			}
		}

		if ( empty( $match[1] ) ) {
			// Nothing weird found, let it go:
			return $value;
		}

		$value = '';
		if ( strlen( $match[1] ) > 200 ) { $match[1] = mb_substr( $match[1], 0, 200, 'utf-8' ) . '...'; }
		$value = $match[1];

		// Send a notification to the admin:
		nf_monitor_options_alert( $option, $value, null, 'injection' );

		// Log the request:
		nfw_log2('Blocked attempt to inject code in WordPress options table', "option: {$option}, value: {$value}", 3, 0);

	// We are monitoring those settings:
	} else {

		if ( in_array( $option, $monitor2 ) ) {
			$res = nfw_check_roles( $value );
			if ( $res === true ) {
				return $value;
			}
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$tmp = serialize( $value );
			$value = '';
			if ( strlen( $tmp ) > 200 ) { $tmp = mb_substr( $tmp, 0, 200, 'utf-8' ) . '...'; }
			$value = $tmp;
		}
		if ( is_array( $old_value ) || is_object( $old_value ) ) {
			$tmp = serialize( $old_value );
			$old_value = '';
			if ( strlen( $tmp ) > 200 ) { $tmp = mb_substr( $tmp, 0, 200, 'utf-8' ) . '...'; }
			$old_value = $tmp;
		}

		// Send a notification to the admin:
		nf_monitor_options_alert( $option, $value, $old_value, 'settings' );

		// Log the request:
		nfw_log2('Blocked attempt to modify WordPress settings', "option: {$option}, value: {$value}", 3, 0);
	}

	// Block it:
	$nfw_options = nfw_get_option( 'nfw_options' );
	$_SESSION = array();
	@session_destroy();
	wp_die(
		'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
		'NinjaFirewall: '. __('You are not allowed to perform this task.', 'ninjafirewall'),
		$nfw_options['ret_code']
	);
}

// ---------------------------------------------------------------------

function nfw_load_optmon() {

	if (! current_user_can('manage_options') && ! nfw_is_whitelisted() ) {
		add_filter( 'pre_update_option', 'nf_monitor_options', 10, 3 );
	}
}

add_action( 'plugins_loaded', 'nfw_load_optmon' );

// ---------------------------------------------------------------------

function nfw_check_roles( $user_roles ) {

	$admin_only_cap = array(
		'activate_plugins', 'create_users', 'delete_plugins', 'delete_themes',
		'delete_users', 'edit_files', 'edit_plugins', 'edit_theme_options',
		'edit_themes', 'edit_users', 'export', 'import', 'install_plugins',
		'install_themes', 'list_users', 'manage_options', 'promote_users',
		'remove_users', 'switch_themes', 'update_core', 'update_plugins',
		'update_themes', 'edit_dashboard', 'customize',	'delete_site',
		// WooCommerce shop_manager:
		'manage_woocommerce', 'view_woocommerce_reports',
		// bbPress bbp_keymaster:
		'publish_forums', 'edit_forums', 'delete_forums', 'keep_gate'
	);
	// Default WP user, WooCommerce and bbPress
	$check_users = array(
		'subscriber', 'contributor', 'customer', 'bbp_participant', 'bbp_spectator'
	);

	foreach ( $user_roles as $user => $cap ) {
		if ( in_array( $user, $check_users ) ) {
			foreach( $cap['capabilities'] as $k => $v ) {
				if (! empty( $v ) && in_array( $k, $admin_only_cap ) ) {
					// Stop here and send an alert:
					return false;
				}
			}
		}
	}
	// OK
	return true;
}

// ---------------------------------------------------------------------
// $type = settings or injection.

function nf_monitor_options_alert( $option, $value, $old_value = null, $type ) {

	$nfw_options = nfw_get_option( 'nfw_options' );

	nfw_get_blogtimezone();

	if ( is_multisite() && $nfw_options['alert_sa_only'] == 2 ) {
		$recipient = get_option('admin_email');
	} else {
		$recipient = $nfw_options['alert_email'];
	}

	$action = __('The attempt was blocked and the option was reversed to its original value.', 'ninjafirewall');

	// WP settings:
	if ( $type == 'settings' ) {

		$subject = '[NinjaFirewall] ' . __('Attempt to modify WordPress settings', 'ninjafirewall');
		$message = __('NinjaFirewall has blocked an attempt to modify some important WordPress settings by a user that does not have administrative privileges:', 'ninjafirewall') . "\n\n";
		$message.= sprintf( __('Option: %s', 'ninjafirewall') ."\n", $option );
		$message.= sprintf( __('Original value: %s', 'ninjafirewall') ."\n", $old_value );
		$message.= sprintf( __('Modified value: %s', 'ninjafirewall') ."\n", $value );
		$message.= sprintf( __('Action taken: %s', 'ninjafirewall') ."\n\n", $action );

	// Misc. injection:
	} else {
		$subject = '[NinjaFirewall] ' . __('Code injection attempt in WordPress options table', 'ninjafirewall');
		$message = __('NinjaFirewall has blocked an attempt to inject code in the WordPress options table by a user that does not have administrative privileges:', 'ninjafirewall') . "\n\n";
		$message.= sprintf( __('Option: %s', 'ninjafirewall') ."\n", $option );
		$message.= sprintf( __('Code: %s', 'ninjafirewall') ."\n", $value );
		$message.= sprintf( __('Action taken: %s', 'ninjafirewall') ."\n\n", $action );
	}

	// Attach PHP backtrace:
	$verbosity = nfw_verbosity( $nfw_options );
	if ( $verbosity !== false ) {
		$nftmpfname = NFW_LOG_DIR .'/nfwlog/backtrace_'. uniqid() .'.txt';
		$dbg = debug_backtrace( $verbosity );
		array_shift( $dbg );
		file_put_contents( $nftmpfname, print_r( $dbg, true ) );
		$message.= __('A PHP backtrace has been attached to this message for your convenience.', 'ninjafirewall') . "\n\n";
	}

	// Show current blog, not main site (multisite):
	$message.= __('Blog:', 'ninjafirewall') .' '. home_url('/') . "\n";
	$message.= __('User IP:', 'ninjafirewall') .' '. NFW_REMOTE_ADDR . "\n";
	$message.= 'SCRIPT_FILENAME: ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
	$message.= 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n";
	$message.= __('Date:', 'ninjafirewall') .' '. date_i18n('F j, Y @ H:i:s') . ' (UTC '. date('O') . ")\n\n";

	$message.= __('This protection (and notification) can be turned off from NinjaFirewall "Firewall Policies" page.', 'ninjafirewall') . "\n\n";
	$message.= 	'NinjaFirewall (WP Edition) - https://nintechnet.com/' . "\n" .
					'Support forum: http://wordpress.org/support/plugin/ninjafirewall' . "\n\n";

	$message .= sprintf(
				__('Need more security? Check out our supercharged NinjaFirewall (WP+ Edition): %s', 'ninjafirewall'),
				'https://nintechnet.com/ninjafirewall/wp-edition/?comparison' );

	if ( empty( $nftmpfname ) ) {
		wp_mail( $recipient, $subject, $message );

	} else {
		// Attach backtrace and delete temp file:
		wp_mail( $recipient, $subject, $message, '', $nftmpfname );
		unlink( $nftmpfname );
	}

}

// ---------------------------------------------------------------------
// EOF
