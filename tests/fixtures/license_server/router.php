<?php
/*
	A license server for the license tests of Ka Extensions (php -S 127.0.0.1:<port> router.php).
	The same file is in the tests of the OC3 and OC4 libraries.

	The test writes the configuration to KA_TEST_LICENSE_SERVER_CONFIG (json):
		mode     - 'new'   ka-station with ka_licenses 1.0.1.4: the API api/checkModules, api/linkLicense and
		                   the old scripts domain_info, register_key (old answers only),
		           'old'   an old ka-station: the API routes give a 404 page (the old scripts answer, the tests check
		                   that the library does not call them),
		           'error' the API answers with errors, the old scripts answer,
		           failures of all routes: '500' (HTTP 500 with a page), 'empty' (an empty answer),
		           'invalid' (not json), 'timeout' (the answer after the curl timeout of the library, 8 s)
		modules  - [license code => 'registered' | 'expired' | 'free'], others are unregistered.
		           A code may have an edition: 'code@oc3' is only for this edition
		key      - the right license key
	Requests are appended to KA_TEST_LICENSE_SERVER_LOG (one json line per request).
*/

$config = json_decode((string)@file_get_contents(getenv('KA_TEST_LICENSE_SERVER_CONFIG')), true) ?: array();
$config += array('mode' => 'new', 'modules' => array(), 'key' => 'GOOD-KEY');
$mode = $config['mode'];

$route = isset($_GET['route']) ? $_GET['route'] : '';
file_put_contents(getenv('KA_TEST_LICENSE_SERVER_LOG'), json_encode(array('route' => $route, 'post' => $_POST)) . "\n", FILE_APPEND);

if ($mode == 'timeout') {
	sleep(10);
	return;
} elseif ($mode == '500') {
	http_response_code(500);
	echo '<html><body>Internal Server Error</body></html>';
	return;
} elseif ($mode == 'empty') {
	return;
} elseif ($mode == 'invalid') {
	echo '{"result": "ok", "extensions": ';
	return;
}

header('Content-Type: application/json');

function module_status($config, $code, $edition) {
	if (isset($config['modules'][$code . '@' . $edition])) {
		return $config['modules'][$code . '@' . $edition];
	}
	return isset($config['modules'][$code]) ? $config['modules'][$code] : 'unregistered';
}

$api = 'extension/ka_extensions/ka_licenses/api/';

if ($route == $api . 'checkModules' || $route == $api . 'linkLicense') {

	if ($mode == 'old') {
		header('Content-Type: text/html', true, 404);
		echo '<html><body>The page you requested cannot be found!</body></html>';
		return;
	}
	if ($mode == 'error') {
		echo json_encode(array('api_version' => 1, 'result' => 'error', 'message' => 'Server error'));
		return;
	}

	if ($route == $api . 'checkModules') {
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

	if (isset($_POST['license_key']) && $_POST['license_key'] == $config['key']) {
		echo json_encode(array('api_version' => 1, 'result' => 'ok', 'message' => ''));
	} else {
		echo json_encode(array('api_version' => 1, 'result' => 'error', 'message' => 'Invalid license key'));
	}
	return;
}

if ($route == 'extension/domain_info') {
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
	// the old script adds the edition to the code: a code with an edition is not found here
	$key = isset($_POST['key']) ? $_POST['key'] : '';
	if ($key == $config['key'] && empty($_POST['edition'])) {
		$parts = explode('/', $_POST['extension']);
		echo json_encode(array('ext_code' => end($parts), 'result' => 'OK'));
	} else {
		echo json_encode(array('error' => 'The license key (' . $key . ') was not found for the extension "' . $_POST['extension'] . '". '));
	}
	return;
}

header('Content-Type: text/html', true, 404);
echo '<html><body>The page you requested cannot be found!</body></html>';
