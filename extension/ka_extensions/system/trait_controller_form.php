<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
	
namespace Opencart\Extension\KaExtensions\System;

/**
	Helpful functions for forms: field values, errors and validation.

	@package Controller
*/
trait TraitControllerForm {

	protected $fields;
	protected $errors;
	protected $url_params;

	/**
	 Fills a field with its value: from new data, then old data, then the default value. Formats prices and prepares thumbnails for image fields.

	 $k — field code.
	 $f — field metadata array.
	 $old_data — old data, e.g. from the database.
	 $new_data — new data (POST array) or null.

	 @return array Field with the value.
	*/
	protected function getFieldWithData($k, $f, $old_data, $new_data) {

		if (!empty($f['code'])) {
			$k = $f['code'];
		} else {
			$f['code'] = $k;
		}		

		if (is_array($new_data)) {
			if (isset($new_data[$k])) {
				$f['value'] = $new_data[$k];
			} else {
				$f['value'] = '';
			}
		} elseif (isset($old_data[$k])) {
			$f['value'] = $old_data[$k];
		} elseif (!empty($f['default_value'])) {
			$f['value'] = $f['default_value'];
		} else {
			$f['value'] = '';
		}
		
		if (!empty($f['value']) && !empty($f['format'])) {
			if ($f['format'] == 'price') {
				$f['value'] = $this->currency->format($f['value'], $this->config->get('config_currency'));
				
			}
		}

		if (!empty($f['type'])) {
			if ($f['type'] == 'image') {
				if (is_file(DIR_IMAGE . $f['value'])) {
					$f['value'] = $f['value'];
					$f['thumb'] = $this->model_tool_image->resize($f['value'], 200, 200);
				} else {
					$f['value'] = '';
					$f['thumb'] = $this->model_tool_image->resize('no_image.png', 200, 200);
				}
				
				$f['default_thumb'] = $this->model_tool_image->resize('no_image.png', 200, 200);
				$f['default_value'] = 'no_image.png';
			}				
		}
		
		return $f;
	}	
	

	/**
	 Fills fields with values from old and new data and attaches errors.

	 $fields — field metadata arrays.
	 $old_data — old data, e.g. from the database.
	 $new_data — new data when the form was submitted, otherwise null.
	 $errors — errors indexed by field code.

	 @return array Fields with values.
	*/
	protected function getFieldsWithData($fields, $old_data, $new_data = null, $errors = array()) {
	
		foreach ($fields as $k => $f) {

			if (!empty($errors[$k])) {
				$f['error'] = $errors[$k];
			}
		
			$f = $this->getFieldWithData($k, $f, $old_data, $new_data);

			$fields[$k] = $f;
		}
		
		return $fields;
	}

	
	/**
	 Returns code => value pairs from fields.

	 $fields — fields with values.

	 @return array Field values.
	*/
	protected function getFieldValues($fields) {
	
		$values = array();
	
		foreach ($fields as $k=>$f) {
			$values[$f['code']] = $f['value'];
		}
		
		return $values;
	}
	

	/**
	 Validates a field (required, presence, number min and max) and writes errors to $this->errors.

	 $code — field code.
	 $field — field metadata.
	 $post — submitted data.

	 @return bool False if the field is required but empty or missing. Min and max errors are recorded but still return true.
	*/
	protected function validateField($code, $field, $post) {
	
		if (!empty($field['required'])) {
			if (empty($post[$code])) {
				$this->errors[$code] = sprintf($this->language->get('Field "%s" cannot be empty'), $code);
				return false;
			}
		}

		if (!empty($field['type'])) {

			if (in_array($field['type'], array('number', 'text', 'textarea'))) {
				if (!isset($post[$code])) {
					$this->errors[$code] = sprintf($this->language->get('The field %s was not found'), $this->language->get('txt_title_' . $code));
					return false;
				}
			}
		
			if ($field['type'] == 'number') {
				// an empty value is checked by 'required' only, a non-required empty field has no limits
				if (is_string($post[$code]) && !strlen(trim($post[$code]))) {
					return true;
				}
				if (isset($field['min_value'])) {
					if ($field['min_value'] > $post[$code]) {
						$this->errors[$code] = sprintf($this->language->get("Minimum value is %s"), $field['min_value']);
						return false;
					}
				} 
				if (isset($field['max_value'])) {
					if ($field['max_value'] < $post[$code]) {
						$this->errors[$code] = sprintf($this->language->get("Maximum value is %s"), $field['max_value']);
						return false;
					}
				}
			}
		}
		
		return true;
	}
	

	/**
	 Validates all fields.

	 $fields — field metadata arrays.
	 $post — submitted data.

	 @return bool True if there are no errors.
	*/
	protected function validateFields($fields, $post) {

		foreach ($fields as $k => $f) {
			$this->validateField($k, $f, $post);
		}
		
		if (empty($this->errors)) {
			return true;
		}

		return false;
	}	
}