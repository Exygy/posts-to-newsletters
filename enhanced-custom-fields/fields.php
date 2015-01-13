<?php
class PECF_Field {
	var $type;	
	var $default_value;
	var $value, $values = array();
	
	var $post_id;
	
	var $id;
	
	var $is_subfield = false;
		
	// whether this custom field can have more than one value
	var $is_multiply = false;
	
	
	var $labels = array(
		'add_field'=>'Add field ...',
		'delete_field'=>'Delete field ...',
	);
	
	function factory($type, $name, $label=null) {
		$type = str_replace(" ", '', ucwords(str_replace("_", ' ', $type)));
		
		$class = "PECF_Field$type";
		
		if (!class_exists($class)) {
			pecf_conf_error("Cannot add meta field $type -- unknow type. ");
		}
		
		// Try to guess field label from it's name
		if (is_null($label)) {
			// remove the leading underscore(if it's there)
			$label = preg_replace('~^_~', '', $name);
			// split the name into words and make them capitalized
			$label = ucwords(str_replace('_', ' ', $label));
		}
		
		if (substr($name, 0, 1)!='_') {
			// add underscore to custom field name -- this will remove it from 
			// custom fields list in administration
			$name = "_$name";
		}
		$field = new $class($name, $label);
		$field->type = $type;
	    return $field;
	}
	
	function PECF_Field($name, $label) {
	    $this->name = $name;
	    $this->label = $label;
	    
	    $random_string = md5(mt_rand() . $this->name . $this->label);
	    $random_string = substr($random_string, 0, 5); // 5 chars should be enough
	    $this->id = 'pecf-'. $random_string;
	    
	    $this->init();
	}
	function load() {
		if (empty($this->post_id)) {
			pecf_conf_error("Cannot load -- unknow POST ID");
		}
		$single = true;
		if ($this->is_multiply) {
			$single = false;
		}
		$value = get_post_meta($this->post_id, $this->name, $single);
	    $this->set_value($value);
	}
	// abstract method
	function init() {}
	
	function multiply() {
		$this->is_multiply = true;
	    return $this;
	}
	function setup_labels($labels) {
	    $this->labels = array_merge($this->labels, $labels);
	    return $this;
	}
	function set_value($value) {
		if ($this->is_multiply) {
			$this->values = $value;
			$this->value = '';
		} else {
			$this->value = $value;
		}
	    
	}
	function set_default_value($default_value) {
	    $this->default_value = $default_value;
	    return $this;
	}
	
	function help_text($help_text) {
		$this->help_text = $help_text;
		return $this;
	}

	function render_row($field_html) {
		$help_text = isset($this->help_text) ? '<p class="pecf-description" rel="' . $this->id . '">' . $this->help_text . '</p>' : '' ;

		$field_has_options = $this->is_multiply || $this->is_subfield;

		$html = '
		<tr class="pecf-field-container"> 
			<td class="pecf-label"><label for="' . $this->id . '">' . $this->label . '</label></td>
			<td ' . ($field_has_options ? '' : 'colspan="2"') . '>' . $field_html . $help_text . '</td>
		';
		
		if ($this->is_multiply) {
			$html .= '<td class="pecf-action-cell"><a href="#" class="clone-pecf pecf-action">' . $this->labels['add_field'] . '</a></td>';
		} else if ($this->is_subfield) {
			$html .= '<td class="pecf-action-cell"><a href="#" class="delete-pecf pecf-action">' . $this->labels['delete_field'] . '</a>';
			$html .= '<input type="hidden" name="' . $this->name . '_original_vals[' . $this->id . ']" value="' . esc_attr($this->value) . '" />';
			$html .= '</td>';
		}
		$html .= '</tr>';
		return $html;
	}
	
	function set_value_from_input() {
		if (!isset($_POST[$this->name])) { return; }
		$value = $_POST[$this->name];
	    $this->set_value($value);	
	}

