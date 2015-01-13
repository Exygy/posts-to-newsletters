<?php
/*
Plugin Name: Posts to Newsletter
Plugin URI: 
Description: Create newsletters from posts
Author: Exygy
Author URI: http://exygy.com 
Version: 1.0
*/

define('PTN_PLUGIN_NAME', 'Posts to Newsletter');
define('PTN_NEWSLETTER_POST_TYPE', 'ptnewsletter');

add_action('init', 'setup_newsletter_post_type');
add_action('admin_head', 'ptn_admin_styles_and_scripts');
add_action('init', 'ptn_setup_custom_fields');
add_action('init', 'ptn_enqueue_scripts');
register_activation_hook( __FILE__, 'ptn_rewrite_flush' );
register_activation_hook( __FILE__, 'ptn_setup_options' );


function setup_newsletter_post_type() {
	$labels = array(
		'name' => _x('Newsletters', 'post type general name'),
		'singular_name' => _x('Newsletter', 'post type singular name'),
		'add_new' => _x('Add New', 'newsletter'),
		'add_new_item' => __('Add New Newsletter'),
		'edit_item' => __('Edit Newsletter'),
		'new_item' => __('New Newsletter'),
		'view_item' => __('View'),
		'search_items' => __('Search Newsletterss'),
		'not_found' =>  __('No newsletters found'),
		'not_found_in_trash' => __('No newsletters found in Trash'), 
		'parent_item_colon' => ''
	);
	
	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true, 
		'query_var' => true,
		'rewrite' => array('slug'=> 'newsletter'),
		'capability_type' => 'post',
		'hierarchical' => false,
		'menu_position' => null,
		//'supports' => array('title'),
	); 
	register_post_type(PTN_NEWSLETTER_POST_TYPE, $args);
}

function ptn_setup_custom_fields() {
	if ( is_admin() ) { 
		include_once(dirname(__FILE__) . '/enhanced-custom-fields/enhanced-custom-fields.php');
		$sections = array();
		$fields = array();
		$newsletter_panel =& new PECF_Panel('newsletter-data', 'Select posts for newsletter', PTN_NEWSLETTER_POST_TYPE, 'normal', 'high');
		if ( get_option('ptn_highlights') != 'dont_show' ) {
			$fields[] = PECF_Field::factory('selectMulti', 'highlights');
			$sections[] = 'highlights';
		}
		if ( get_option('ptn_featured_post') != 'dont_show' ) {
			$fields[] = PECF_Field::factory('select', 'featured_post');
			$sections[] = 'featured_post';
		}
		if ( get_option('ptn_main_articles') != 'dont_show' ) { 
			$fields[] = PECF_Field::factory('selectMulti', 'main_articles', 'Main section');
			$sections[] = 'main_articles';
		}
		if ( get_option('ptn_side_articles') != 'dont_show' ) {
			$fields[] = PECF_Field::factory('selectMulti', 'side_articles', 'Side section');
			$sections[] = 'side_articles';
		}		
		
		// add posts to sections
		$i = 0;
		foreach ($sections as $section) {
		
			// for multiselects, get selected posts and put them at the top in the correct order
			$featured_post_options = array();
			$all_posts = get_post_meta($_GET['post'], '_' . $section, true);
			$post_IDs = "";
			$j = 0; 
			if (is_array($all_posts)) { 
				foreach($all_posts as $post_ID) {
					$j++;
					if ($j > 1) { $post_IDs .= ","; }
					$post_IDs .= $post_ID;
					
					$selected_post = get_post($post_ID);
					$featured_post_options[$selected_post->ID] = $selected_post->post_title;
				}
			}
	
			// get the rest of the posts
			$post_type = get_option('ptn_' . $section); 
			$latest_posts = get_posts('post_type=' . $post_type . '&numberposts=' . get_option('ptn_number_latest_posts') . '&exclude=' . $post_IDs); 
	
			foreach ($latest_posts as $post) {
				$featured_post_options[$post->ID] = $post->post_title; 
			}
			
			$fields[$i]->add_options($featured_post_options);
			$i++;
		}
		$fields[] = PECF_Field::factory('media', 'header_image', 'Header Image')->set_labels('Header Image')->set_post_type(PTN_NEWSLETTER_POST_TYPE)->help_text('<p style="color:red">Note: Make sure to click the button that says "Header Image", and NOT the "Insert into Post" button. <br />If you upload your own image, you\'ll have to click the "Save all changes" button first, then click the "Show" link next to your image, then click the "Header Image" button.</p>');
		$newsletter_panel->add_fields($fields);

	} 
}




