<?php

/*
Plugin Name: Stumble! For WordPress
Plugin URI: http://infinity-infinity.com/2009/07/stumble-for-wordpress/
Description: Adds "random article" functionality to Wordpress, similar to StumbleUpon and Wikipedia's random article feature
Author: Brendon Boshell
Version: 1.1.1
Author URI: http://infinity-infinity.com/
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

$_stumble_version          = "1.1.1";
$GLOBALS['_stumble_table'] = $GLOBALS['wpdb']->prefix . "stumble"; // Wordpress wouldn't put these in the global space for me when activating :(
$GLOBALS['_stumble_table_stats'] = $GLOBALS['wpdb']->prefix . "stumble_stats";
$_stumble_hasOptions       = false;
$_stumble_favour           = array('comments' => array(400, 50, 50));
$_stumble_max              = 1;
$_stumble_support          = true;
$_stumble_auto_insert      = true;
$_stumble_altUrls          = array();

// new in version 1.0 - Stumble! for WP network
$_stumble_on_network       = true;
$_stumble_network_percent  = 75;
$_stumble_network_cat      = "other";

load_plugin_textdomain('stumble', "/wp-content/plugins/".dirname(plugin_basename(__FILE__))); // we'll need the language data

function _stumble_altUrlsMap($data) {
	return explode(' ', $data);
}

function _stumble_options_panel() {
	global $_stumble_favour, $_stumble_support, $_stumble_auto_insert, $_stumble_altUrls, $wpdb, $_stumble_table_stats, $_stumble_on_network, $_stumble_network_percent, $_stumble_network_cat;
	
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
		
		if($_POST['_stumble_network_public'] == "no") {
            update_option("_stumble_network_private", "true");
			if($_POST['_stumble_network_private_code'] != get_option('_stumble_network_cat')) {
			
				update_option("_stumble_network_cat", $_POST['_stumble_network_private_code']);		 
				?>
					<iframe src="http://stumble.22talk.com/?cat=<?php echo htmlspecialchars(urlencode($_POST['_stumble_network_private_code'])); ?>&r=<?php echo htmlspecialchars(urlencode(get_bloginfo('url'))); ?>&join=true" frameborder="0" width="0" height="0" style="width: 0; height: 0;"></iframe>
				<?php
			
			}	
		}else{
			update_option("_stumble_network_private", "false");
			update_option("_stumble_network_cat", $_POST['_stumble_network_cat']);
		}
		
	}
	
	_stumble_get_options();

	?>
	
	<div class="wrap">
		
		<h2><?php _e("Stumble! for WordPress", 'stumble'); ?></h2>
		
		<?php if(isset($_POST['Submit'])) { ?>
			<p><div id="message" class="updated fade"><p><strong><?php _e("Your settings have been updated.", 'stumble'); ?></strong></p></div></p>
		<?php } ?>
		
		<?php
			
			$table = '<table cellspacing="5" cellpadding="0" border="0"><tr><th align="left">URL</th><th align="left">Hits</th></tr>';
			
			$i = 10;
			
			$results = $wpdb->get_results("SELECT url, hits FROM `$_stumble_table_stats` ORDER BY hits DESC LIMIT 0, ".$i++, ARRAY_A);
			
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
			
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__) ?>">
			<?php wp_nonce_field('stumble-update-options'); ?>
			
			<input type="hidden" name="_stumble_slashes" value="'" />
			
			<div>
			
				<h3><?php _e("Networked blogs", 'stumble'); ?></h3>
				
				<p><?php _e("If you join the <i>Stumble! for WordPress</i> network, readers from your blog will be sent to other, relevant blogs. Similarly, readers will be sent to articles on your site.", 'stumble'); ?></p>
				
				<table cellspacing="7" cellpadding="5" border="0">
					
					<tr>
						<td valign="top" align="right"><input type="checkbox" name="_stumble_on_network" id="_stumble_on_network" value="true" <?php if($_stumble_on_network) { ?> checked="checked"<?php } ?> /></td>
						<td valign="top"><?php _e('Join a Network', 'stumble'); ?></td>
					</tr>
					
					<tr>
						<td valign="top"></td>
						<td valign="top"><?php printf(__('Send %s%% of stumbles to networked blogs.', 'stumble'), '<input type="text" name="_stumble_network_percent" size="5" value="'. $_stumble_network_percent .'" />'); ?></td>
					</tr>
					
					<?php
							
								$list = "";
							
								$publicCat = false;
							
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
									$list .= "<option value=\"".$val."\"";
									if($_stumble_network_cat == $val) { 
										$publicCat = true;
										$list.= " selected=\"selected\"";
									}
									$list .= ">".$name."</option>";
								}
							
							
							?>
					
					<tr>
						<td valign="top" align="right"><input type="radio" name="_stumble_network_public" value="yes"<?php if($publicCat) { ?> checked="checked"<?php } ?> /></td>
						<td valign="top">
							
							<?php _e("Public Network", 'stumble'); ?>
							
						</td>
						
					</tr>
					
					<tr>
					
						<td></td>
						<td>
							
							<?php _e("Category:", 'stumble'); ?> <select name="_stumble_network_cat" style="vertical-align: middle;">
						
							<?php
								echo $list;
							?>
							
							</select>
							
						</td>
						
					</tr>
					
					<tr>
						<td valign="top" align="right"><input type="radio" name="_stumble_network_public" value="no"<?php if(!$publicCat) { ?> checked="checked"<?php } ?> /></td>
						<td valign="top">
							
							<?php _e("Private Network", 'stumble'); ?>
							
						</td>
						
					</tr>
					
					<tr>
						<td></td>	
						
						<td>
						
							<script type="text/javascript">
								function genCode() {
									var code = ''; 
									for(var a = 0; a < 9; a++) {
										code += String.fromCharCode( Math.floor((Math.random()*26)+97));
									} 
									document.getElementById('_stumble_network_private_code').value = code;
								}
							</script>
							
							<?php _e("Private Key:", 'stumble'); ?> <input type="text" name="_stumble_network_private_code" id="_stumble_network_private_code" value="<?php if(!$publicCat) { echo htmlspecialchars($_stumble_network_cat); }?>" /> <input type="button" name="rand" value="Random" onclick="genCode();" />
							
							<?php if($publicCat) { ?>
								<script type="text/javascript">
									genCode();
								</script>
							<?php } ?>
							
							<p><?php _e("If you have a collection of a blogs, you can set-up your own private network to exchange stumbles between your blogs only. Firstly, generate a random key. Then, copy this key to the 'Private Key' field for all of your other blogs. After a short while, you will be able to stumble across your own private network. After clicking save, <strong>wait for confirmation</strong> from the network.", 'stumble'); ?></p>
						
						</td>
					</tr>
					
				</table>
				
			</div>
			
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save Changes'); ?>" />
			</p>
			
			<div style="height: 70px;"></div>
			
			<h3><?php _e('Options', 'stumble'); ?></h3>
			
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
								<input type="text" name="_stumble_favour_comments_relative" size="5" value="<?php echo $_stumble_favour['comments'][1]; ?>" /> (<small><?php _e('With how many comments should an article be 100% more likely to be stumbled than an article with none? Probability will be calculated by the number of comments divided by the "relative to" value. Can not be 0.', 'stumble'); ?></small>)
							</td>
						</tr>
						
						<tr>
							<td><?php _e('Limit:', 'stumble'); ?></td>
							
							<td>
								<input type="text" name="_stumble_favour_comments_limit" size="5" value="<?php echo $_stumble_favour['comments'][2]; ?>" /> (<small><?php _e('When the number of comments reaches this value, favouring no longer becomes a factor.', 'stumble'); ?></small>)
							</td>
						</tr>
						
					</table>
					
					
			
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
						<textarea name="_stumble_altUrls" cols="50" rows="4"><?php if(is_array($_stumble_altUrls)) foreach($_stumble_altUrls as $cururl) { echo $cururl[0]." ".$cururl[1]."\n"; } ?></textarea> <br />(<small><?php _e("List of URLs, apart from posts, which users can Stumble! to", 'stumble'); ?></small>)<br />
						<p><?php _e("Write Like This:", 'stumble'); ?></p>
						<blockquote>
							50 http://infinity-infinity.com/
						</blockquote>
						<p><?php _e("Where the number (50, in this case) is the number of \"comments\" on the page. The page doesn't necessarily have to use comments, but this indicates the importance of the page, relative to your blog's posts.", 'stumble'); ?></p>
						<p><?php _e("One line per URL", 'stumble'); ?></p>
					</td>
				</tr>
				
			</table>		
			
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save Changes'); ?>" />
			</p>
			
			<div style="height: 70px;"></div>
			
			<h3><?php _e("Statistics", 'stumble'); ?></h3>
			
			<h4><?php printf(__('Total Stumbles: %d', 'stumble'), $overall_hits); ?></h4>
			<?php
				
				echo $table;
					
			?>
		
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

$GLOBALS['_stumble_startedinstall'] = false;

function _stumble_install() {
	global $wpdb, $_stumble_table, $_stumble_table_stats, $_stumble_version, $_stumble_startedinstall;
	
	if($_stumble_startedinstall) return;
	$_stumble_startedinstall = true;
	
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	$upgrade = get_option('_stumble_installed') ? true : false;
	$notifyInstall = (get_option('_stumble_installed') == $_stumble_version) ? false : true;
	
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
	wp_schedule_event(time(), 'daily', '_stumble_update_table_event');
	
	if($notifyInstall) {
	
		$message  = __("Hi\n\n", "stumble");
		$message .= __("Stumble! for WordPress ".$_stumble_version." has been successfully installed.\n\n", "stumble");
		
		if($upgrade)
			$message .= __("Your previous settings have been applied.\n\n", "stumble");
		else{
			$message .= __("We have automatically placed the 'Stumble!' button at the bottom of every post on your blog. You are also able to create your own buttons and links, rather than using the default button. To stumble through your blog, simply link to this URL:\n\n", "stumble");
			$message .= __(get_bloginfo('url')."/?stumble\n\n", "stumble");
			$message .= __("You have also been automatically joined up to the Stumble! for Wordpress network which will send stumblers to your blog. Please make sure you set your site's category from the settings page.\n\n", "stumble");
		}
		
		if(get_option('_stumble_on_network') != "true") {
		
			$message .= __("You are *not* currently using the Stumble! for WordPress network to allow readers from other blogs to stumble through your posts. Joining the network will help increase the number of pageviews your blog receives, and ultimately leads to more subscribers.\n", "stumble");
			$message .= __("The network is totally free, and you can easily join from the plugin's option page.\n\n", "stumble");
		
		}
		
		$message .= __("If you require any help in setting up the plugin, or have any suggestion or question, just leave a comment at Infinity-Infinity.com forums: http://infinity-infinity.com/forums/forum/4\n\n", "stumble");
		
		$message .= __("Thank you for using the plugin,\nBrendon Boshell\n\nhttp://www.infinity-infinity.com/\n\n", "stumble");
		
		$message .= __("(Sent via your WordPress blog)", "stumble");
	
		wp_mail(get_option("admin_email"), ($upgrade ? __("Stumble! for WordPress upgraded", "stumble") : __("Stumble! for WordPress installed", "stumble")), $message, "");
		
	}
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
	
	/* A call to exit() should be made in the parent */
	
}

