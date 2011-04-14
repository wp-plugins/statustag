<?php
/**
 * @package StatusTag
 * @version 0.9.1
 */
/*
Plugin Name: StatusTag
Plugin URI: http://wordpress.org/extend/plugins/statustag/
Description: Put your tagline to better use! Display your latest post from Twitter!
Author: Tim S Christie
Version: 0.9.1
Author URI: http://timschristie.com/
License: GPL2
*/

/**
 * Front-end code
 *
 * Display your latest tweet, instead of the default tagline
 */
add_filter('option_blogdescription','replace_description');
wp_enqueue_style( 'st_styles', plugins_url('/st_styles.css', __FILE__) );

function replace_description($description) {
	$description = get_latest_tweet();
	return $description;
}

// Work-around for getting links to be displayed as links
add_filter('bloginfo', 'blog_filter', 10, 2);
function blog_filter($string, $show) {
	/**
	 * NOTE: If your description is displayed in the title, these things will make it look ugly (i.e. HTML markup in title)
	 */
	if($show == "description" && get_option('st_links')) {
		// Handle regular links <-- must come first or it messes up the links created below
		$string = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $string);
		// Handle @username
		$string = preg_replace('/(^|\s)@(\w+)/','\1@<a href="http://www.twitter.com/\2">\2</a>',$string);
		// Handle #hashtags
		$string = preg_replace('/(^|\s)#(\w+)/','\1#<a href="http://search.twitter.com/search?q=%23\2">\2</a>',$string);
		// Append custom twitter image to start of description
		get_option('st_icon') ? $string = '<img class="st_image" src="'.get_option('st_icon').'">'.$string : '';
	}
	
	return $string;
}

/**
 * Back-end code
 *
 * First we set up the admin screens, then handle the twitter functionality
 */
add_action('admin_menu', 'statustag_admin');

function statustag_admin() {
	add_plugins_page('StatusTag Options', 'StatusTag', 'manage_options', 'statustag-options', 'statustag_options');
}

function statustag_options() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	// Variables for the field and option names
	$var_profile = 'st_profile';
	$var_interval = 'st_interval';
	$var_links = 'st_links';
	$var_icon = 'st_icon';
	$var_hidden = 'st_hidden';
	$data_profile = 'st_profile';
	$data_interval = 'st_interval';
	$data_links = 'st_links';
	$data_icon = 'st_icon';

	// Read in existing options from the database
	$val_profile = get_option($var_profile);
	$val_interval = get_option($var_interval);
	$val_links = get_option($var_links);
	$val_icon = get_option($var_icon);

	// See if the user has posted some information
	if(isset($_POST[$var_hidden]) && $_POST[$var_hidden] == 'Y') {
		// Read their posted value
		$val_profile = $_POST[$data_profile];
		$val_interval = $_POST[$data_interval];
		$val_links = $_POST[$data_links] ? $_POST[$data_links] : '0';
		$val_icon = $_POST[$data_icon];
		// Save their posted value in the database
		update_option($var_profile, $val_profile);
		update_option($var_interval, $val_interval);
		update_option($var_links, $val_links);
		update_option($var_icon, $val_icon);

		// Put a settings updated message on the screen
?>
<div class="updated"><p><strong><?php _e('Your settings have been saved.'); ?></strong></p></div>
<?php
	}

	// Now display the settings editing screen
	echo '<div class="wrap">';
	// Header
	echo '<div class="icon32" id="icon-statustag" style="background:url('.WP_PLUGIN_URL.'/statustag/statustag_icon.png);"><br></div>';
	echo '<h2>' . __('StatusTag Options') . '</h2>';
	// Settings form
?>
<form name="st_form" method="post" action="">
<input type="hidden" name="<?php echo $var_hidden; ?>" value="Y">
<p><?php _e("Profile Name: (e.g. <span style='color:#ababab;'>http://twitter.com/</span><strong>wordpress</strong>)"); ?><br>
<input type="text" name="<?php echo $data_profile; ?>" value="<?php echo $val_profile; ?>" size="20">
</p>
<p><?php _e("Cache Refresh Interval:"); ?><br>
<input type="text" name="<?php echo $data_interval; ?>" value="<?php echo $val_interval; ?>" size="20"> hour(s)
</p>
<p><?php _e("Enable links:"); ?> <input type="checkbox" name="<?php echo $data_links; ?>" value="1" <?php echo $val_links ? 'checked="checked"' : ''; ?>><br>
<i>(Note: This can cause undesired effects if your theme uses the site description in the page title.)</i><br>
</p>
<p><?php _e("Custom Twitter Icon:"); ?><br>
<input type="text" name="<?php echo $data_icon; ?>" value="<?php echo $val_icon; ?>" size="20">
</p><hr />
<p class="submit">
<input type="submit" name="st_submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>
</form>
<?php
}

/**
 * Get the latest tweet either from the cache or online
 */
function get_latest_tweet() {
	$cache_timer = get_option('st_cache_timer');
	if(!$cache_timer || $cache_timer < strtotime("now")) {
		$twitter_account = get_option('st_profile');
		$tweet = json_decode(file_get_contents("http://api.twitter.com/1/statuses/user_timeline/{$twitter_account}.json"));
		update_option('st_cache', $tweet);
		update_option('st_cache_timer', strtotime("now + ".get_option('st_interval')." hours"));
	}
	else {
		$tweet = get_option('st_cache');
	}
	
	return $tweet[0]->text;
}

?>
