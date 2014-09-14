/*
 * Render the data-description attribute of each definition term as a jQuery-UI tooltip.
 */
$("dt").each(function() {
	$(this).tooltip({
		content: $(this).data('description'),
		items: '*'
	});
});
