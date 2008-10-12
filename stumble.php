<?php

/*
Plugin Name: Stumble! For WordPress
Plugin URI: http://making-the-web.com/stumble-for-wordpress/
Description: Adds "random article" functionality to Wordpress, similar to StumbleUpon and Wikipedia's random article feature
Author: Brendon Boshell
Version: 0.1.1
Author URI: http://making-the-web.com
*/

$_stumble_version          = "0.1.1";
$GLOBALS['_stumble_table'] = $GLOBALS['wpdb']->prefix . "stumble"; // Wordpress wouldn't put these in the global space for me when activating :(
$_stumble_hasOptions       = false;
$_stumble_favour           = array('comments' => array(400, 50, 50));
$_stumble_max              = 1;
$_stumble_support          = true;
$_stumble_auto_insert      = true;

function _stumble_options_panel() {
	global $_stumble_favour, $_stumble_support, $_stumble_auto_insert;
	
	if($_POST['_stumble_slashes'] == '\\\'') { // slashes problem
		$_POST['_stumble_favour_comments']      = stripslashes($_POST['_stumble_favour_comments']);
	}
	
	if(isset($_POST['Submit'])) {
	
		check_admin_referer('stumble-update-options');
		
		if($_POST['_stumble_favour_comments_relative'] <= 0)
			$_POST['_stumble_favour_comments_relative'] = 1;
		
		$data = serialize(array('comments' => array($_POST['_stumble_favour_comments']/100, $_POST['_stumble_favour_comments_relative'], $_POST['_stumble_favour_comments_limit'])));
		
		if($data != get_option('_stumble_favour')) {
		
			update_option('_stumble_favour', $data);
		
			// we need to update the table
			_stumble_update_table();
			
		}
		
		update_option('_stumble_auto_insert', $_POST['_stumble_auto_insert']);
		update_option('_stumble_support',     $_POST['_stumble_support']);
		
	}
	
	_stumble_get_options();

	?>
	
	<div class="wrap">
		
		<h2><?php _e("Stumble! for WordPress", 'stumble'); ?></h2>
		
		<p><?php _e("Disclaimer: This plugin is not associated with <a href=\"http://www.stumbleupon.com/\">StumbleUpon</a>.", 'stumble'); ?></p>
		
		<?php if(isset($_POST['Submit'])) { ?>
			<p><div id="message" class="updated fade"><p><strong><?php _e("Your settings have been updated.", 'stumble'); ?></strong></p></div></p>
		<?php } ?>
			
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__) ?>">
			<?php wp_nonce_field('stumble-update-options'); ?>
			
			<input type="hidden" name="_stumble_slashes" value="'" />
			
			<h3><?php _e('Favouring', 'stumble'); ?></h3>
			
			<p><?php _e("The options below specify how a factor will 'weigh' on an article's probability of being stumbled. For example, if 'favour by comments' is set to 100% relative to 50, an article with 50 comments is 100% more likely to be stumbled than an article with none. Set these values to 0 for <strong>truely random</strong> articles.", 'stumble'); ?></p>
			
			<p><?php _e("Changing these values will require all blog posts to be re-indexed.", 'stumble'); ?></p>
			
			<table cellspacing="7" cellpadding="5" border="0">
					
				<tr>
					<td><?php _e('Favour by Comments:', 'stumble'); ?></td>
					
					<td>
						<input type="text" name="_stumble_favour_comments" size="5" value="<?php echo $_stumble_favour['comments'][0]*100; ?>" />% (<small><?php _e('Any percentage', 'stumble'); ?></small>)
					</td>
				</tr>
				
				<tr>
					<td><?php _e('Relative to:', 'stumble'); ?></td>
					
					<td>
						<input type="text" name="_stumble_favour_comments_relative" size="5" value="<?php echo $_stumble_favour['comments'][1]; ?>" /> (<small><?php _e('With how many comments should an article be 100% more likely to be stumbled than an article with none? Probability will be calculated by the number of comments divided by the "relative to" value. <a href="http://en.wikipedia.org/wiki/Division_by_zero">Can not be 0</a>.', 'stumble'); ?></small>)
					</td>
				</tr>
				
				<tr>
					<td><?php _e('Limit:', 'stumble'); ?></td>
					
					<td>
						<input type="text" name="_stumble_favour_comments_limit" size="5" value="<?php echo $_stumble_favour['comments'][2]; ?>" /> (<small><?php _e('When the number of comments reaches this value, favouring no longer becomes a factor.', 'stumble'); ?></small>)
					</td>
				</tr>
				
				<tr>
					<td align="right" valign="top"><input type="checkbox" name="_stumble_auto_insert" id="_stumble_auto_insert" value="true" onchange="document.getElementById('_stumble_support').disabled = this.checked==false;"<?php if($_stumble_auto_insert) { ?> checked="checked"<?php } ?> /></td>
					
					<td>
						<div style="padding-bottom: 3px;"><?php _e("Automatically insert the 'Liked this article?' button at the end of all articles.", 'stumble'); ?></div>
						<small><input type="checkbox" name="_stumble_support" id="_stumble_support"<?php if($_stumble_support) { ?> checked="checked"<?php } ?> /> Mention Stumble! for WordPress below this button</small>
						
						<script type="text/javascript">
							document.getElementById('_stumble_support').disabled = document.getElementById('_stumble_auto_insert').checked==false;
						</script>
					</td>
				</tr>
				
			</table>
			
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save Changes'); ?>" />
			</p>
			
			<div style="height: 70px;"></div>
			
			<h3><?php _e("How to use Stumble! for WordPress", 'stumble'); ?></h3>
			
			<p><?php _e("Stumble! for WordPress is easy to integrate with any blog. To go to a random article, you can create a link to the following page:", 'stumble'); ?></p>
			
			<blockquote><?php bloginfo('url'); ?>/?stumble</blockquote>
			
			<p><?php _e("<em>If</em> you have <a href=\"http://mitcho.com/code/yarpp/\">Yet Another Related Posts Plugin</a> installed, you can stumble to a similar article like this:", 'stumble'); ?></p>
			
			<blockquote><?php bloginfo('url'); ?>/?stumble=#</blockquote>
			
			<?php
			if(function_exists('yarpp_sql')) {
			?>
				<div style="margin: 5px; width: 400px; border: 2px solid #006600; padding: 5px;">
					<?php _e("YARPP <strong>is</strong> installed on your blog.", 'stumble'); ?>
				</div>
			<?php 
			}else{
			?>
				<div style="margin: 5px; width: 450px; border: 2px solid #990000; padding: 10px;">
					<?php _e("YARPP <strong>is not</strong> installed/activated on your blog. To use this feature, please download and install <a href=\"http://mitcho.com/code/yarpp/\">YARPP</a>.", 'stumble'); ?>
				</div>
			<?php
			}
			?>
			
			<p><?php _e("Just replace # with the <a href=\"http://codex.wordpress.org/Template_Tags/the_ID\">post's ID</a> and you will be brought to one of five related articles (from YARPP), chosen randomly.", 'stumble'); ?></p>
			
		</form>
		
			<div style="height: 70px;"></div>
			
			<h3><?php _e("Code", 'stumble'); ?></h3>
			
			<p><?php _e("We have a collection of buttons and links which you can include in theme files to use Stumble! for WordPress.", 'stumble'); ?></p>
			
			<table cellspacing="0" cellpadding="0" border="0">
			
			<tr>
			
			<td colspan="2">
				
				<h4><?php _e("Liked this article? button", 'stumble'); ?></h4>
				
			</td>
			
			</tr>
			
			<tr>
			
			<td valign="top">
			
				<p><img src="<?php echo bloginfo('url')."/wp-content/plugins/".dirname(plugin_basename(__FILE__))."/liked.png"; ?>" border="0" width="300" height="92" alt="<?php _e("Liked this article? Read another similar article.", 'stumble'); ?>" /></p>
			
			</td>
			
			<td>
			
				<textarea onkeydown="return false;" cols="50" rows="5">&lt;a href="<?php bloginfo('url'); ?>/?stumble=&lt;?php the_ID(); ?&gt;"&gt;&lt;img src="<?php echo bloginfo('url')."/wp-content/plugins/".dirname(plugin_basename(__FILE__))."/liked.png"; ?>" border="0" width="300" height="92" alt="<?php _e("Liked this article? Read another similar article.", 'stumble'); ?>" /&gt;&lt;/a&gt;</textarea>
			
			</td>
			
			</tr>
			
			<tr>
			
			<td colspan="2">
				
				<p><?php _e("This button is designed for use on a single post page. When clicked, it will bring the user to a similar article. <strong>Requires YARPP</strong>", 'stumble'); ?></p>
				
				<h4><?php _e("Basic Text Link", 'stumble'); ?></h4>
				
			</td>
			
			</tr>
			
			<tr>
			
			<td valign="top">
			
				<p><a href="#"><?php _e("Random Article", 'stumble'); ?></a></p>
			
			</td>
			
			<td>
			
				<textarea onkeydown="return false;" cols="50" rows="5">&lt;a href="<?php bloginfo('url'); ?>/?stumble"&gt;<?php _e("Random Article", 'stumble'); ?>&lt;/a&gt;</textarea>
			
			</td>
			
			</tr>
			
			</table>
		
	</div>
	
	<?php
}

