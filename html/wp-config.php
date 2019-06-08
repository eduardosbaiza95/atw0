<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'root' );

/** MySQL hostname */
define( 'DB_HOST', '35.224.2.4' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );


define('FS_METHOD', 'direct');





/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'V_?}+|<&QCdqhOxN_d:{/`|t-$:a|}DE!N- {!E|c&{. A2kX2`-gbE`i_;=:9dl');
define('SECURE_AUTH_KEY',  'b8_L#2#+AQZ(o)1YMZml0c/gn$PVp58b9~entd=,tWUS_=!%idC6%DeiL54jz0DH');
define('LOGGED_IN_KEY',    ' ltR)c{WR1|d*u5ZQxXY)E]tMh(h[&xa{YS~*y;NoJyPp=-lMJ`NLHGHpy$I!<b~');
define('NONCE_KEY',        ')fU-{lbX6$g[adS]s{#3c<!wvGS6s]Ry52(-?!=Yu bks*`^tP-$~5(w1j;x=-z@');
define('AUTH_SALT',        'A{rx4dV#Tp-jp@ce( *6yA0qpH(P$Y`rPn^X:&5oCV@~/lJ`s84MSNDvz>sa2]^u');
define('SECURE_AUTH_SALT', 'Lht}%.N4WB]|Z=O[4N-X(K3j5>q`4pUg+RN@Q]7e$NYs3ez_Z/.xs|Bw!h*HY$%R');
define('LOGGED_IN_SALT',   'U7~9KHY-Nt(sXu<f/5S!K9nguE/5EguVk7*T^h2fI+rJ3+/kcs:*x$m[U:EGPtG:');
define('NONCE_SALT',       '-XuN$cor;$ZS)/Y63:D}!NgZG>vegV3Fuu{NWuM2rYutys^EV%Bj,0-M^28l16U|');
/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );


