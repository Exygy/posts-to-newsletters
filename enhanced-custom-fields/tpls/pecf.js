jQuery(function ($) {
	if ($('form#post input[type=file]').length) {
		$('form#post').attr('enctype', 'multipart/form-data');
	}
	
	$('.clone-ecf').live('click', function () {
	    var src_row = $(this).parents('tr')
	    var new_row = src_row.clone();
	    new_row.find('td:first').html('');
	    new_row.find('td:last').html('');
	    var field = new_row.find('input, textarea, select').eq(0);// .each(function () {
	    var related_fields = $('*[rel=' + field.attr('rel') + ']');
	    var new_id = field.attr('id') + '-' + related_fields.length;
	    field.attr('id', new_id);
	    field.val('');
		new_row.insertAfter(related_fields.eq(related_fields.length - 1).parents('tr:eq(0)'));
		$('p.ecf-description[rel=' + src_row.find('.ecf-description').attr('rel') + ']:not(:last)').hide();
		new_row.find('.ecf-description').show();
		field.focus();
		return false;
	});
	
	$('.delete-ecf').click(function () {
		var container = $(this).parents('tr:eq(0)');
	    var field = container.find('input, textarea, select').not('[type=hidden]');
	    field.remove();
	    container.hide();
	    return false;
	});
});