function _stumble_options() {
	if (function_exists('add_options_page')) {
		add_options_page('Stumble! for WordPress', 'Stumble! for WordPress', 8, plugin_basename(__FILE__), '_stumble_options_panel');
	}
}

function _stumble_get_options() {
	global $_stumble_favour, $_stumble_hasOptions, $_stumble_max, $_stumble_support, $_stumble_auto_insert;
	
	$_stumble_hasOptions = true;
	
	$_stumble_favour = get_option('_stumble_favour');
	if(!is_array($_stumble_favour)) $_stumble_favour = unserialize($_stumble_favour);
	
	$_stumble_max              = get_option('_stumble_max');
	$N_stumble_support         = get_option('_stumble_support');
	$_stumble_support          = ($N_stumble_support === false) ? $_stumble_support : $N_stumble_support;
	$N_stumble_auto_insert     = get_option('_stumble_auto_insert');
	$_stumble_auto_insert      = ($N_stumble_auto_insert === false) ? $_stumble_support : $N_stumble_auto_insert;
}

function _stumble_update_table($record = 0) {
	global $wpdb, $_stumble_max, $_stumble_table, $_stumble_favour;
	
	_stumble_get_options();
	
	$favourComments         = $_stumble_favour['comments'][0];
	$favourCommentsRelative = $_stumble_favour['comments'][1];
	$favourCommentsLimit    = $_stumble_favour['comments'][2];
	
	$favourCommentsRelative = ($favourCommentsRelative > 0) ? $favourCommentsRelative : 1; // we don't want to divide by zero
	
	$upscale = 65535/($favourCommentsLimit/$favourCommentsRelative*$favourComments+1);
	
	if($record) {
	
		$origprob = $wpdb->get_var("SELECT prob FROM $_stumble_table WHERE `postid` = $record");
		
		if(!$origprob) {
			$wpdb->query("INSERT INTO $_stumble_table (postid, prob) VALUES ($record, 0)");
			$origprob = 0;
		}
		
		$result = $wpdb->get_row("SELECT ID, comment_count FROM $wpdb->posts WHERE `ID` = $record AND `post_status` = 'publish' AND `post_type` = 'post'");
		
		if(!$result) {
			$wpdb->query("DELETE FROM $_stumble_table WHERE `ID` = $record");
			update_option('_stumble_max', $_stumble_max-$origprob); // update the "divisor" as we have added more/less values
			return;
		}
		
		$comments = $result->comment_count;
		$comments = ($result->comment_count > $favourCommentsLimit) ? $favourCommentsLimit : $comments;
	
		$prob = floor((($comments/$favourCommentsRelative*$favourComments)+1)*$upscale);
		
		$wpdb->query("UPDATE $_stumble_table SET prob = $prob WHERE postid = $record");
		
		update_option('_stumble_max', $_stumble_max+$prob-$origprob); // update the "divisor" as we have added more/less values
	
		return;
	}
	
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	$sql = "TRUNCATE TABLE `$_stumble_table`";
	$wpdb->query($sql);
	
	$batchSize = 300;
	$offset    =   0;
	
	$maxval = 0;
	
	while($results = $wpdb->get_results("SELECT ID, comment_count FROM $wpdb->posts WHERE `post_status` = 'publish' AND `post_type` = 'post' LIMIT $offset, $batchSize")) {
		$offset += $batchSize;
		
		$query = "INSERT INTO $_stumble_table (postid, prob) VALUES ";
		
		$first = true;
		
		foreach($results as $result) {
		
			$comments = $result->comment_count;
			$comments = ($result->comment_count > $favourCommentsLimit) ? $favourCommentsLimit : $comments;
		
			$prob = floor((($comments/$favourCommentsRelative*$favourComments)+1)*$upscale);
		
			$query .= (($first) ? '' : ',')."($result->ID, $prob)";
			$first = false;
			
			$maxval += $prob;
		}
		
		dbDelta($query);
		
		update_option('_stumble_max', $maxval);
		
	}
	
}