// creates newsletter based on selected posts and saves it into the content field
function ptn_update_newsletter($postID){
	global $wpdb, $_POST;

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return;
	
	// get updated content
	$post = get_post($postID);
	if ($post->post_type==PTN_NEWSLETTER_POST_TYPE && ($_POST['update_newsletter']==1 || $post->post_content=='')) {

		$content = get_option('ptn_newsletter_template');			
		$content = ptn_replace_shortcodes($content);
		
		$content = addslashes($content);
		$wpdb->query("UPDATE $wpdb->posts SET post_content='$content' WHERE ID=$postID");
	}
}
add_action('save_post','ptn_update_newsletter', 100);



function ptn_admin_styles_and_scripts() {
	global $post;

	if ($post->post_type == PTN_NEWSLETTER_POST_TYPE) {
		?>
		<link rel='stylesheet' href='<?php echo plugins_url('css/style.multi.css', __FILE__ ); ?>' type='text/css' media='all' /> 
		<link type="text/css" rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/base/ui.all.css" />
				<script type="text/javascript" charset="utf-8">
		(function($){
			$(function () { 
				$(".multiselect").multiselect(); 
				$('#titlewrap').prepend('Subject');
				
				<?php 
				// hide content field when adding new newsletter
				if ($post->post_content == "") { ?>
					$('#postdivrich').hide();
				<?php } else { ?>
					$('#newsletter-data h3').after('<tr class="pecf-field-container"><td class="pecf-label">&nbsp;</td><td colspan="2" style="color:red;padding:10px;">Note: Changing anything below will overwrite any changes above when you click the "Update" button.<input type="hidden" name="update_newsletter" value="0"></td></tr>');
					
					// set to update (overwrite) newsletter when one of the selects is changed
					$('a.remove-all, a.add-all, a.action, li.ui-state-default ui-element').click(function() {
						$('input[name="update_newsletter"]').val('1');
					});
					
					$('li.ui-state-default').live('mousedown', function() {
						$('input[name="update_newsletter"]').val('1');	
					});
					
					$('.pecf-pecf_fieldselect').change(function() {
						$('input[name="update_newsletter"]').val('1');
					});
				<?php } ?>
			});
		})(jQuery)
		</script>
<?php
	}	
}



function ptn_enqueue_scripts() {
	global $_GET;
	if ($_GET['post_type'] != "") {
		$post_type = $_GET['post_type'];
	} else {
		$post = get_post($_GET['post']);
		$post_type = $post->post_type;
	}
	if ($post_type == PTN_NEWSLETTER_POST_TYPE) {	
        wp_deregister_script( 'jquery-ui-core' );
        wp_register_script( 'jquery-ui-1.8', plugins_url( 'js/jquery-ui-1.8.custom.min.js' , __FILE__ ) );
        wp_register_script( 'ui-multiselect', plugins_url('js/ui.multiselect.js', __FILE__ ) );
        
        wp_enqueue_script( 'jquery-ui-1.8' );
        wp_enqueue_script( 'ui-multiselect' );
    }
}    

function ptn_shorten_string($string, $wordsreturned)
	/*  Returns the first $wordsreturned out of $string.  If string
	contains fewer words than $wordsreturned, the entire string
	is returned.
	*/
	{
	$retval = $string;    
	 
	$array = explode(" ", $string);
	if (count($array)<=$wordsreturned){
		$retval = $string;
	} else {
		array_splice($array, $wordsreturned);
		$retval = implode(" ", $array)." ...";
	}
	return $retval;
}

