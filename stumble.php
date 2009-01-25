<?php

/*
Plugin Name: Stumble! For WordPress
Plugin URI: http://making-the-web.com/stumble-for-wordpress/
Description: Adds "random article" functionality to Wordpress, similar to StumbleUpon and Wikipedia's random article feature
Author: Brendon Boshell
Version: 1.0.1
Author URI: http://making-the-web.com
*/

/*

	Thanks to an anonymous user, "dhave config", for pointing out that
	empty database resultscan cause foreach to throw errors - is_array()
	has now been added in a variety of places.
	
	Thanks to Pavan (http://www.techpavan.com/) for submitting a fix to
	stop Google from indexing 302 redirect pages, which often replace 
	results in the SERPs. Caused serious navigation issues for users
	coming from the search engines. ~27/11/08~

*/

$_stumble_version          = "1.0.1";
$GLOBALS['_stumble_table'] = $GLOBALS['wpdb']->prefix . "stumble"; // Wordpress wouldn't put these in the global space for me when activating :(
$GLOBALS['_stumble_table_stats'] = $GLOBALS['wpdb']->prefix . "stumble_stats";
$_stumble_hasOptions       = false;
$_stumble_favour           = array('comments' => array(400, 50, 50));
$_stumble_max              = 1;
$_stumble_support          = true;
$_stumble_auto_insert      = true;
$_stumble_altUrls          = array();

// new in version 1.0 - Stumble! for WP network
$_stumble_on_network       = false;
$_stumble_network_percent  = 75;
$_stumble_network_cat      = "other";

load_plugin_textdomain('stumble', "/wp-content/plugins/".dirname(plugin_basename(__FILE__))); // we'll need the language data

function _stumble_altUrlsMap($data) {
	return explode(' ', $data);
}