function _stumble_delete_post($record) {	
	global $_stumble_table, $wpdb;
	$origprob = $wpdb->get_var("SELECT prob FROM $_stumble_table WHERE `postid` = $record");

	$wpdb->query("DELETE FROM $_stumble_table WHERE `postid` = $record");
	
	update_option('_stumble_max', get_option('_stumble_max')-$origprob); // update the "divisor" as we have added more/less values
}

function _stumble_update($id) {
	_stumble_update_table($id);
}

function _stumble_install() {
	global $wpdb, $_stumble_table;
	
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	if($wpdb->get_var("SHOW TABLES LIKE '".$_stumble_table."'") != $_stumble_table) {
		
		$sql = "DROP TABLE IF EXISTS `".$_stumble_table."`";
	
		$wpdb->query($sql);
		
		$sql = "CREATE TABLE ".$_stumble_table." (
						`postid` INT UNSIGNED NOT NULL,
						`prob` SMALLINT UNSIGNED NOT NULL,
						PRIMARY KEY ( `postid` )
					);";
	
		dbDelta($sql);
		
		_stumble_update_table();
		
	}	
	
	if(get_option('_stumble_auto_insert') === false) {
		update_option('_stumble_auto_insert', 'true');
	}
	
	if(get_option('_stumble_support') === false) {
		update_option('_stumble_support', 'true');
	}
	
	wp_clear_scheduled_hook('_stumble_update_table_event');
	wp_schedule_event(time(), 'hourly', '_stumble_update_table_event');
}

