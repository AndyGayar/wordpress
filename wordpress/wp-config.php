<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '/C0z6i+_*:gKGLBS8yZ+y8x(.Z9euB!8e-6`<9>EMNl=C +*o?kaS<<*n<.C3c0:' );
define( 'SECURE_AUTH_KEY',  'dnLP:nC E)<H8m(ncV5@I/5Id TkcN2%_~1>&dxNYh8zSuG_;^N2*>WMX<h ~`?v' );
define( 'LOGGED_IN_KEY',    'QvWF%Xd~RX/OS}S.VdXZZKV&E7@o4e<?b7-~%@GN</<ZcI*J>wKC.{|o;/.REIm^' );
define( 'NONCE_KEY',        'F roJ&ieGR+A`(ox00^46hka+BhCj$Jw=%~*}&$T(J<wZcq&[Nxqu.E[gtr!$pDg' );
define( 'AUTH_SALT',        '|#4oOUaks78m2_aTd?}>7&[7<W[#*jR|Xo15AN9<~.cau.smODB`NzOV{>F:6hw2' );
define( 'SECURE_AUTH_SALT', 'QM>!ZJx)PZkKQGk>nfLnxc&0{x7EYmKjG9Ct_4rLb3Lk5J9{Y938q~NtjW-9wu 2' );
define( 'LOGGED_IN_SALT',   'q}bV;Q%[(R07]}t/wuX-Qvf5uWn8kw%8K=ClVdoRc+[v#1RaV}]&dt4@WKN(qa*h' );
define( 'NONCE_SALT',       '?yC4q^%C^[)e?o:cP5XYZpd[hO]Ja{$oJOJB,V(]97__(xc R<E%EBgs[4<@>wiu' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'for_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