function _stumble_options_panel() {
	global $_stumble_favour, $_stumble_support, $_stumble_auto_insert, $_stumble_altUrls, $wpdb, $_stumble_table_stats, $_stumble_on_network, $_stumble_network_percent, $_stumble_network_cat;
	
	if($_POST['_stumble_slashes'] == '\\\'') { // slashes problem
		$_POST['_stumble_favour_comments']      = stripslashes($_POST['_stumble_favour_comments']);
	}
	
	if(isset($_POST['Submit'])) {
	
		check_admin_referer('stumble-update-options');
		
		if($_POST['_stumble_favour_comments_relative'] <= 0)
			$_POST['_stumble_favour_comments_relative'] = 1;
		
		$data = serialize(array('comments' => array($_POST['_stumble_favour_comments']/100, $_POST['_stumble_favour_comments_relative'], $_POST['_stumble_favour_comments_limit'])));
		
		$altUrls = $_POST['_stumble_altUrls'];
		$altUrls = serialize(array_map('_stumble_altUrlsMap', array_filter(array_map('trim', explode("\n", $altUrls)))));
		
		$opt1 = get_option('_stumble_favour');
		if(is_array($opt1)) $opt1 = serialize($opt1);
		
		$opt2 = get_option('_stumble_altUrls');
		if(is_array($opt2)) $opt2 = serialize($opt2);
		
		if(($data != $opt1) || ($altUrls != $opt2)) {
		
			update_option('_stumble_favour', $data);
			update_option('_stumble_altUrls', $altUrls);
		
			// we need to update the table
			_stumble_update_table();
			
		}
		
		update_option('_stumble_auto_insert', $_POST['_stumble_auto_insert']);
		update_option('_stumble_support',     $_POST['_stumble_support']);
		
		update_option("_stumble_on_network", $_POST['_stumble_on_network']);
		update_option("_stumble_network_percent", $_POST['_stumble_network_percent']);
		update_option("_stumble_network_cat", $_POST['_stumble_network_cat']);
		
	}
	
	_stumble_get_options();

	?>
	
	<div class="wrap">
		
		<h2><?php _e("Stumble! for WordPress", 'stumble'); ?></h2>
		
		<?php if(isset($_POST['Submit'])) { ?>
			<p><div id="message" class="updated fade"><p><strong><?php _e("Your settings have been updated.", 'stumble'); ?></strong></p></div></p>
		<?php } ?>
		
		<h3><?php _e("Statistics", 'stumble'); ?></h3>
		
		<?php
			
			$table = '<table cellspacing="5" cellpadding="0" border="0"><tr><th align="left">URL</th><th align="left">Hits</th></tr>';
			
			$i = 10;
			
			$results = $wpdb->get_results("SELECT url, hits FROM `$_stumble_table_stats` ORDER BY hits DESC LIMIT 0, ".$i++, ARRAY_A);
			
			/*
				Thanks to an anonymous user, "dhave config", for pointing out that I didn't check
				$results was an array
			*/
			
			if(is_array($results)) foreach($results as $result) {
			
				if($result['url'] == '~') {
					// this should always have the maximum value!
					$overall_hits = $result['hits'];
					continue;
				}
				
				if($i-- == 0) break;
				
				$urld = $result['url'];
				$len = strlen($urld);
				$urld = substr($urld,0, 50);
				
				if(strlen($urld) < $len) $urld .= '...';
			
				$table .= '<tr><td><a href="'.htmlspecialchars($result['url']).'" target="_blank">'. $urld .'</a></td><td>'. $result['hits'] .'</td></tr>';
			}
			
			$table .= '</table>';
			
		?>
			<h4><?php printf(__('Total Stumbles: %d', 'stumble'), $overall_hits); ?></h4>
		<?php
			
			echo $table;
				
		?>
		
		<div style="height: 70px;"></div>
			
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__) ?>">
			<?php wp_nonce_field('stumble-update-options'); ?>
			
			<input type="hidden" name="_stumble_slashes" value="'" />
			
			<h3><?php _e('Options', 'stumble'); ?></h3>
			
				<div style="border: 1px dashed #999999; padding: 10px;">
			
					<p><?php _e("The options below specify how the number of comments will 'weigh' on an article's probability of being stumbled. For example, if 'favour by comments' is set to 100% relative to 50, an article with 50 comments is 100% more likely to be stumbled than an article with none. Set these values to 0 for <strong>truely random</strong> articles.", 'stumble'); ?></p>
					
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
						
					</table>
					
				</div>
			
			<table cellspacing="7" cellpadding="5" border="0">
				
				<tr>
					<td align="right" valign="top"><input type="checkbox" name="_stumble_auto_insert" id="_stumble_auto_insert" value="true" onchange="document.getElementById('_stumble_support').disabled = this.checked==false;"<?php if($_stumble_auto_insert) { ?> checked="checked"<?php } ?> /></td>
					
					<td>
						<div style="padding-bottom: 3px;"><?php _e("Automatically insert the 'Liked this article?' button at the end of all articles.", 'stumble'); ?></div>
						<small><input type="checkbox" name="_stumble_support" id="_stumble_support" value="true"<?php if($_stumble_support) { ?> checked="checked"<?php } ?> /> Mention Stumble! for WordPress below this button</small>
						
						<script type="text/javascript">
							document.getElementById('_stumble_support').disabled = document.getElementById('_stumble_auto_insert').checked==false;
						</script>
					</td>
				</tr>
				
				<tr>
					<td valign="top"><?php _e('Additional URLs:', 'stumble'); ?></td>
					
					<td>
						<textarea name="_stumble_altUrls" cols="50" rows="8"><?php if(is_array($_stumble_altUrls)) foreach($_stumble_altUrls as $cururl) { echo $cururl[0]." ".$cururl[1]."\n"; } ?></textarea> <br />(<small><?php _e("List of URLs, apart from posts, which users can Stumble! to", 'stumble'); ?></small>)<br />
						<p><?php _e("Write Like This:", 'stumble'); ?></p>
						<blockquote>
							50 http://making-the-web.com/
						</blockquote>
						<p><?php _e("Where the number (50, in this case) is the number of \"comments\" on the page. The page doesn't necessarily have to use comments, but this indicates the importance of the page, relative to your blog's posts.", 'stumble'); ?></p>
						<p><?php _e("One line per URL", 'stumble'); ?></p>
					</td>
				</tr>
				
			</table>
			
			<div style="border: 1px solid #000066; padding: 10px;">
			
				<h3><?php _e("Networked blogs", 'stumble'); ?></h3>
				
				<p><?php _e("If you join the <i>Stumble! for WordPress</i> network, readers from your blog will be sent to other, relevant blogs. Similarly, readers will be sent to articles on your site.", 'stumble'); ?></p>
				
				<table cellspacing="7" cellpadding="5" border="0">
					
					<tr>
						<td valign="top" align="right"><input type="checkbox" name="_stumble_on_network" id="_stumble_on_network" value="true" <?php if($_stumble_on_network) { ?> checked="checked"<?php } ?> /></td>
						<td valign="top"><?php _e('Join Network', 'stumble'); ?></td>
					</tr>
					
					<tr>
						<td valign="top"></td>
						<td valign="top"><?php printf(__('Send %s%% of stumbles to networked blogs.', 'stumble'), '<input type="text" name="_stumble_network_percent" size="5" value="'. $_stumble_network_percent .'" />'); ?></td>
					</tr>
					
					<tr>
						<td valign="top"><?php _e("Category:", 'stumble'); ?></td>
						<td valign="top">
							
							<select name="_stumble_network_cat">
						
							<?php
							
								$cats = array(
								
									'autos'           => __("Autos", 'stumble'),
									'baby'            => __("Baby", 'stumble'),
									'news'            => __("News", 'stumble'),
									'business'        => __("Business", 'stumble'),
									'gambling'        => __("Gambling", 'stumble'),
									'computer'        => __("Computer", 'stumble'),
									'education'       => __("Education", 'stumble'),
									'health'          => __("Health", 'stumble'),
									'entertainment'   => __("Entertainment", 'stumble'),
									'personal'        => __("Personal", 'stumble'),
									'science'         => __("Science", 'stumble'),
									'religion'        => __("Religion", 'stumble'),
									'seo'             => __("SEO", 'stumble'),
									'technology'      => __("Technology", 'stumble'),
									'web-dev'         => __("Web Development", 'stumble'),
									'adult'           => __("Adult", 'stumble'),
									'other'           => __("Other", 'stumble')
									
								
								);
								
								asort($cats);
								
								foreach($cats as $val => $name) {
									?>
									<option value="<?php echo $val; ?>"<?php if($_stumble_network_cat == $val) { ?> selected="selected"<?php } ?>><?php echo $name; ?></option>
									<?php
								}
							
							
							?>
							
							</select>
						
						</td>
					</tr>
					
				</table>
				
			</div>
			
			
			
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save Changes'); ?>" />
			</p>
			
			<div style="height: 70px;"></div>
			
			<h3><?php _e("How to use Stumble! for WordPress", 'stumble'); ?></h3>
			
			<p><?php _e("Stumble! for WordPress is easy to integrate with any blog. To go to a random article, you can create a link to the following page:", 'stumble'); ?></p>
			
			<blockquote><?php bloginfo('url'); ?>/?stumble</blockquote>
			
			<p><?php _e("To override the network system, simply use:", 'stumble'); ?></p>
			
			<blockquote><?php bloginfo('url'); ?>/?stumble=&nonetwork=true</blockquote>
			
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
			
			<p><?php _e("Disclaimer: This plugin is not associated with <a href=\"http://www.stumbleupon.com/\">StumbleUpon</a>.", 'stumble'); ?></p>
		
	</div>
	
	<?php
}