function _stumble_stumble($int = false) {
	global $_stumble_favour, $post, $wpdb, $_stumble_max, $_stumble_table;
	
	_stumble_get_options();
	
	$visited = _stumble_cookie_arr();
	
	if($_GET['stumble'][0] != '/') {
		header('Location: '.get_bloginfo('url').'/?stumble=/'.intval($_GET['stumble']));
		exit;
	}
	
	if(($_GET['stumble'] != '/0') && function_exists('yarpp_sql')) { // if there is a post specified, integrate with YARPP, if it is installed
	
		query_posts('p='.intval(substr($_GET['stumble'], 1)));
	
		the_post();
		
		$ret = _stumble_yarpp();
		
		while($max = count($ret)-1) {
			if($max <= 0) break;
			$i = rand(0, $max);
			
			if(!isset($visited[$ret[$i]->ID])) {		
				_stumble_cookie_append($ret[$i]->ID);
				header('Location: '.get_permalink($ret[$i]->ID));
				exit;
			}
			
			array_splice($ret, $i, 1);
			
		}
	
	}
	
	
	$stats = array();
	
	$sqlcond = '';;
	
	if(is_array($visited)) foreach($visited as $val => $null) {
		$sqlcond .= ' AND postid != '.$val;
	}
	
	$sqlcond2 = '';
	
	if(is_array($visited)) foreach($visited as $val => $null) {
		$sqlcond2 .= ' OR postid = '.$val;
	}
	
	// Otherwise, show ANY random article.
	
	$div = $_stumble_max;
	
	if($sqlcond2) {
		$result = $wpdb->get_var("SELECT SUM(`prob`) FROM `$_stumble_table`".($sqlcond2 ? ' WHERE '.substr($sqlcond2, 4) : '')." LIMIT 0, 1");
		$div -= $result;
	}
	
	if($div <= 0) { // user has already visited everything. clear the cookies and restart
		_stumble_cookie_delete();
		if(!$int) _stumble_stumble(true);
		return;
	}
	
	$result = $wpdb->get_var("SELECT `postid` FROM `$_stumble_table` WHERE RAND() < `prob`/$div$sqlcond LIMIT 0, 1");
	/*
		MySQL's RAND() is not very random at all. Leads me to think something better could be done here (above).
	*/
	
	// Most of the time, the above query will give us a value. Sometimes, it might now, so in this case we just take a pure record
	
	if(!$result) {
		$result = $wpdb->get_var("SELECT `postid` FROM `$_stumble_table`".($sqlcond ? ' WHERE '.substr($sqlcond, 4) : '')." ORDER BY RAND() LIMIT 0, 1");
		if(!$result) {
		
			if($visited) {
				_stumble_cookie_delete();
				die($div.'t');
				return;
				if(!$int) _stumble_stumble(true);
			}
		
			return; // just give up, there's nothing we can return
		}
	}
	
	_stumble_cookie_append($result);
	
	query_posts('p='.$result);
	
	the_post();
	
	header('Location: '.get_permalink());
	
	exit;
	
}