	function save() {
		if ($this->is_multiply) {
			foreach ($this->values as $val) {
				if ($val) {
					add_post_meta($this->post_id, $this->name, $val);
				}
			}
			if (isset($_POST[$this->name . "_original_vals"])) {
				foreach ($_POST[$this->name . "_original_vals"] as $key => $original_value) {
					// deleting value actually removes the field from the form
					if (!isset($_POST[$this->name . "_updated_vals"][$key])) {
						delete_post_meta($this->post_id, $this->name, $original_value);
						continue;
					}
					$updated_value = $_POST[$this->name . "_updated_vals"][$key];
					
					// empty value removes the field
					if (empty($updated_value)) {
						delete_post_meta($this->post_id, $this->name, $original_value);
					}
					if ($original_value!=$updated_value) {
						update_post_meta($this->post_id, $this->name, $updated_value, $original_value);
					}
				}
			}
		} else {
			update_post_meta($this->post_id, $this->name, $this->value);
		}
	    
	}

	// abstract method
	function render() {}
	
	function build_html_atts($tag_atts) {
	    $default = array(
	    	'class'=>'pecf-field pecf-' . strtolower(get_class($this)),
	    	'id'=>$this->id,
	    	'rel'=>$this->id,
	    );
	    
	    if (isset($tag_atts['class'])) {
	    	$tag_atts['class'] .= ' ' . $default['class'];
	    }
	    
	    if ($this->is_multiply) {
	    	$tag_atts['name'] .= '[]';
	    } else if ($this->is_subfield) {
	    	$tag_atts['name'] .= '_updated_vals[' . $this->id . ']';
	    }
	    
	    return array_merge($default, $tag_atts);
	}

	// Builds HTML for tag. 
	// example usage:
	// echo $this->build_tag('strong', array('class'=>'red'), 'I'm bold and red');
	// ==> <strong class="red">I'm bold and red</strong>
	function build_tag($tag, $atts, $content=null) {
	    $atts_text = '';
	    foreach ($atts as $key=>$value) {
	    	$atts_text .= ' ' . $key . '="' . esc_attr($value) . '"';
	    }
	    
	    $return = '<' . $tag . $atts_text;
	    if (!is_null($content)) {
	    	$return .= '>' . $content . '</' . $tag . '>';
	    } else {
	    	$return .= ' />';
	    }
	    return $return;
	}

	function render_field() {
		$return = '';
		if ($this->is_multiply) {
			foreach ($this->values as $val) {
				// create new field object.
				$field = PECF_Field::factory($this->type, $this->name, $this->label);
				$field->post_id = $this->post_id;
				$field->value = $val;
				$field->is_subfield = true;
				$return .= $field->render();
			}
		} 
		$return .= $this->render();
		return $return;
	}
}
class PECF_FieldText extends PECF_Field {
	function render() {
		
		$input_atts = $this->build_html_atts(array(
			'type'=>'text',
			'name'=>$this->name,
			'value'=>$this->value,
		));
		$field_html = $this->build_tag('input', $input_atts);
		
	    return $this->render_row($field_html);
	}
}


// ADDED BY JC
class PECF_FieldCheckbox extends PECF_Field {
	function render() {
		
		if ($this->value != 'FALSE') {
		$input_atts = $this->build_html_atts(array(
				'type'=>'checkbox',
				'name'=>$this->name,
				'value'=>"TRUE",
				'checked'=>'CHECKED'
			));
		} else {
			$input_atts = $this->build_html_atts(array(
				'type'=>'checkbox',
				'name'=>$this->name,
				'value'=>"TRUE",
			));
		}		
		$field_html = $this->build_tag('input', $input_atts);

		$input1 = $this->render_row($field_html);
		
		// hidden field 
		$hidden = '<input type="hidden" name="' . $this->name . '" value="FALSE">';
				
		
	    return $hidden . $input1;
	}
}


class PECF_FieldTextarea extends PECF_Field {
	function render() {
		$textarea_atts = $this->build_html_atts(array(
			'name'=>$this->name,
		));
		$val = $this->value ? $this->value : '';
		$field_html = $this->build_tag('textarea', $textarea_atts, $val);
		
	    return $this->render_row($field_html);
	}
}
class PECF_FieldSelect extends PECF_Field {

