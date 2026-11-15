<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace Opencart\Extension\KaExtensions\System;

/**
	Hidden values of a module in the admin area and in the catalog: the installed version, counters,
	reference data loaded from external services and other values that are not fields of the module
	settings page. It is the same class as in Ka Extensions for OpenCart 3.

	The values are stored in the OpenCart setting table of the default store (store_id 0) in the group
	'<ext_code>_hidden' under the keys '<ext_code>_hidden_<key>'. Arrays are stored as JSON
	(serialized = 1) as OpenCart does. Every method reads or writes one key only, other values of the
	group are not touched, so saving the module settings page (ControllerSettings saves the group
	'<ext_code>' completely) or another key does not overwrite a hidden value.

	The values are loaded into the store config at the start of a request like other settings
	('<ext_code>_hidden_<key>'), the methods below always read the database.

	Example:
```php
	use \Opencart\Extension\KaExtensions\System\HiddenSettings;

	HiddenSettings::set('my_module', 'last_run', date('Y-m-d'));
	$last_run = HiddenSettings::get('my_module', 'last_run');      // '2026-11-15'
	$requests = HiddenSettings::increase('my_module', 'requests'); // atomic +1, the new value
```
	@package Service
*/
class HiddenSettings {

	/**
	 Returns the hidden value $key of the module or $default when it is not stored.

	 @return mixed
	*/
	public static function get($ext_code, $key, $default = null) {

		$row = static::getRow($ext_code, $key);
		if (empty($row)) {
			return $default;
		}

		if (!empty($row['serialized'])) {
			return json_decode($row['value'], true);
		}

		return $row['value'];
	}

	/**
	 Stores one hidden value of the module, other values are kept. Arrays are stored as JSON,
	 other values as strings. A null value removes the key.

	 @return void
	*/
	public static function set($ext_code, $key, $value) {

		$db = static::db();

		if (is_null($value)) {
			$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE " . static::getWhere($ext_code, $key));
			return;
		}

		$serialized = is_array($value) ? 1 : 0;
		$value      = is_array($value) ? json_encode($value) : (string)$value;

		$row = static::getRow($ext_code, $key);
		if (!empty($row)) {
			$db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $db->escape($value) . "', `serialized` = '" . $serialized . "' WHERE `setting_id` = '" . (int)$row['setting_id'] . "'");
		} else {
			$code = $ext_code . '_hidden';
			$db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = '" . $db->escape($code) . "', `key` = '" . $db->escape($code . '_' . $key) . "', `value` = '" . $db->escape($value) . "', `serialized` = '" . $serialized . "'");
		}
	}

	/**
	 Adds $add to a numeric hidden value with one UPDATE (value = value + $add), so parallel requests
	 do not lose increments. A missing value is created with $add. Returns the new value.

	 @return mixed
	*/
	public static function increase($ext_code, $key, $add = 1) {

		$db = static::db();

		$add = is_int($add) ? $add : (is_numeric($add) ? (float)$add : 0);
		if (empty($add)) {
			return static::get($ext_code, $key);
		}

		$where = static::getWhere($ext_code, $key);

		$update = "UPDATE `" . DB_PREFIX . "setting` SET `value` = `value` + " . $add . " WHERE " . $where;

		$db->query($update);
		if (!$db->countAffected()) {

			// the value is missing. The setting table has no unique key, a named lock makes parallel
			// requests insert it once: the others wait and update the inserted row
			$lock = "'ka_hidden_" . md5($ext_code . '/' . $key) . "'";
			$db->query("SELECT GET_LOCK(" . $lock . ", 10)");
			try {
				$db->query($update);
				if (!$db->countAffected()) {
					static::set($ext_code, $key, $add);
				}
			} finally {
				$db->query("SELECT RELEASE_LOCK(" . $lock . ")");
			}
		}

		return static::get($ext_code, $key);
	}

	/**
	 @internal
	*/
	protected static function db() {
		return KaGlobal::getRegistry()->get('db');
	}

	/**
	 @internal
	 The row of the hidden value: setting_id, value, serialized or an empty array.
	*/
	protected static function getRow($ext_code, $key) {
		$qry = static::db()->query("SELECT `setting_id`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE " . static::getWhere($ext_code, $key) . " ORDER BY `setting_id` LIMIT 1");
		return $qry->row;
	}

	/**
	 @internal
	*/
	protected static function getWhere($ext_code, $key) {
		$db   = static::db();
		$code = $ext_code . '_hidden';
		return "`store_id` = '0' AND `code` = '" . $db->escape($code) . "' AND `key` = '" . $db->escape($code . '_' . $key) . "'";
	}
}