function _stumble_options() {
	if (function_exists('add_options_page')) {
		add_options_page('Stumble! for WordPress', 'Stumble! for WordPress', 8, plugin_basename(__FILE__), '_stumble_options_panel');
	}
}

function _stumble_get_options() {
	global $_stumble_favour, $_stumble_hasOptions, $_stumble_max, $_stumble_support, $_stumble_auto_insert, $_stumble_altUrls, $_stumble_on_network, $_stumble_network_percent, $_stumble_network_cat;
	
	$_stumble_hasOptions = true;
	
	$N_stumble_favour = get_option('_stumble_favour');
	if($N_stumble_favour !== false) {
		$_stumble_favour = $N_stumble_favour;
		if(!is_array($_stumble_favour)) 
			$_stumble_favour = unserialize($_stumble_favour);
	}
	
	$_stumble_max              = get_option('_stumble_max');
	$N_stumble_support         = get_option('_stumble_support');
	$_stumble_support          = ($N_stumble_support === false) ? $_stumble_support : $N_stumble_support;
	$N_stumble_auto_insert     = get_option('_stumble_auto_insert');
	$_stumble_auto_insert      = ($N_stumble_auto_insert === false) ? $_stumble_support : $N_stumble_auto_insert;
	
	$N_stumble_altUrls         = get_option('_stumble_altUrls');
	if(!is_array($N_stumble_altUrls)) $N_stumble_altUrls = unserialize($N_stumble_altUrls);
	$_stumble_altUrls          = ($N_stumble_altUrls === false) ? $_stumble_altUrls : $N_stumble_altUrls;
	
	$N_stumble_on_network      = get_option('_stumble_on_network');
	$_stumble_on_network       = ($N_stumble_on_network === false) ? $_stumble_on_network : $N_stumble_on_network;
	
	$N_stumble_network_percent      = get_option('_stumble_network_percent');
	$_stumble_network_percent       = ($N_stumble_network_percent === false) ? $_stumble_network_percent : $N_stumble_network_percent;
	
	$N_stumble_network_cat      = get_option('_stumble_network_cat');
	$_stumble_network_cat       = ($N_stumble_network_cat === false) ? $_stumble_network_cat : $N_stumble_network_cat;
	
}