	var $options = array();
	function add_options($options) {
	    $this->options = $options;
	    return $this;
	}
    function render() {
    	if (empty($this->options)) {
    		pecf_conf_error("Add some options to $this->name");
    	}
		$options = '';
		foreach ($this->options as $key=>$value) {
			$options_atts = array('value'=>$key);
			if ($this->value==$key) {
				$options_atts['selected'] = "selected";
			}
			$options .= $this->build_tag('option', $options_atts, $value);
		}
		$select_atts = $this->build_html_atts(array(
			'name'=>$this->name,
		));
		$select_html = $this->build_tag('select', $select_atts, $options);
		
	    return $this->render_row($select_html);
	}
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}

// added by JC
class PECF_FieldSelectMulti extends PECF_FieldSelect {
	
    function render() {
    	if (empty($this->options)) {
    		pecf_conf_error("Add some options to $this->name");
    	}
		$options = '';
		foreach ($this->options as $key=>$value) {

			$options_atts = array('value'=>$key);
			if (is_array($this->value)) {
				if (in_array($key, $this->value)) {
					$options_atts['selected'] = "selected";
				}
			}
			$options .= $this->build_tag('option', $options_atts, $value);
		}
		$select_atts = $this->build_html_atts(array(
			'name'=>$this->name . "[]",
			'multiple'=>'multiple',
			'class'=>'multiselect',
		));
		$select_html = $this->build_tag('select', $select_atts, $options);
		
	    return $this->render_row($select_html);
	}
	
	function build_html_atts($tag_atts) {
	    $default = array(
	    	'class'=>'',
	    	'id'=>$this->id,
	    	'rel'=>$this->id,
	    );
	    
	    if (isset($tag_atts['class'])) {
	    	$tag_atts['class'] .= '' . $default['class'];
	    }
	    
	    if ($this->is_multiply) {
	    	$tag_atts['name'] .= '[]';
	    } else if ($this->is_subfield) {
	    	$tag_atts['name'] .= '_updated_vals[' . $this->id . ']';
	    }
	    
	    return array_merge($default, $tag_atts);
	}
		
/*
	function save() {
echo var_dump($_POST);
		$this->value = "";
		foreach ($_POST[$this->name . "_updated_vals"] as $key => $val) {
			$this->value .= $val . ",";
		}

echo $this->value."<BR>";	
		update_post_meta($this->post_id, $this->name, $this->value);
	    
	}
	*/		
}

class PECF_FieldFile extends PECF_Field {
	function render() {
	    $atts = $this->build_html_atts(array(
		    'type'=>'file',
		    'name'=>$this->name,
	    ));
	    
	    $input_html = $this->build_tag('input', $atts);
	    if ( !empty($this->value) ) {
	    	$input_html .= $this->get_file_description();
	    }
	    
	    return $this->render_row($input_html);
	}
	
	function get_file_description() {
	    return '<a href="' . get_option('home') . '/wp-content/uploads/' . $this->value . '" alt="" class="pecf-view_file" target="_blank">View File</a>';
	}
	function set_value_from_input() {
		if ( empty($_FILES[$this->name]) || $_FILES[$this->name]['error'] != 0) {
			return;
		}
		
		// Build destination path
		$upload_path = get_option( 'upload_path' );
		$upload_path = trim($upload_path);
		if ( empty($upload_path) || realpath($upload_path) == false ) {
			$upload_path = WP_CONTENT_DIR . '/uploads';
		}
		
		$file_ext = array_pop(explode('.', $_FILES[$this->name]['name']));
		
		// Build file name (+path)
		$file_path = $this->name . '/' . $this->post_id . '-' . time() . '.' . $file_ext;
		
		$file_dest = $upload_path . DIRECTORY_SEPARATOR . $file_path;
		if ( !file_exists( dirname($file_dest) ) ) {
			mkdir( dirname($file_dest) );
		}
		
		if ( !empty($this->value) && $this->value != $file_path) {
			if ( file_exists($upload_path . DIRECTORY_SEPARATOR . $this->value) ) {
				unlink($upload_path . DIRECTORY_SEPARATOR . $this->value);
			}
		}
		
		// Move file
		if ( move_uploaded_file($_FILES[$this->name]['tmp_name'], $file_dest) != FALSE ) {
	    	$this->set_value($file_path);
		}
	}
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}