function ptn_setup_options() {
	$default_options = array(	
		'ptn_highlights'=> 'post',
		'ptn_featured_post'=> 'post',
		'ptn_main_articles'=> 'post',
		'ptn_side_articles'=> 'post',
		'ptn_number_latest_posts'=> 100,
		'ptn_newsletter_template'=> ptn_get_default_template(),
	);
	
	foreach ($default_options as $option_name => $option_val) {
		add_option($option_name, $option_val);
	}

}

function ptn_rewrite_flush() {
    setup_newsletter_post_type();
   	global $wp_rewrite;
	$wp_rewrite->flush_rules(); 
}

function ptn_get_default_template() {
	$template = <<<ENDTEMPLATE
<div bgcolor="#E5DCD0" style="margin:0" alink="#D06D19">
<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#E5DCD0">
  <tr>
    <td align="center"><table width="600" border="0" align="center" cellpadding="0" cellspacing="0">
      <tr>
        <td>
			[logo]
        </td>
      </tr>
      <tr>
        <td bgcolor="#555555" style="padding:5px 25px;font-family:Verdana, Geneva, sans-serif;font-size:14px;font-weight:bold;color:#fff">Highlights:</td>
      </tr>
      <tr>
        <td align="left" bgcolor="#777777" style="padding:5px 25px;font-family:Verdana, Geneva, sans-serif;font-size:11px;color:#fff"><ul>
        [highlights_section]
		       
          </ul>
          </td>
      </tr>
      <tr>
        <td colspan="2" bgcolor="#2D2D25"> 
		[header_image]
		
		</td>
      </tr>
      <tr>
            <td width="565" align="left" bgcolor="#2D2D25" style="padding:20px;font-family:Verdana, Geneva, sans-serif;color:#fff;font-size:12px;line-height:18px">
			[featured_post]
			         
	   </td>
      </tr>
      <tr>
        <td colspan="2" bgcolor="#FFFFFF"><table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Verdana, Geneva, sans-serif;color:#2D2D25;font-size:11px;line-height:18px">
          <tr>
            <td width="396" align="left" valign="top" style="padding:15px 25px">
            [main_section]

              </td>
            <td align="left" valign="top" bgcolor="#F3F3F3" style="padding:15px 25px">
			[sidebar_section]

             </td>
          </tr>
        </table></td>
      </tr>
      
    </table></td>
  </tr>
</table>
</div>
ENDTEMPLATE;

	return $template;
}


add_filter('single_template', 'ptn_single_template') ;
function ptn_single_template($template) {
    global $post;           
    if ($post->post_type == PTN_NEWSLETTER_POST_TYPE)
        $template = dirname( __FILE__ ) . '/single-newsletter.php';
    return $template;
}

function ptn_render_highlights_section() {
	global $post;

	$highlights = get_post_meta($post->ID, '_highlights', true);
	$content = '';
	
	if ( is_array($highlights) ) { 
		foreach($highlights as $hpost) { 
			$hpost = get_post($hpost);
			$content.= '<li style="list-style:square;color:#ccc;padding-bottom:7px"><span style="color:#fff">' . $hpost->post_title . '</span></li>';
		}
	}
	return $content;
}

function ptn_render_header_image() {
	global $post;
	
	$header_image = get_post_meta($post->ID, '_header_image', true); 
	$content = '';
	
	if ( $header_image ) {
		$content = '<img src="' . wp_get_attachment_url($header_image) . '" width="600" border="0" alt="">';
	}
	return $content;
}

function ptn_render_featured_post() {
	global $post;
	
	$featured = get_post_meta($post->ID, '_featured_post', true);
	$content = '';
	
	if ( $featured ) {
		$featured_post = get_post($featured);
		$featured_link = get_permalink($featured);
	
	    $content = '<h1 style="color:#D06D19;font-size:14px;margin:0 0 10px 0"><a href="';
		
		$content.= get_permalink($featured);
		
		$content.= '" style="text-decoration:none;color:#D06D19" target="_blank">';
		
		$content.= $featured_post->post_title;
		
		$content.= '</a></h1>';
					
		$content.= ptn_shorten_string(strip_tags($featured_post->post_content), 150); //echo $featured_post->post_content;
					
		$content.= '<p><a href="';
		
		$content.= get_permalink($featured);
		
		$content.= '" style="color:#D06D19" target="_blank">Read more</a></p>';
	}
		
	return $content;

}