function _stumble_auto($data) {
	global $post;

	$button = '<div align="center" style="padding: 20px 5px;"><a href="'.get_bloginfo('url').'/?stumble='.$post->ID.'"><img src="'.get_bloginfo('url').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__)).'/liked.png" border="0" width="300" height="92" alt="'.__("Liked this article? Read another similar article.", 'stumble').'" /></a>'.(get_option('_stumble_support') ? '<br /><small><a href="http://making-the-web.com/stumble-for-wordpress/">Powered by Stumble! for WordPress</a></small>' : '').'</div>';
	
	return $data.$button;
	
}

function _stumble_cookie_arr() {
	$cookievals = explode('-', $_COOKIE['_stumble_recent']);
	$cookievals = array_chunk(array_map('intval', $cookievals), 50);
	$cookievals = array_flip($cookievals[0]);
	
	return $cookievals;
	
	/*$ret = '';
	
	$first = true;
	
	foreach($cookievals as $val) {
		$ret .= ($first ? '' : ' AND ').'postid != '.$val;
		$first = false;
	}
	
	return $ret;	*/
}

function _stumble_cookie_delete() {	
	setcookie('_stumble_recent', '', time()-1209600);
	unset($_COOKIE['_stumble_recent']);
}

function _stumble_cookie_append($id) {
	// create cookie, expires in 2 weeks
	// holds recently "stumbled" articles.
	$cookievals = explode('-', $_COOKIE['_stumble_recent']);
	$cookievals = array_chunk(array_map('intval', $cookievals), 50);
	$cookievals = $cookievals[0];
	array_splice($cookievals, 0, 0, $id);
	$cookievals = implode('-', $cookievals);
	
	_stumble_cookie_delete();
	
	setcookie('_stumble_recent', $cookievals, time()+1209600, dirname($_SERVER['REQUEST_URI']).'/');
}

/*
 * _stumble_yarpp()
 * 
 */
function _stumble_yarpp() {
	global $wpdb, $post, $user_level;

	// get options
	$options = array('limit','threshold','before_title','after_title','show_excerpt','excerpt_length','before_post','after_post','show_pass_post','past_only','show_score');
	$optvals = array();
	foreach (array_keys($options) as $index) {
		$optvals[$options[$index]] = stripslashes(stripslashes(get_option('yarpp_'.$options[$index])));
	}
	extract($optvals);
	$optvals['type'] = 'post';
	
	// Primary SQL query
	if(get_option('yarpp_version') >= 2.1)
   		$results = $wpdb->get_results(yarpp_sql(array('post'), array(), true));
	else
		$results = $wpdb->get_results(yarpp_sql($optvals));
    
	return $results;
} /* _stumble_yarpp() */

add_action('admin_menu', '_stumble_options', 20);
add_action('activate_'.plugin_basename(__FILE__), '_stumble_install');

add_action('edit_post', '_stumble_update', 1, 1);
add_action('delete_post', '_stumble_delete_post', 1, 1);

add_action('_stumble_update_table_event', '_stumble_update_table');

if(isset($_GET['stumble'])) {
	add_action('init', '_stumble_stumble');
}

if(get_option('_stumble_auto_insert')) {
	add_filter('the_content','_stumble_auto',1);
}

?>