class PECF_FieldImage extends PECF_FieldFile {
	var $width, $height;
	
	function set_size($width, $height) {
	    $this->width = intval($width);
	    $this->height = intval($height);
	    return $this;
	}
	function get_file_description() { 
		$image = $this->value;
		return '<img src="' . ( strstr($image, 'http://') ? $image : get_upload_url() . '/' . $image ) . '" alt="" height="100" class="pecf-view_image"/>';	
	    //return '<img src="' . get_option('home') . '/wp-content/uploads/' . $this->value . '" alt="" height="100" class="pecf-view_image"/>';
	}
	function set_value_from_input() {
		if ( empty($_FILES[$this->name]) || $_FILES[$this->name]['error'] != 0) {
			return;
		}
		
		// Build destination path
		$upload_path = get_option( 'upload_path' ); 
		$upload_path = trim($upload_path);
		if ( empty($upload_path) || realpath($upload_path) == false ) {
			$upload_path = WP_CONTENT_DIR . '/uploads';
		}
		
		$file_ext = array_pop(explode('.', $_FILES[$this->name]['name']));
		
		// Build image name (+path)
		$image_path = $this->name . '/' . $this->post_id . '-' . time() . '.' . $file_ext;
		
		$file_dest = $upload_path . DIRECTORY_SEPARATOR . $image_path;
		if ( !file_exists( dirname($file_dest) ) ) {
			mkdir( dirname($file_dest) );
		}
		
		if ( !empty($this->value) && $this->value != $image_path) {
			if ( file_exists($upload_path . DIRECTORY_SEPARATOR . $this->value) ) {
				unlink($upload_path . DIRECTORY_SEPARATOR . $this->value);
			}
		}
		
		// Move file
		if ( move_uploaded_file($_FILES[$this->name]['tmp_name'], $file_dest) != FALSE ) {
	    	$this->set_value($image_path);
	    	
			// Resize if width and height are set
			if ( !($this->width == null && $this->height == null)) {
				$resized = image_resize($file_dest , $this->width, $this->height, true, 'tmp');
				// Check if image was resized
				if ( is_string($resized) ) {
					if ( file_exists($file_dest)) {
						unlink($file_dest);
					}
					rename($resized, $file_dest);
				}
			}
		}
	}
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}


class PECF_FieldSeparator extends PECF_Field {
	function render() {
		$field_html = '';
	    return $this->render_row($field_html);
	}
	function render_row($field_html) {
	    return '
		<tr class="pecf-field-container">
			<td class="pecf-label">&nbsp;</td>
			<td>' . (( !empty($this->label) ) ? '<strong>' . $this->label . '</strong>' : '') . '&nbsp;</td>
		</tr>
		';
	}
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}

class PECF_FieldMap extends PECF_Field {
	var $lat = 0, $long = 0, $zoom = 1, $api_key;
	function init() {
		$this->help_text = 'Double click on the map and marker will appear. Drag &amp; Drop the marker to new position on the map.';
		PECF_Field::init();
	}
	function render() {
		if (empty($this->api_key)) {
			$this->help_text = '';
			return $this->render_row('<em>Please setup Google Maps API key in order to use this field</em>');
		}
		ob_start();
		include_once('tpls/pecf_fieldmap.php');
	    return $this->render_row(ob_get_clean());
	}
	function set_api_key($_key) {
		$this->api_key = $_key;
		return $this;
	}
	function set_position($lat, $long, $zoom) {
		$this->lat = $lat;
		$this->long = $long;
		$this->zoom = $zoom;
		
		return $this;
	}
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'new-files.php');