function ptn_render_main_section() {
	global $post;

	$main_articles = get_post_meta($post->ID, '_main_articles', true); 
	$content = '';
	
	if ( is_array($main_articles) ) { 
		foreach($main_articles as $article) {
			$article = get_post($article); 
			
			$content.= '<h2 style="font-size:13px"><a href="' . get_permalink($article->ID) . '" style="text-decoration:none;color:#2D2D25" target="_blank">' . $article->post_title . '</a></h2>';
			
			$post_image = get_post_meta($article->ID, '_post_image', true);
			if ($post_image) { 
				$content.= '<a href="' . get_permalink($article->ID) . '" target="_blank"><img src="' . wp_get_attachment_url($post_image) . '" width="345"></a>';
				
			}
			
			$content.= '<p>' . ptn_shorten_string(strip_tags($article->post_content), 100) . '</p>';
			
			$content.= '<p></p><a href="' . get_permalink($article) . '" style="color:#D06D19" target="_blank">Read more</a></p>';
	
		}
	}            
	return $content;
}


function ptn_render_sidebar_section() {
	global $post;

	$sidebar_articles = get_post_meta($post->ID, '_side_articles', true); 
	$content = '';
	
	if ( is_array($sidebar_articles) ) { 
		$i = 0;
		foreach($sidebar_articles as $article) { 
			$i++;
			if ($i > 1) { 
				$content.= '<img src="http://www.skollfoundation.org/wp-content/uploads/2010/10/div.gif" width="155" height="2">';
			}
			$article = get_post($article); 
			$content.= '<h2 style="font-size:13px"><a href="' . get_permalink($article->ID) . '" style="text-decoration:none;color:#2D2D25" target="_blank">' . $article->post_title .'</a></h2>
			 
			<p>' . ptn_shorten_string(strip_tags($article->post_content), 50) . '</p>
			
			<p><a href="' . get_permalink($article) . '" style="color:#D06D19" target="_blank">Read more</a></p>';
			
		}
	}
	return $content;
}



function ptn_replace_shortcodes($content) {
	$content = str_replace('[highlights_section]', ptn_render_highlights_section(), $content);
	$content = str_replace('[header_image]', ptn_render_header_image(), $content);
	$content = str_replace('[featured_post]', ptn_render_featured_post(), $content);
	$content = str_replace('[main_section]', ptn_render_main_section(), $content);
	$content = str_replace('[sidebar_section]', ptn_render_sidebar_section(), $content);
	$content = str_replace('[logo]', '<img src="' . get_option('ptn_logo_url') . '" width="600">', $content);

	return $content;
}





/* 
 * Settings page
 */
 
add_action('admin_menu', 'ptn_add_options_page');

function ptn_add_options_page() {
	add_options_page(PTN_PLUGIN_NAME . ' settings', PTN_PLUGIN_NAME, 'manage_options', 'ptn-settings', 'ptn_settings_page');
	add_submenu_page( 'edit.php?post_type=' . PTN_NEWSLETTER_POST_TYPE, 'Settings', 'Settings', 'manage_options', 'ptn-settings', 'ptn_settings_page' ); 	
}

