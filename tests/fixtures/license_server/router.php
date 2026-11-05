<?php
/*
	A license server for the license tests (php -S 127.0.0.1:<port> router.php).

	The test writes the configuration to KA_TEST_LICENSE_SERVER_CONFIG (json):
		mode     - 'new' (domain_info and register_key with api_version, as ka_licenses 1.0.1.4),
		           'old' (the old scripts: api_version is ignored),
		           'error' (requests with api_version get an error, old requests the old answer)
		modules  - [license code => 'registered' | 'expired' | 'free'], others are unregistered.
		           A code may have an edition: 'code@oc3' is only for this edition
		key      - the right license key
	Requests are appended to KA_TEST_LICENSE_SERVER_LOG (one json line per request).
*/

$config_file = getenv('KA_TEST_LICENSE_SERVER_CONFIG');
$config = json_decode((string)@file_get_contents($config_file), true) ?: array();
$config += array('mode' => 'new', 'modules' => array(), 'key' => 'GOOD-KEY');

$route = isset($_GET['route']) ? $_GET['route'] : '';
file_put_contents(getenv('KA_TEST_LICENSE_SERVER_LOG'), json_encode(array('route' => $route, 'post' => $_POST)) . "\n", FILE_APPEND);

header('Content-Type: application/json');

$is_new = isset($_POST['api_version']) && $config['mode'] != 'old';

function module_status($config, $code, $edition) {
	if (isset($config['modules'][$code . '@' . $edition])) {
		return $config['modules'][$code . '@' . $edition];
	}
	return isset($config['modules'][$code]) ? $config['modules'][$code] : 'unregistered';
}

if ($route == 'extension/domain_info') {

	if ($is_new) {
		if ($config['mode'] == 'error') {
			echo json_encode(array('api_version' => 1, 'result' => 'error', 'message' => 'Server error'));
			return;
		}
		$modules = array();
		foreach ((array)$_POST['modules'] as $module) {
			$module += array('vendor' => '', 'code' => '', 'edition' => '');
			$status = module_status($config, $module['code'], $module['edition']);
			$modules[] = $module + array(
				'license_code' => $module['vendor'] . '/' . $module['code'] . '@' . $module['edition'],
				'status'       => $status,
				'expiry_at'    => $status == 'expired' ? '2020-01-01 00:00:00' : ($status == 'registered' ? '2030-01-01 00:00:00' : ''),
				'masked_key'   => in_array($status, array('registered', 'expired')) ? 'GOOD-XXXX-XXXX-XXXX-XXXX' : '',
			);
		}
		echo json_encode(array('api_version' => 1, 'result' => 'ok', 'modules' => $modules));
		return;
	}

	// the old answer: expired licenses are registered, the code without the edition
	$extensions = array();
	foreach ($config['modules'] as $code => $status) {
		$code = explode('@', $code)[0];
		if ($status == 'free') {
			$extensions[$code] = array('license_key' => '', 'masked_key' => '', 'expiry_date' => '', 'is_free' => 1);
		} else {
			$extensions[$code] = array('masked_key' => 'GOOD-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'GOOD-XXXX-XXXX-XXXX-XXXX', 'is_registered' => 1);
		}
	}
	echo json_encode(array('result' => 'ok', 'extensions' => $extensions));
	return;
}

if ($route == 'extension/register_key') {

	$key = isset($_POST['key']) ? $_POST['key'] : '';
	if ($is_new) {
		if ($config['mode'] == 'error') {
			echo json_encode(array('api_version' => 1, 'result' => 'error', 'message' => 'Server error', 'license_code' => ''));
		} elseif ($key == $config['key']) {
			echo json_encode(array('api_version' => 1, 'result' => 'ok', 'message' => '', 'license_code' => $_POST['vendor'] . '/' . $_POST['code'] . '@'
				. (empty($_POST['edition']) ? 'oc' . (int)$_POST['opencart_version'] : $_POST['edition'])));
		} else {
			echo json_encode(array('api_version' => 1, 'result' => 'error', 'message' => 'Invalid license key', 'license_code' => ''));
		}
		return;
	}

	// the old script adds the edition to the code: a code with an edition is not found here
	if ($key == $config['key'] && empty($_POST['edition'])) {
		echo json_encode(array('ext_code' => explode('/', $_POST['extension'])[count(explode('/', $_POST['extension'])) - 1], 'result' => 'OK'));
	} else {
		echo json_encode(array('error' => 'The license key (' . $key . ') was not found for the extension "' . $_POST['extension'] . '". '));
	}
	return;
}

echo json_encode(array('error' => 'Unknown route'));