function _stumble_update_table($record = 0) {
	global $wpdb, $_stumble_max, $_stumble_table, $_stumble_favour, $_stumble_altUrls;
	
	_stumble_get_options();
	
	$favourComments         = $_stumble_favour['comments'][0];
	$favourCommentsRelative = $_stumble_favour['comments'][1];
	$favourCommentsLimit    = $_stumble_favour['comments'][2];
	
	$favourCommentsRelative = ($favourCommentsRelative > 0) ? $favourCommentsRelative : 1; // we don't want to divide by zero
	
	$upscale = 65535/($favourCommentsLimit/$favourCommentsRelative*$favourComments+1);
	
	if($record) {
	
		$origprob = $wpdb->get_var("SELECT prob FROM $_stumble_table WHERE `postid` = $record");
		
		if(!$origprob) {
			$wpdb->query("INSERT INTO $_stumble_table (postid, url, prob) VALUES ($record, '', 0)");
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
		
		$query = "INSERT INTO $_stumble_table (postid, url, prob) VALUES ";
		
		$first = true;
		
		if(is_array($results)) foreach($results as $result) {
		
			$comments = $result->comment_count;
			$comments = ($result->comment_count > $favourCommentsLimit) ? $favourCommentsLimit : $comments;
		
			$prob = floor((($comments/$favourCommentsRelative*$favourComments)+1)*$upscale);
		
			$query .= (($first) ? '' : ',')."($result->ID, '', $prob)";
			$first = false;
			
			$maxval += $prob;
		}
		
		dbDelta($query);
		
		update_option('_stumble_max', $maxval);
		
	}
	
	$query = "INSERT INTO $_stumble_table (postid, url, prob) VALUES ";
	$first = true;
	
	if(is_array($_stumble_altUrls)) foreach($_stumble_altUrls as $i => $val) {
		$comments = $val[0];
		$comments = ($comments > $favourCommentsLimit) ? $favourCommentsLimit : $comments;	
		
		$prob = floor((($comments/$favourCommentsRelative*$favourComments)+1)*$upscale);
		
		$query .= $wpdb->prepare((($first) ? '' : ',')."(0, %s, $prob)", $val[1]);
		$first = false;
		$maxval += $prob;
	}
	
	if(!$first) {
	
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
	global $wpdb, $_stumble_table, $_stumble_table_stats, $_stumble_version;
	
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	$structure = array(
				'id'             => array('Key' => 'PRI'),
				'postid'         => array('Key' => 'MUL'),
				'url'            => array(),
				'prob'           => array()
				);
				
	$results = $wpdb->get_results("SHOW COLUMNS FROM `".$_stumble_table."`", ARRAY_A);
	
	$needsupdate = false;
	
	$tf = count($structure);
	
	if(isset($results)) foreach($results as $result) {
		if(isset($structure[$result['Field']])) {
			foreach($structure[$result['Field']] as $req => $value) {
				if($result[$req] != $value)
					$needsupdate = true;
			}			
		}
		$tf--;
	}
	
	if($tf != 0) $needsupdate = true;
	
	if(($wpdb->get_var("SHOW TABLES LIKE '".$_stumble_table."'") != $_stumble_table) || $needsupdate) {
		
		$sql = "DROP TABLE IF EXISTS `".$_stumble_table."`";
	
		$wpdb->query($sql);
		
		$sql = "CREATE TABLE ".$_stumble_table." (
						`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
						`postid` INT UNSIGNED NOT NULL,
						`url` TEXT NOT NULL,
						`prob` SMALLINT UNSIGNED NOT NULL,
						KEY ( `postid` ),
						PRIMARY KEY (`id`)
					);";
	
		dbDelta($sql);
		
		_stumble_update_table();
		
	}
	
	/* Statistics table */
	$structure = array(
				'urlhash'        => array('Key' => 'MUL'),
				'url'            => array(),
				'hits'           => array('Key' => 'MUL')
				);
				
	$results = $wpdb->get_results("SHOW COLUMNS FROM `".$_stumble_table_stats."`", ARRAY_A);
	
	$needsupdate = false;
	
	$tf = count($structure);
	
	if(isset($results)) foreach($results as $result) {
		if(isset($structure[$result['Field']])) {
			foreach($structure[$result['Field']] as $req => $value) {
				if($result[$req] != $value)
					$needsupdate = true;
			}			
		}
		$tf--;
	}
	
	if($tf != 0) $needsupdate = true;
	
	if(($wpdb->get_var("SHOW TABLES LIKE '".$_stumble_table_stats."'") != $_stumble_table_stats) || $needsupdate) {
	
		$sql = "DROP TABLE IF EXISTS `".$_stumble_table_stats."`";
	
		$wpdb->query($sql);
		
		$sql = "CREATE TABLE ".$_stumble_table_stats." (
						`urlhash` INT NOT NULL,
						`url` TEXT NOT NULL,
						`hits` INT UNSIGNED NOT NULL,
						KEY ( `urlhash` ),
						KEY ( `hits` )
					);";
	
		dbDelta($sql);
	}	
	
	if(get_option('_stumble_auto_insert') === false) {
		update_option('_stumble_auto_insert', 'true');
	}
	
	if(get_option('_stumble_support') === false) {
		update_option('_stumble_support', 'true');
	}
	
	update_option('_stumble_installed', $_stumble_version);
	
	wp_clear_scheduled_hook('_stumble_update_table_event');
	wp_schedule_event(time(), 'hourly', '_stumble_update_table_event');
}

function _stumble_ShiftLeftAs32($val, $l) { /* 32 bit shift */
	$val = str_pad(decbin($val) . str_repeat(0, $l), 32, 0, STR_PAD_LEFT);
	$val = substr($val, strlen($val)-32);
	
	if($val[0] == '1')
		return bindec(substr($val, 1)) -2147483648;
		
	return bindec($val);
}

function _stumble_hashint($text) {
	$hash = pack('H*', md5($text));
		
	$hash = 
		  _stumble_ShiftLeftAs32(ord($hash[0]), 24)
		+ _stumble_ShiftLeftAs32(ord($hash[1]), 16)
		+ _stumble_ShiftLeftAs32(ord($hash[2]), 8)
		+(ord($hash[3]));
		
	return $hash;
}

function _stumble_updateStats($url) 
{
	global $wpdb, $_stumble_table_stats;
	
	$hashid = _stumble_hashint($url);

	$result = $wpdb->get_var($wpdb->prepare("SELECT `hits` FROM `$_stumble_table_stats` WHERE `urlhash` = %d AND `url` = %s", $hashid, $url));
	
	if($result === NULL) {
		$wpdb->query($wpdb->prepare("INSERT INTO `$_stumble_table_stats` (urlhash, url, hits) VALUES (%d, %s, 0)", $hashid, $url));
	}
	
	$wpdb->query($wpdb->prepare("UPDATE `$_stumble_table_stats` SET hits = hits+1 WHERE urlhash = %d AND url = %s", $hashid, $url));
}

function _stumble_stumbleGo($id, $url) 
{
	global $wpdb, $_stumble_table_stats;
	
	_stumble_cookie_append($id);
	
	_stumble_updateStats($url);
	
	_stumble_updateStats('~'); // update overall statistics
	
	header('HTTP/1.0 301 Moved Permanently');
	header('Location: '.$url);
	
}

function _stumble_stumble($int = false) {
	global $_stumble_favour, $post, $wpdb, $_stumble_max, $_stumble_table, $_stumble_table_stats, $_stumble_on_network, $_stumble_network_percent, $_stumble_network_cat;
	
	_stumble_get_options();
	
	$visited = _stumble_cookie_arr();
	
	if($_GET['stumble'][0] != '/') {
		if($_stumble_on_network == "true" && !isset($_GET['nonetwork'])) {
			if(rand(0, 100) < $_stumble_network_percent) {
				header('HTTP/1.0 301 Moved Permanently');
				header('Location: http://stumble.making-the-web.com/?cat='.$_stumble_network_cat.'&r='.urlencode(get_bloginfo('url')));
				exit;
			}
		}
	
		header('HTTP/1.0 301 Moved Permanently');
		header('Location: '.get_bloginfo('url').'/?stumble=/'.intval($_GET['stumble']));
		exit;
	}
	
	if(($_GET['stumble'] != '/0') && function_exists('yarpp_sql')) { // if there is a post specified, integrate with YARPP, if it is installed
	
		query_posts('p='.intval(substr($_GET['stumble'], 1)));
	
		the_post();
		
		$ret = _stumble_yarpp();
		
		for($max = count($ret); $max > 0;$max--) {
			if($max <= 0) break;
			$i = rand(0, $max);
			
			$id = $wpdb->get_var("SELECT `id` FROM `$_stumble_table` WHERE `postid` = ".intval($ret[$i]->ID));
			
			if(!isset($visited[$id])) {		
				//_stumble_cookie_append($id);
				//header('Location: '.get_permalink($ret[$i]->ID));
				_stumble_stumbleGo($id, get_permalink($ret[$i]->ID));
				exit;
			}
			
			array_splice($ret, $i, 1);
			
		}
	
	}
	
	
	$stats = array();
	
	$sqlcond = '';;
	
	if(is_array($visited)) foreach($visited as $val => $null) {
		$sqlcond .= ' AND id != '.$val;
	}
	
	$sqlcond2 = '';
	
	if(is_array($visited)) foreach($visited as $val => $null) {
		$sqlcond2 .= ' OR id = '.$val;
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
	
	$result = $wpdb->get_row("SELECT `id`, `postid`, `url` FROM `$_stumble_table` WHERE RAND() < `prob`/$div$sqlcond LIMIT 0, 1", ARRAY_N);
	/*
		MySQL's RAND() is not very random at all. Leads me to think something better could be done here (above).
	*/
	
	// Most of the time, the above query will give us a value. Sometimes, it might now, so in this case we just take a pure record
	
	if($result === NULL) {
		$result = $wpdb->get_row("SELECT `id`, `postid`, `url` FROM `$_stumble_table`".($sqlcond ? ' WHERE '.substr($sqlcond, 4) : '')." ORDER BY RAND() LIMIT 0, 1", ARRAY_N);
		if($result === NULL) {
		
			if($visited) {
				_stumble_cookie_delete();
				if(!$int) _stumble_stumble(true);
			}
		
			return; // just give up, there's nothing we can return
		}
	}
	
	_stumble_cookie_append($result[0]);
	
	if(!$result[1]) {
		$url = $result[2];
	}else{
		query_posts('p='.$result[1]);
		the_post();
		$url = get_permalink();
	}
	
	_stumble_stumbleGo($id, $url);
	
	//header('Location: '.$url);
	
	exit;
	
}

function _stumble_auto($data) {
	global $post;
	
	if(!is_single()) return $data;

	$button = '<div align="center" style="padding: 20px 5px;"><a href="'.get_bloginfo('url').'/?stumble='.$post->ID.'" rel="nofollow"><img src="'.get_bloginfo('url').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__)).'/liked.png" border="0" width="300" height="92" alt="'.__("Liked this article? Read another similar article.", 'stumble').'" /></a>'.((get_option('_stumble_support') == "true") ? '<br /><small><a href="http://making-the-web.com/stumble-for-wordpress/">Powered by Stumble! for WordPress</a></small>' : '').'</div>';
	
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

function _stumble_cat() {
	global $_stumble_network_cat;
	_stumble_get_options();
	echo "***".$_stumble_network_cat."***";
	exit;
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

if(isset($_GET['stumblecat'])) {
	add_action('init', '_stumble_cat');
}

if(get_option('_stumble_auto_insert') == "true") {
	add_filter('the_content','_stumble_auto',1);
}

if(get_option('_stumble_installed') != $_stumble_version)
	_stumble_install();

?>