function ptn_settings_page() {
	?>

	<script>
	(function($) {
		$(function() {
			$('#advanced-options-link').click(function(e) {
				e.preventDefault();
				$('#advanced-options').slideToggle();
			});		
		});
	})(jQuery);
	</script>

<?php	

	$sections = array(
		'ptn_highlights'=> 'Highlights Section',
		'ptn_featured_post'=> 'Featured Post',
		'ptn_main_articles'=> 'Main Section',
		'ptn_side_articles'=> 'Side Section',	
	);
	$other_options = array(
		'ptn_mailchimp_api_key'=> '',
		'ptn_number_latest_posts'=> '',
		'ptn_newsletter_template'=> '',
		'ptn_logo_url'=> '',
	);
	
	$all_options = array_merge(
		$sections,
		$other_options
	);
	
	if ( isset($_POST['ptn_submit']) ) { 
		// update all options
		foreach($all_options as $option_name => $option_title) {
			update_option($option_name, stripslashes($_POST[$option_name]));	
		} 
		?>
		<div id="message" class="updated fade">
		  <p><b>Settings saved.</b></p>
		</div>
		<?php	
	}

	$post_types = get_post_types(array('public' => true, '_builtin' => false));
	$post_types = array_merge(
		array('post' => 'post'), 
		$post_types, 
		array('dont_show'=> "-- Don't show --")
	); 
	
	?>
	<h2><?php echo PTN_PLUGIN_NAME; ?> settings</h2>
	<form name="ptn" method="post">
		<p>
			<b>MailChimp API Key</b> <br />
			<input type="text" name="ptn_mailchimp_api_key" value="<?php echo get_option('ptn_mailchimp_api_key'); ?>"><br />
			This allows you to create MailChimp campaigns and send emails directly from Wordpress. Get your MailChimp API key by logging into your MailChimp account and going to Account > API Keys.
		</p>

		<p>
			<b>Logo URL</b> <br />
			<input type="text" name="ptn_logo_url" value="<?php echo get_option('ptn_logo_url'); ?>">
		</p>
				
		<p>
			<b>Newsletter template</b> <br />
			<div style="width:800px">
			<?php 
			$template_val = get_option('ptn_newsletter_template');
			if ( function_exists('wp_editor') ) {
				$editor_settings = array(
					'wpautop' => false,
					'textarea_name'=> 'ptn_newsletter_template',
					'media_buttons'=> false,
					'textarea_rows'=> 20,
				);
				 
				wp_editor($template_val, 'ptn_newsletter_template', $editor_settings);
			} else {
				echo '<textarea cols="80" rows="20" name="ptn_newsletter_template">' . $template_val . '</textarea>';
				echo '<p style="color:green;">Note: Upgrade your version of Wordpress if you\'d like to use a WYSIWYG editor to edit your newsletter template.</p>';
			}			
			 ?>
			</div>
			<i>May contain the following short codes:</i> <br />
			 [highlights_section], [header_image], [featured_post], [main_section], [sidebar_section]
		</p>
		
		<a href="#" id="advanced-options-link">Advanced options &raquo;</a> <br /><br />
		<div id="advanced-options" style="display:none">
			<p>
				<b>Number of latest posts to pick from:</b> <br />
				<select name="ptn_number_latest_posts">
					<?php $number_posts_options = array(50, 100, 200, 'All'); 
					foreach ($number_posts_options as $option) : 
						$selected = ( $option==get_option('ptn_number_latest_posts') ? ' SELECTED="SELECTED"' : '' ); ?>
						<option value="<?php echo $option; ?>"<?php echo $selected; ?>><?php echo $option; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			
			<h3>Post Types:</h3>
			<?php
			foreach ($sections as $section_name => $section_title) : ?>
				<p><b><?php echo $section_title; ?>:</b> <br />
				<select name="<?php echo $section_name; ?>">
				<?php
				foreach ($post_types as $post_type_val => $post_type) :
					if ( $post_type==PTN_NEWSLETTER_POST_TYPE ) continue;
					
					$selected = ( $post_type_val==get_option($section_name) ? 'SELECTED="SELECTED"' : '' );
					echo '<option value="' . $post_type_val . '"' . $selected . '>' . ucwords($post_type) . '</option>';
				endforeach;
				?>
				</select></p>
	
			<?php endforeach; ?>	
		</div>
		<input type="submit" name="ptn_submit" value="Save">
	</form>
	<?php
}









/* 
 * MailChimp stuff 
 */

add_action( 'add_meta_boxes', 'ptn_add_mailchimp_box' );
add_action( 'save_post', 'ptn_save_postdata', 200 );

function ptn_add_mailchimp_box($post_type) {
    add_meta_box( 
        'myplugin_sectionid',
        __( 'MailChimp', 'myplugin_textdomain' ),
        ( get_option('ptn_mailchimp_api_key') != '' ? 'ptn_inner_mailchimp_box' : 'ptn_get_mailchimp_box' ),
        PTN_NEWSLETTER_POST_TYPE,
        'side',
        'high'
    );
}

