<?php
// ===============================================================//
// NinjaFirewall's loader.                                        //
// DO NOT alter or remove it as long as NinjaFirewall is running! //
// ===============================================================//
if ( file_exists('/var/www/html/wp-content/plugins/ninjafirewall/lib/firewall.php') ) {
	@include('/var/www/html/wp-content/plugins/ninjafirewall/lib/firewall.php');
}
// EOF
