<?php
/*
	Router of the local http server for tests/services.php.
*/
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

header('Content-Type: application/json');

if ($path == '/auth') {
	echo json_encode(array(
		'user'     => isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '',
		'password' => isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '',
	));

} elseif (preg_match('#^/status/(\d+)$#', $path, $m)) {
	http_response_code((int)$m[1]);
	echo json_encode(array('status' => (int)$m[1]));

} elseif ($path == '/echo') {
	$body = file_get_contents('php://input');
	echo json_encode(array(
		'method' => $_SERVER['REQUEST_METHOD'],
		'length' => strlen($body),
		'expect' => isset($_SERVER['HTTP_EXPECT']) ? $_SERVER['HTTP_EXPECT'] : '',
	));

} else {
	http_response_code(404);
	echo '{}';
}