function ptn_setup_mailchimp_api() {
  	$api_key = get_option('ptn_mailchimp_api_key');
	require_once 'MCAPI.class.php';
	$api = new MCAPI($api_key);
	return $api;
}

function ptn_inner_mailchimp_box( $post ) {  
    if ($post->post_status != 'publish') {
    	echo 'You must publish the post before you can create a MailChimp campaign';
    
    } elseif ( get_post_meta($post->ID, '_mailchimp_campaign_id', true)=='' || get_post_meta($post->ID, '_edit_mailchimp_campaign', true) ) {
		ptn_render_create_campaign_form();
		delete_post_meta($post->ID, '_edit_mailchimp_campaign');

    } elseif ( get_post_meta($post->ID, '_mailchimp_campaign_sent', true) ) {
		echo '<h2>Campaign sent!</h2>';
		ptn_render_mailchimp_campaign_details();
    
    } else {
		ptn_render_mailchimp_send_form();
	} 
}

function ptn_get_mailchimp_box() {
	echo '<p>Enter your MailChimp API key on the Settings page to be able to send newsletters directly from here.</p>';
	echo '<p>If you don\'t have one yet, sign up for a <a target="_blank" href="http://eepurl.com/pr9oj">free MailChimp account here</a>.</p>';
	echo '<p style="text-align:center"><a target="_blank" href="http://eepurl.com/pr9oj"><img src="' . plugins_url('mailchimp.gif', _FILE_) . '"></a></p>';
}

function ptn_render_create_campaign_form() {
	global $post;
	wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
	
	$api = ptn_setup_mailchimp_api();
	
	$retval = $api->lists();
	
	if ($api->errorCode){
		$msg = "Unable to load lists()!";
		$msg.= "\n\tCode=".$api->errorCode;
		$msg.= "\n\tMsg=".$api->errorMessage."\n";
		ptn_store_mailchimp_error($msg); 
	} else { 
		echo '<h2>Create campaign:</h2>';
		echo 'List: <br />';
		
		$mailchimp_list_id = get_post_meta($post->ID, '_mailchimp_list_id', true);
		echo '<select name="_mailchimp_list_id" id="mailchimp-list-id">';
			echo '<option value="">Select a List...</option>';
		foreach ($retval['data'] as $list){
			$selected = ( $list['id']==$mailchimp_list_id ? ' selected="selected"' : '');
			echo '<option value="' . $list['id'] . '"' . $selected . ' data-default-name="' . $list['default_from_name'] . '" data-default-email="' . $list['default_from_email'] . '">' . $list['name'] . '</option>';
		}
		echo '</select> <br /><br />';
		
		$subject = $post->post_title;
		$mailchimp_email = get_post_meta($post->ID, '_mailchimp_email', true);
		$mailchimp_name = get_post_meta($post->ID, '_mailchimp_name', true);
		$mailchimp_list_name = get_post_meta($post->ID, '_mailchimp_list_name', true);
		
		echo 'Email from: <br />';
		echo '<input type="text" name="_mailchimp_email" id="mailchimp-email" value="' . $mailchimp_email . '"> <br /><br />';
		echo 'Name from: <br />';
		echo '<input type="text" name="_mailchimp_name" id="mailchimp-name" value="' . $mailchimp_name . '"> <br /><br />';
		echo '<input type="hidden" name="_mailchimp_list_name" id="mailchimp-list-name" value="' . $mailchimp_list_name . '">';
		$updating = get_post_meta($post->ID, '_edit_mailchimp_campaign', true);
		if ( $updating ) {
			echo '<input type="submit" id="update-mailchimp-campaign" name="update_mailchimp_campaign" value="Update campaign">';
		} else {
			echo '<input type="submit" id="create-mailchimp-campaign" name="create_mailchimp_campaign" value="Create campaign">';
		}
		echo '<div style="float:right;margin-top:3px;font-style:italic;">*all fields required</div>';
	}
	?>
	
	<script>
	(function($) {
		$(function() {
			$('#create-mailchimp-campaign').click(function(e) {
				if ( $('#mailchimp-name').val()=='' || $('#mailchimp-email').val()=='' 
					|| $('#mailchimp-list-id').val()=='' ) {
					e.preventDefault();
					alert('Error: Missing fields. All fields are required.');
				}
			});
			
			$('#mailchimp-list-id').change(function() {
				var $selected = $(this).find('option:selected');
				$('#mailchimp-email').val($selected.attr('data-default-email'));
				$('#mailchimp-name').val($selected.attr('data-default-name'));
				$('#mailchimp-list-name').val($selected.text());
			});
		});
	})(jQuery);
	</script>
	
	<?	
}