class PECF_FieldDate extends PECF_Field {
	function init() {
		if (defined('WP_ADMIN') && WP_ADMIN) {
			wp_enqueue_script('jqueryui-datepicker', get_bloginfo('stylesheet_directory') . '/lib/enhanced-custom-fields/tpls/jqueryui/jquery-ui-1.7.3.custom.min.js');
			wp_enqueue_style('jqueryui-datepicker', get_bloginfo('stylesheet_directory') . '/lib/enhanced-custom-fields/tpls/jqueryui/ui-lightness/jquery-ui-1.7.3.custom.css');
			wp_enqueue_script('jqueryui-initiate', get_bloginfo('stylesheet_directory') . '/lib/enhanced-custom-fields/tpls/jqueryui/initiate.js');
		}
		PECF_Field::init();
	}
	function render() {
		$input_atts = $this->build_html_atts(array(
			'type'=>'text',
			'name'=>$this->name,
			'value'=>$this->value,
			'class'=>'datepicker-me',
		));
		$field_html = $this->build_tag('input', $input_atts);
		
	    return $this->render_row($field_html);
	}
}
class PECF_FieldChooseSidebar extends PECF_FieldSelect {
	// Whether to allow the user to add new sidebars
	var $allow_adding = true;
	var $sidebar_options = array(
	    'before_widget' => '<li id="%1$s" class="widget %2$s">',
	    'after_widget' => '</li>',
	    'before_title' => '<h2 class="widgettitle">',
	    'after_title' => '</h2>',	
	);
	
	function init() {
		
		add_action('init', array($this, 'setup_sidebars'));
		$sidebars = $this->_get_sidebars();
		$options = array();
		
		foreach ($sidebars as $sidebar) {
			$options[$sidebar] = $sidebar;
		}
		
		$this->add_options($options);
		add_action('admin_footer', array($this, '_print_js'));

	    PECF_FieldSelect::init();
	}
	function disallow_adding_new() {
	    $this->allow_adding = false;
	    return $this;
	}
	function set_sidebar_options($sidebar_options) {
		// Make sure that all needed fields are in the options array
		foreach ($this->sidebar_options as $key => $value) {
			if (!isset($sidebar_options[$key])) {
				pecf_conf_error("Provide all sidebar options for $this->name PECF: <code>" . 
					implode(', ', array_keys($this->sidebar_options)) . "</code>");
			}
		}
	    $this->sidebar_options = $sidebar_options;
	    return $this;
	}
	function render() {
	    if ($this->allow_adding) {
			$this->options['new'] = "Add New";
		}
		return PECF_FieldSelect::render();
	}
	function setup_sidebars() {
		$sidebars = $this->_get_sidebars();
		foreach ($sidebars as $sidebar) {
			$associated_pages = get_posts('post_type=page&meta_key=' . $this->name . '&meta_value=' . urlencode($sidebar));
			if (count($associated_pages)) {
				$show_pages = 5;
				$assoicated_pages_titles = array();
				$i = 0;
				foreach ($associated_pages as $associated_page) {
					$assoicated_pages_titles[] = apply_filters('the_title', $associated_page->post_title);
					if ($i==$show_pages) {
						break;
					}
					$i++;
				}
				$msg = 'This sidebar is used on ' . implode(', ', $assoicated_pages_titles) . ' ';
				if (count($associated_pages) > $show_pages) {
					$msg .= ' and ' . count($associated_pages) - $show_pages . ' more pages';
				}
			} else {
				$msg = '';
			}
			
			$slug = strtolower(preg_replace('~-{2,}~', '', preg_replace('~[^\w]~', '-', $sidebar)));
			
			register_sidebar(array(
				'name'=>$sidebar,
				'id'=>$slug,
				'description'=>$msg,
			    'before_widget' => $this->sidebar_options['before_widget'],
			    'after_widget' => $this->sidebar_options['before_widget'],
			    'before_title' => $this->sidebar_options['before_title'],
			    'after_title' => $this->sidebar_options['after_title'],
			));
		}
	}
	function _print_js() {
	    include_once(dirname(__FILE__) . '/tpls/pecf_choose-sidebar-js.php');
	}
	function _get_sidebars() {
		global $wp_registered_sidebars;
	    $pages_with_sidebars = get_pages("meta_key=$this->name&hierarchical=0");
		$sidebars = array();
		foreach ($wp_registered_sidebars as $sidebar) {
			$sidebars[$sidebar['name']] = 1;
		}
		foreach ($pages_with_sidebars as $page_with_sidebar) {
			$sidebar = get_post_meta($page_with_sidebar->ID, $this->name, 1);
			if ($sidebar) {
				$sidebars[$sidebar] = 1;
			}
		}
		
		$sidebars = array_keys($sidebars);
		
		return $sidebars;
	}
}

