// This overrides the sidebar_condition_process method in WSForm and add a belong_to condition in the sidebar filters
(function($) {
  if ($.WS_Form && typeof $.WS_Form.prototype.sidebar_condition_process === "function") {
	// Sidebar - Condition process
	$.WS_Form.prototype.sidebar_condition_process = function(obj_sidebar_outer, obj, initial_run) {

	  if(this.sidebar_conditions.length == 0) { return true; }

	  var condition_result_array = [];

	  // Run all conditions
	  for(var sidebar_conditions_index in this.sidebar_conditions) {

		if(!this.sidebar_conditions.hasOwnProperty(sidebar_conditions_index)) { continue; }

		var sidebar_condition = this.sidebar_conditions[sidebar_conditions_index];

		var sidebar_condition_type = sidebar_condition.type;
		var sidebar_condition_logic = sidebar_condition.logic;
		var sidebar_condition_meta_key = sidebar_condition.meta_key;
		var sidebar_condition_meta_value = sidebar_condition.meta_value;
		var sidebar_condition_logic_previous = sidebar_condition.logic_previous;

		// Check type
		if(sidebar_condition_type === 'sidebar_meta_key') {

		  // Get meta key obj
		  var sidebar_condition_meta_key_obj = $('[data-meta-key="' + this.esc_selector(sidebar_condition_meta_key) + '"]', obj_sidebar_outer);

		  // Check meta key exists
		  if(!sidebar_condition_meta_key_obj.length) { continue; }
		}

		// Get meta key type
		var meta_key_config = $.WS_Form.meta_keys[sidebar_condition_meta_key];
		var sidebar_condition_meta_key_type = meta_key_config['type'];

		// Get meta key to show
		var sidebar_condition_show = sidebar_condition.show;
		var sidebar_condition_show_obj = $('[data-meta-key="' + this.esc_selector(sidebar_condition_show) + '"]', obj_sidebar_outer);
		if(!sidebar_condition_show_obj.length) { continue; }

		// Get current result
		var result = true;
		var meta_value = '';

		// Process condition
		switch(sidebar_condition_meta_key_type) {

		  case 'checkbox' :

			switch(sidebar_condition_type) {

			  case 'object_meta_value_form' :

				meta_value = this.get_object_meta_value(this.form, sidebar_condition_meta_key, false);
				break;

			  case 'sidebar_meta_key' :

				meta_value = sidebar_condition_meta_key_obj.is(':checked');
				break;
			}


			switch(sidebar_condition_logic) {

			  case '==' :

				result = meta_value;
				break;

			  case '!=' :

				result = !meta_value;
				break;
			}

			break;

		  default :

			switch(sidebar_condition_type) {

			  case 'object_meta_value_form' :

				meta_value = this.get_object_meta_value(this.form, sidebar_condition_meta_key, false);
				break;

			  case 'sidebar_meta_key' :

				meta_value = sidebar_condition_meta_key_obj.val();
				break;
			}

			if(meta_value === null) { meta_value = ''; }

			// Check for options_default
			if(meta_value === 'default') {

			  var meta_key_config = $.WS_Form.meta_keys[sidebar_condition_meta_key];
			  if(typeof(meta_key_config['options_default']) !== 'undefined') {

				meta_value = this.get_object_meta_value(this.form, meta_key_config['options_default'], '');
			  }
			}

			switch(sidebar_condition_logic) {
			  case '==' :
				result = (meta_value == sidebar_condition_meta_value);
				break;
			  case '!=' :
				result = (meta_value != sidebar_condition_meta_value);
				break;

			  case 'contains' :
				result = (meta_value.indexOf(sidebar_condition_meta_value) !== -1);
				break;

			  case 'contains_not' :
				result = (meta_value.indexOf(sidebar_condition_meta_value) === -1);
				break;

			  case 'belong_to' :
				result = (sidebar_condition_meta_value.indexOf(meta_value) !== -1);
				break;
			}
		}

		// Assign to result
		if(typeof(condition_result_array[sidebar_condition_show]) === 'undefined') {

		  condition_result_array[sidebar_condition_show] = result;

		} else {

		  switch(sidebar_condition_logic_previous) {

			case '||' :

			  condition_result_array[sidebar_condition_show] = (condition_result_array[sidebar_condition_show] || result);
			  break;

			default :

			  condition_result_array[sidebar_condition_show] = (condition_result_array[sidebar_condition_show] && result);
			  break;
		  }
		}
	  }

	  // Process results
	  for(sidebar_condition_show in condition_result_array) {

		var condition_result = condition_result_array[sidebar_condition_show];

		// Show / hide
		var show_obj = $('[data-meta-key="' + this.esc_selector(sidebar_condition_show) + '"]', obj_sidebar_outer).closest('.wsf-field-wrapper');

		// Show / hide object
		if(condition_result) {

		  show_obj.show().removeClass('wsf-field-hidden');

		} else {

		  show_obj.hide().addClass('wsf-field-hidden');
		}

		// Sidebar fieldset toggles
		this.sidebar_fieldset_toggle(show_obj);
	  }

	  if(!initial_run) {

		// Check if this is an element within a datagrid
		var object_data_saved = false;

		var data_grid_obj = obj.closest('.wsf-data-grid');
		if(data_grid_obj.length) {

		  var data_grid_meta_key = data_grid_obj.attr('data-meta-key');

		  switch(data_grid_meta_key) {

			case 'conditional' :

			  $.WS_Form.this.conditional_save();
			  object_data_saved = true;
			  break;

			case 'action' :

			  $.WS_Form.this.action_save();
			  object_data_saved = true;
			  break;
		  }
		}

		if(!object_data_saved) {

		  // Get object data
		  var object_identifier = obj.closest('[data-object]');
		  var object = object_identifier.attr('data-object');
		  var object_id = object_identifier.attr('data-id');
		  var object_data = $.WS_Form.this.get_object_data(object, object_id, true);

		  // Save sidebar
		  for(var key in $.WS_Form.this.object_meta_cache) {

			if(!$.WS_Form.this.object_meta_cache.hasOwnProperty(key)) { continue; }

			// Get meta_key
			var meta_key = $.WS_Form.this.object_meta_cache[key]['meta_key'];

			// Update object data
			$.WS_Form.this.object_data_update_by_meta_key(object, object_data, meta_key);
		  }
		}

		// Init
		var inits = ['options-action', 'repeater', 'text-editor', 'html-editor'];
		$.WS_Form.this.sidebar_inits(inits, obj_sidebar_outer);
	  }
	}
  }

  if(moWSForm.is_premium === 'false') {
      var old = window.sidebar_action_open;
      window.sidebar_action_open = function (ws_this, obj_form, obj_button) {
          old(ws_this, obj_form, obj_button);
          $('.wsf-sidebar-footer').before(moWSForm.upsell);
      };
  }
})(jQuery);
