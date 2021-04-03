(function($){

	$(function() {
		moment.locale(xe.current_lang);

		$('.date_range').each(function() {
			var oid = $(this).attr('id').replace('date_range_', '');
			var start_date = current_url.getQuery('extra_vars'+oid),
				end_date = current_url.getQuery('extra_vars'+oid+'-2');
			if ( start_date && end_date )
			{
				var start_year = start_date.substring(0, 4), start_month = start_date.substring(4, 6), start_day = start_date.substring(6, 8);
				var end_year = end_date.substring(0, 4), end_month = end_date.substring(4, 6), end_day = end_date.substring(6, 8);
				$('#extra_vars'+oid).val(moment(start_date).format('YYYY-MM-DD') + ' ~ ' + moment(end_date).format('YYYY-MM-DD'));
				$('#extra_vars'+oid).daterangepicker({
					minDate: moment().subtract(100, 'year'),
					maxDate: moment(),
					startDate: moment(start_date, 'YYYYMMDD'),
					endDate: moment(end_date, 'YYYYMMDD'),
					autoUpdateInput: false,
					locale: {
						cancelLabel: 'Clear'
					},
					showDropdowns: true
				});
			}
			else
			{
				$('#extra_vars'+oid).val('');
				$('#extra_vars'+oid).daterangepicker({
					minDate: moment().subtract(100, 'year'),
					maxDate: moment(),
					autoUpdateInput: false,
					locale: {
						cancelLabel: 'Clear'
					},
					showDropdowns: true
				});
			}
			$('#extra_vars'+oid).on('apply.daterangepicker', function(e, picker) {
				$(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
				$('input[name="extra_vars'+oid+'"]').val(picker.startDate.format('YYYYMMDD'));
				$('input[name="extra_vars'+oid+'-2"]').val(picker.endDate.format('YYYYMMDD'));
			});
			$('#extra_vars'+oid).on('cancel.daterangepicker', function(e, picker) {
				$(this).val('');
				$('input[name="extra_vars'+oid+'"]').val('');
				$('input[name="extra_vars'+oid+'-2"]').val('');
			});
		});
		
		$('.date_range').children('input:button').on('click', function() {
			$(this).siblings('input').val('');
		});

		if ( xe.current_lang === 'ko' )
		{
			$('.daterangepicker').children('.drp-buttons').children('button:even').text('취소');
			$('.daterangepicker').children('.drp-buttons').children('button:odd').text('적용');
		}
	});

})(jQuery);