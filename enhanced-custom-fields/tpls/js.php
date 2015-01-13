<?php
global $post;
?>
<script type="text/javascript" charset="utf-8">
(function ($) {
	<?php if (isset($this->parent_page)): ?>
	    $('#parent_id').change(function () {
			var cont = $('#<?php echo $this->id ?>');
	        if ($(this).val()==<?php echo $this->parent_page->ID ?>) {
	        	cont.slideDown();
	        } else {
	        	cont.slideUp();
	        }
	    }).change();
	<?php endif ?>
	<?php if (isset($this->page)): ?>
		var cont = $('#<?php echo $this->id ?>');
		<?php if ($this->page->ID == $post->ID) : ?>
	        cont.slideDown();
	    <?php else : ?>
        	cont.slideUp();
	    <?php endif; ?>
	<?php endif ?>
	<?php if (isset($this->cat)): ?>
		$('#categorychecklist input, #categories-pop input').change(function() {
			var cont = $('#<?php echo $this->id ?>');
	        if ($(this).val()==<?php echo $this->cat->term_id ?> && $(this).is(':checked')) {
	        	cont.slideDown();
	        } else if($(this).val()==<?php echo $this->cat->term_id ?>) {
	        	cont.slideUp();
	        }
		});
		if (!$('#categorychecklist input[value=<?php echo $this->cat->term_id ?>], #categories-pop input[value=<?php echo $this->cat->term_id ?>]').is(':checked')) {
			$('#<?php echo $this->id ?>').hide();
		};
	<?php endif ?>
	<?php if (!empty($this->template_names)): ?>
	    $('#page_template').change(function () {
			var cont = $('#<?php echo $this->id ?>');
	        if ($(this).val()=="<?php echo implode('" || $(this).val()=="', $this->template_names) ?>") {
	        	cont.slideDown();
	        } else {
	        	cont.slideUp();
	        }
	    }).change();
	<?php endif ?>
})(jQuery);
</script>