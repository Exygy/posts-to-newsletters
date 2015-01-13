<?php
$image = '';
if ($this->value && get_post($this->value)) {
	$image = wp_get_attachment_url($this->value); 
}
?>
<img id="<?php echo $this->name; ?>_image" src="<?php echo $image; ?>" alt="" width="150" style="<?php echo (!$image) ? 'display: none;' : ''; ?>" />
<div class="cl" style="height: 10px;">&nbsp;</div>
<a href="media-upload.php?post_id=<?php echo $this->post_id; ?>&amp;type=image&amp;TB_iframe=1&amp;width=640&amp;height=467" class="button ecf-pick-media thickbox">Select Image</a>
&nbsp;
<a href="#" class="button ecf-clear-media thickbox" data-field-name="<?php echo $this->name; ?>" style="<?php echo (!$image) ? 'display: none;' : ''; ?>">Clear Image</a>
<div class="cl" style="height: 10px;">&nbsp;</div>

<script type="text/javascript">
function retrieve_attachment_id(field_name, attachment_id, attachment_url) {
	(function($){
		$('input[name="' + field_name + '"]').val(attachment_id);
		$('#' + field_name + '_image').attr('src', attachment_url).show();
		$('.ecf-clear-media[data-field-name="' + field_name + '"]').show();
		
		$('input[name="update_newsletter"]').val('1');

	})(jQuery)
}

(function($){
	$('.ecf-clear-media').live('click', function() {
		$('input[name="' + $(this).attr('data-field-name') + '"]').val(0);
		$('#' + $(this).attr('data-field-name') + '_image').hide();
		$(this).hide();
		return false;
	});
})(jQuery)
</script>