function ptn_render_mailchimp_send_form() {
	global $post;
	wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
	
	echo '<div style="float:right"><input type="submit" name="edit_mailchimp_campaign" value="Edit"></div>';
	echo '<h2>Campaign created!</h2>';
	ptn_render_mailchimp_campaign_details();
	
	echo '<div style="background:#eee;padding:5px;border:1px dotted #ddd;"><h2 style="margin: 0;font-size: 16px;">Test Campaign</h2>';
	echo 'Email address(es): <br />';
	echo '<input type="text" name="mailchimp_test_email_addresses" id="mailchimp-test-email-addresses" value="">';
	echo '<p style="margin-top:0">(separated by commas)</p>';
	echo '<input type="submit" name="send_mailchimp_test" id="send-mailchimp-test" value="Send Test Email"></div>';
	echo '<div style="background:#eee;padding:5px;border:1px dotted #ddd;margin-top:15px;"><h2 style="margin: 10px 0 0;font-size: 16px;">Ready to send?</h2>';
	echo '<input type="submit" name="send_mailchimp_campaign" value="Send Campaign" style="background:green;color:white"></div>';
	?>
	
	<script>
	(function($) {
		$(function() {
			$('#send-mailchimp-test').click(function(e) {
				if ( $('#mailchimp-test-email-addresses').val()=='') {
					e.preventDefault();
					alert('Error: Please enter at least 1 email address.');
				}
			});
			
		});
	})(jQuery);
	</script>
	
	<?	
}

function ptn_render_mailchimp_campaign_details() {
	global $post;
	echo '<p><b>List: </b> ' . get_post_meta($post->ID, '_mailchimp_list_name', true) . '</p>';
	echo '<p><b>Email from: </b> ' . get_post_meta($post->ID, '_mailchimp_email', true) . '</p>';
	echo '<P><b>Name from: </b> ' . get_post_meta($post->ID, '_mailchimp_name', true) . '</p>';
}


function ptn_save_postdata( $post_id ) { 
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
	 	return;
	
	if ( !wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename( __FILE__ ) ) )
	 	return;
	
	if ( 'page' == $_POST['post_type'] ) {
		if ( !current_user_can( 'edit_page', $post_id ) ) {
	    	return;
	    }
	} else {
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}
	
	if ( isset($_POST['create_mailchimp_campaign']) || isset($_POST['update_mailchimp_campaign']) ) { 
		update_post_meta($post_id, '_mailchimp_list_id', $_POST['_mailchimp_list_id']);
		update_post_meta($post_id, '_mailchimp_list_name', $_POST['_mailchimp_list_name']);
		update_post_meta($post_id, '_mailchimp_email', $_POST['_mailchimp_email']);
		update_post_meta($post_id, '_mailchimp_name', $_POST['_mailchimp_name']);
		if ( $_POST['_mailchimp_list_id']=='' || $_POST['_mailchimp_email']=='' || $_POST['_mailchimp_name']=='' ) {
		 return;
		}
		if ( isset($_POST['update_mailchimp_campaign']) ) {
			ptn_update_mailchimp_campaign();
		} else {
			ptn_create_mailchimp_campaign();
		}	
	} elseif ( isset($_POST['send_mailchimp_test']) ) { 
		ptn_send_mailchimp_test_email();
	} elseif ( isset($_POST['send_mailchimp_campaign']) ) { 
		ptn_send_mailchimp_campaign();
	} elseif ( isset($_POST['edit_mailchimp_campaign']) ) { 
		update_post_meta($post_id, '_edit_mailchimp_campaign', 1);
	}

}