function _stumble_stumble($int = false) {
	global $_stumble_favour, $post, $wpdb, $_stumble_max, $_stumble_table, $_stumble_table_stats, $_stumble_on_network, $_stumble_network_percent, $_stumble_network_cat;
	
	_stumble_get_options();
	
	$visited = _stumble_cookie_arr();
	
	if($_GET['stumble'][0] != '/') {
		if($_stumble_on_network == "true" && !isset($_GET['nonetwork'])) {
			if(rand(0, 100) < $_stumble_network_percent) {
			
				$cat = (get_option("_stumble_network_private") === "true") ? "" : $_stumble_network_cat; // private networks don't want to disclose their key - network subscription has been negotiated with MTW
			
				header('HTTP/1.0 301 Moved Permanently');
				header('Location: http://stumble.22talk.com/?cat='.$cat.'&r='.urlencode(get_bloginfo('url')));
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
	
	$sqlcond = '';
	
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
	
	//_stumble_cookie_append($result[0]); // this is handled in _stumble_stumbleGo()
	
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

	$button = '<div align="center" style="padding: 20px 5px;"><a href="'.get_bloginfo('url').'/?stumble='.$post->ID.'" rel="nofollow"><img src="'.get_bloginfo('url').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__)).'/liked.png" border="0" width="300" height="92" alt="'.__("Liked this article? Read another similar article.", 'stumble').'" /></a>'.((get_option('_stumble_support') == "true") ? '<br /><small><a href="http://infinity-infinity.com/stumble-for-wordpress/" rel="nofollow">Stumble! for WP</a></small>' : '').'</div>';
	
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
	$_COOKIE['_stumble_recent'] = $cookievals; // for future reference in the script
}

function _stumble_cat() {
	global $_stumble_network_cat, $_stumble_version;
	_stumble_get_options();
	
 	$resp = ($_GET['stumblecheck'] == $_stumble_network_cat) ? "YES" : "NO";
	
	echo "***VER-".$_stumble_version."***".$resp."***";
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
	add_action('init', '_stumble_install');

?>