class PECF_FieldSet extends PECF_Field {
	var $options = array();
	function add_options($options) {
	    $this->options = $options;
	    return $this;
	}
    function render() {
    	if (!is_array($this->value)) {
    		$this->value = array();
    	}
    	if (empty($this->options)) {
    		pecf_conf_error("Add some options to $this->name");
    	}
		$options = '';
		foreach ($this->options as $key=>$value) {
			$options_atts = array(
				'type'=>'checkbox',
				'name'=>$this->name . '[]',
				'value'=>$key,
				'style'=>'margin-right: 5px;',
			);
			if (in_array($key, $this->value)) {
				$options_atts['checked'] = "checked";
			}
			$options_atts = $this->build_html_atts($options_atts);
			$options .= $this->build_tag('input', $options_atts, $value) . '<br />';
		}
		
	    return $this->render_row('<div style="padding: 5px 0px;">' . $options . '</div>');
	}
	
	function save() {
		if (isset($_POST[$this->name])) {
			update_post_meta($this->post_id, $this->name, $_POST[$this->name]);
		} else {
			update_post_meta($this->post_id, $this->name, array());
		}
	}
	
	function multiply() {
	    pecf_conf_error(get_class($this) . " cannot be multiply");
	}
}



/* begin custom fields - ah */

class PECF_FieldSubmit extends PECF_Field {
	function render() {
		
		$input_atts = $this->build_html_atts(array(
			'type'=>'button',
			'name'=>$this->name,
			'value'=>'upload',
			'id'=>'post-image-1'
		));
		$field_html = $this->build_tag('button', $input_atts);
		
	    return $this->render_row($field_html);
	}
}
class PECF_FieldTextTB extends PECF_Field {
	function render() {
		
		$input_atts = $this->build_html_atts(array(
			'type'=>'text',
			'name'=>'testid',
			'value'=>$this->value,
			'id'=>'testid',
		));
		$field_html = $this->build_tag('input', $input_atts);
		
	    return $this->render_row($field_html);
	}
}

class PECF_FieldMedia extends PECF_Field {
	public $popup_button_label = false;
	public $popup_row_label = 'Use as';
	public $post_type = '';

	function init() {
		$url = preg_replace('~\?.*~', '', $_SERVER['REQUEST_URI']);
		if (preg_match('~(media-upload|async-upload)\.php$~', $url)) {
			add_filter('attachment_fields_to_edit', array($this, 'render_button'), 1, 2);
		}
	}

	function set_labels($button_label, $row_label = false) {
		if ($row_label) {
			$this->popup_row_label = $row_label;
		}
		$this->popup_button_label = $button_label;
		return $this;
	}
	
	function set_post_type($post_type) {
		$this->post_type = $post_type;
		return $this;
	}
	

	function render_button($current_fields, $post) {
		global $_GET; $parent_post = get_post($_GET['post_id']);
		
		$this->post = $post; 
		
		if ($parent_post->post_type == $this->post_type) {	
			$html = "<input type='button' class='button pecf-attachment-button' id='attachment_" . $this->post->ID . "' value='" . $this->popup_button_label . "' data-field-name='" . $this->name . "' />";
		}
		
		ob_start();
		include('tpls/media_popup.php');
		$script = ob_get_clean();

		if (!isset($current_fields['use_as'])) {
			$new_fields = array(
				'use_as' => array(
					'label' => $this->popup_row_label,
		            'input' => 'html',
		            'html' => $script . $html,
		            'value' => '',
				),
			);
			return array_merge($new_fields, $current_fields);
		} else {
			$current_fields['use_as']['html'] .= '&nbsp;' . $html;
		}
		
		return $current_fields;
	}
	
	function render() { 

		if ($this->popup_row_label === false) {
			pecf_conf_error("Media field's labels have not been setup.");
		}
		$input_atts = $this->build_html_atts(array(
			'type'=>'hidden',
			'name'=>$this->name,
			'value'=>$this->value,
		));
		$field_html = $this->build_tag('input', $input_atts);

		ob_start();
		include('tpls/media.php');
		$field_html .= ob_get_clean();
		
	    return $this->render_row($field_html);
	}
}

?>