function ptn_create_mailchimp_campaign() {
	global $post;
	
	$type = 'regular';
	
	$opts['list_id'] = $_POST['_mailchimp_list_id'];
	$opts['subject'] = $post->post_title;
	$opts['from_email'] = $_POST['_mailchimp_email']; 
	$opts['from_name'] = $_POST['_mailchimp_name'];
	
	$opts['tracking']=array('opens' => true, 'html_clicks' => true, 'text_clicks' => false);
	
	$opts['title'] = $_POST['post_title'];
	$opts['generate_text'] = true;
	
	$content = array('url'=> get_permalink($post->ID)); 
		
	$api = ptn_setup_mailchimp_api();	
	$campaign_id = $api->campaignCreate($type, $opts, $content);
	
	if ($api->errorCode){
		$msg = "Unable to Create New Campaign!";
		$msg.= "\n\tCode=".$api->errorCode;
		$msg.= "\n\tMsg=".$api->errorMessage."\n";
		ptn_store_mailchimp_error($msg); 
	} else {
		add_post_meta($post->ID, '_mailchimp_campaign_id', $campaign_id);
	}
}

function ptn_update_mailchimp_campaign() {
	global $post;
	
	$campaign_id = get_post_meta($post->ID, '_mailchimp_campaign_id', true);
	
	$api = ptn_setup_mailchimp_api();
	$api->campaignUpdate($campaign_id, 'subject', $_POST['post_title']);
	$api->campaignUpdate($campaign_id, 'title', $_POST['post_title']);
	$api->campaignUpdate($campaign_id, 'list_id', get_post_meta($post->ID, '_mailchimp_list_id', true));
	$api->campaignUpdate($campaign_id, 'from_email', get_post_meta($post->ID, '_mailchimp_email', true));
	$api->campaignUpdate($campaign_id, 'from_name', get_post_meta($post->ID, '_mailchimp_name', true));
	
	if ($api->errorCode){
		$msg = "Unable to Update Campaign!";
		$msg.= "\n\tCode=".$api->errorCode;
		$msg.= "\n\tMsg=".$api->errorMessage."\n";
		ptn_store_mailchimp_error($msg); 
	}
}

function ptn_send_mailchimp_test_email() { 
	global $post; 
	
	$emails = explode(',', str_replace(' ', '', $_POST['mailchimp_test_email_addresses']));
	if ( empty($emails) )
		return;
		
	ptn_update_mailchimp_campaign();
	
	$api = ptn_setup_mailchimp_api();
	$campaign_id = get_post_meta($post->ID, '_mailchimp_campaign_id', true);
	$retval = $api->campaignSendTest($campaign_id, $emails);
	 
	if ($api->errorCode){
		$msg = "Unable to Send Test!";
		$msg.= "\n\tCode=".$api->errorCode;
		$msg.= "\n\tMsg=".$api->errorMessage."\n";
		ptn_store_mailchimp_error($msg); 
	}
}

function ptn_send_mailchimp_campaign() { 
	global $post; 

	ptn_update_mailchimp_campaign();
	$api = ptn_setup_mailchimp_api();
	$campaign_id = get_post_meta($post->ID, '_mailchimp_campaign_id', true);
	$retval = $api->campaignSendNow($campaign_id);
	 
	if ($api->errorCode){
		$msg = "Unable to Send Campaign!";
		$msg.= "\n\tCode=".$api->errorCode;
		$msg.= "\n\tMsg=".$api->errorMessage."\n";
		ptn_store_mailchimp_error($msg); 
	} else {
		add_post_meta($post->ID, '_mailchimp_campaign_sent', 1);
	}
}



function ptn_store_mailchimp_error($msg) {
	set_transient('ptn_mailchimp_error_msg', $msg);
}

add_action('admin_head', 'ptn_mailchimp_error_action');

function ptn_mailchimp_error_action() {
	$error = get_transient('ptn_mailchimp_error_msg');
	if ( $error ) {
		add_action('admin_notices','ptn_display_mailchimp_error');
	}
}

function ptn_display_mailchimp_error() {
	echo '<div class="error"><p>' . get_transient('ptn_mailchimp_error_msg') . '</p></div>';	
	delete_transient('ptn_mailchimp_error_msg');
}

?>