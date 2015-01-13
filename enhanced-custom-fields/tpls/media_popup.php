<script type="text/javascript">
(function($){
	$('#attachment_<?php echo $this->post->ID; ?>').live('click', function() {
		window.parent.retrieve_attachment_id($(this).attr('data-field-name'), <?php echo $this->post->ID; ?>, "<?php echo wp_get_attachment_url($this->post->ID); ?>");
		window.parent.tb_remove()
		return false;
	});
})(jQuery)
</script>