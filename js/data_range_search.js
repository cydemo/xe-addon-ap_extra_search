(function($){
	$(function() {
		$('.data_range').each(function() {
			var oid = $(this).attr('id').replace('data_range_', '');
			var start_input = $('input[name="extra_vars'+oid+'"]'),
				end_input = $('input[name="extra_vars'+oid+'-2"]');
			var start_val = start_input.val().replace(/[^0-9]/g, ''),
				end_val = end_input.val().replace(/[^0-9]/g, '');
			var min = $(this).data('min'),
				max = $(this).data('max');
			var bar = $('#slider_range_'+oid);
			var start_span = $('span#extra_vars'+oid),
				end_span = $('span#extra_vars'+oid+'-2');

			bar.slider({
				range: true,
				step: ap_extra_search_unit,
				min: min,
				max: max,
				slide: function(event, ui) {
					start_input.val(ui.values[0]);
					start_span.text(ui.values[0]);
					end_input.val(ui.values[1]);
					end_span.text(ui.values[1]);
				}
			});
			if ( start_val && end_val ) bar.slider('values', [start_val, end_val]);
			else
			{
				if ( start_val ) bar.slider('values', [start_val, Math.round((max-min)*2/3)+min]);
				else if ( end_val ) bar.slider('values', [Math.round((max-min)/3)+min, end_val]);
				else bar.slider('values', [Math.round((max-min)/3)+min, Math.round((max-min)*2/3)+min]);
			}
			start_span.text(bar.slider('values', 0));
			end_span.text(bar.slider('values', 1));
		});

		var slide_cursor = document.querySelectorAll('.ui-slider-handle');
		for ( var i = 0; i < slide_cursor.length; i++ )
		{
			slide_cursor[i].addEventListener('touchstart', touchHandler, true);
			slide_cursor[i].addEventListener('touchmove', touchHandler, true);
			slide_cursor[i].addEventListener('touchend', touchHandler, true);
		}

		function touchHandler(event) {
			var touch = event.changedTouches[0];

			var simulatedEvent = document.createEvent('MouseEvent');
				simulatedEvent.initMouseEvent({
				touchstart: 'mousedown',
				touchmove: 'mousemove',
				touchend: 'mouseup'
			}[event.type], true, true, window, 1,
				touch.screenX, touch.screenY,
				touch.clientX, touch.clientY, false,
				false, false, false, 0, null);

			touch.target.dispatchEvent(simulatedEvent);
			event.preventDefault();
		}
	});
})(jQuery);