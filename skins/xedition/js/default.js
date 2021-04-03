jQuery(document).ready(function($) {
	var S = $('.ap_extra_search');

	S.find('.regist button:eq(1)').on('click', function() {
		S.find('input:text').removeAttr('value');
		S.find('select').each(function() {
			$(this).removeAttr('selected');
			$(this).find('option:first').attr('selected', 'true');
		});
		S.find('input:radio:checked').removeAttr('checked');
		S.find('input:checkbox:checked').removeAttr('checked');
		S.find('input.date').siblings('input:hidden').val('');
		if ( S.find('.data_range').length )
		{
			S.find('.data_range').each(function() {
				var min = $(this).data('min'),
					max = $(this).data('max');
				$(this).find('input:hidden').val('');
				$(this).find('div').eq(1).slider(
					'values', [Math.round((max-min)/3)+min, Math.round((max-min)*2/3)+min]
				);
				$(this).find('span').eq(0).text($(this).find('div').eq(1).slider('values', 0));
				$(this).find('span').eq(1).text($(this).find('div').eq(1).slider('values', 1));
			});
		}
	});

	S.find('input.date').attr('readonly', 'true').on('input', function() {
		$(this).prev('input').val($(this).val().replace(/[^0-9]/g, ''));
	});

	S.on('submit', function() {
		var check = false;
		if ( S.find('input[name="search_keyword"]').val() || S.find('input[name="search_signature"]').val() )
		{
			check = true;
		}
		S.find('[name*="extra_vars"]').each(function() {
			var type = $(this).attr('type');
			if ( type === 'radio' || type === 'checkbox' )
			{
				if ( $(this).attr('checked') )
				{
					check = true;
				}
			}
			else
			{
				if ( $(this).val() )
				{
					check = true;
				}
			}
		});
		if ( check === false )
		{
			alert(msg_reset);
			location.href = request_uri + current_mid;
			return false;
		}
	});
});