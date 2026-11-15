<?php
/*
	Tests of library services without an OpenCart store: Curl (Basic authorization, status codes,
	large POST bodies, certificate verification) and HiddenSettings (on a temporary database).

	Usage:
		php tests/services.php [--port=8099] [--db=kat_ext_services]

	Environment:
		KA_TEST_DB_HOST (localhost), KA_TEST_DB_USER (root), KA_TEST_DB_PASSWORD ('')
*/

error_reporting(E_ALL);

define('DB_PREFIX', 'oc_');

$lib = dirname(__DIR__) . '/extension/ka_extensions/system/';
require_once($lib . 'ka_global.php');
require_once($lib . 'curl.php');
require_once($lib . 'hidden_settings.php');

use \Opencart\Extension\KaExtensions\System\Curl;
use \Opencart\Extension\KaExtensions\System\HiddenSettings;
use \Opencart\Extension\KaExtensions\System\KaGlobal;

/*
	A minimal database object with the methods of the OpenCart DB class used by HiddenSettings.
*/
class ServicesTestDb {

	protected $link;

	public function __construct($link) {
		$this->link = $link;
	}

	public function query($sql) {
		$res = $this->link->query($sql);
		if ($res instanceof \mysqli_result) {
			$rows = $res->fetch_all(MYSQLI_ASSOC);
			$result = new \stdClass();
			$result->rows     = $rows;
			$result->row      = isset($rows[0]) ? $rows[0] : array();
			$result->num_rows = count($rows);
			return $result;
		}
		return $res;
	}

	public function escape($value) {
		return $this->link->real_escape_string($value);
	}

	public function countAffected() {
		return $this->link->affected_rows;
	}
}

class ServicesTestRegistry {

	protected $data = array();

	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}
}

$opt = getopt('', array('port:', 'db:'));
$port = isset($opt['port']) ? (int)$opt['port'] : 8099;
$db_name = isset($opt['db']) ? $opt['db'] : 'kat_ext_services';

$passed   = 0;
$failures = 0;

function check($cond, $msg) {
	global $passed, $failures;

	if ($cond) {
		$passed++;
		echo "  ok   $msg\n";
	} else {
		$failures++;
		echo "  FAIL $msg\n";
	}
}

// Curl
//
echo "Curl\n";
$server = proc_open(array(PHP_BINARY, '-S', 'localhost:' . $port, __DIR__ . '/services/router.php'), array(1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes);
usleep(500000);
$base = 'http://localhost:' . $port;

$curl = new Curl();

$secret = 'p@ss:w/rd%20&+ä';
$body = $curl->request($base . '/auth', array(), array(
	'webauth_username' => 'api-user',
	'webauth_password' => $secret,
));
$json = json_decode((string)$body, true);
check(!empty($json) && $json['user'] == 'api-user' && $json['password'] === $secret, 'Basic authorization sends the password as it is');
check($curl->getLastStatusCode() == 200, 'status code 200');

$body = $curl->request($base . '/status/400');
$json = json_decode((string)$body, true);
check($curl->getLastStatusCode() == 400, 'status code 400');
check(!empty($json) && $json['status'] == 400, 'the body of an error response is returned');
check(strpos($curl->getLastError(), '400') !== false, 'getLastError() keeps the old text');

$curl->request($base . '/status/503');
check($curl->getLastStatusCode() == 503, 'status code 503');

$big = str_repeat('x', 5000);
$body = $curl->request($base . '/echo', $big, array('extra_headers' => array('Content-Type' => 'application/json')));
$json = json_decode((string)$body, true);
check(!empty($json) && $json['length'] == 5000 && $json['method'] == 'POST' && $curl->getLastStatusCode() == 200, 'a large POST body gets the real response (no interim 100 Continue)');

$curl->request('http://localhost:1/');
check($curl->getLastStatusCode() == 0 && $curl->getLastError() != '', 'status code 0 when there is no response');

proc_terminate($server);

// certificate verification: a local TLS server with a self-signed certificate (openssl s_server)
//
$tmp = sys_get_temp_dir() . '/ka_ext_services_' . $port;
@mkdir($tmp);
exec('openssl req -x509 -newkey rsa:2048 -nodes -keyout ' . escapeshellarg($tmp . '/key.pem') . ' -out ' . escapeshellarg($tmp . '/cert.pem') . ' -days 1 -subj /CN=localhost 2>&1', $out, $ret);
if ($ret) {
	echo "  skip certificate tests: openssl is not available\n";
} else {
	$tls_port = $port + 1;
	$tls = proc_open(array('openssl', 's_server', '-accept', (string)$tls_port, '-cert', $tmp . '/cert.pem', '-key', $tmp . '/key.pem', '-www', '-quiet'), array(1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes);
	usleep(700000);

	$body = $curl->request('https://localhost:' . $tls_port . '/');
	check(empty($body) && strpos($curl->getLastError(), 'CURL error (60)') !== false, 'a self-signed certificate is rejected by default');

	$body = $curl->request('https://localhost:' . $tls_port . '/', array(), array('verify_ssl' => false));
	check(!empty($body) && $curl->getLastStatusCode() == 200, "'verify_ssl' => false accepts it");

	proc_terminate($tls);
}

// HiddenSettings
//
echo "HiddenSettings\n";
$host = getenv('KA_TEST_DB_HOST') ?: 'localhost';
$user = getenv('KA_TEST_DB_USER') ?: 'root';
$pass = getenv('KA_TEST_DB_PASSWORD') ?: '';

$link = new mysqli($host, $user, $pass);
$link->query("DROP DATABASE IF EXISTS `$db_name`");
$link->query("CREATE DATABASE `$db_name`");
$link->select_db($db_name);
$link->query("CREATE TABLE `oc_setting` (
	`setting_id` int(11) NOT NULL AUTO_INCREMENT,
	`store_id` int(11) NOT NULL DEFAULT 0,
	`code` varchar(128) NOT NULL,
	`key` varchar(128) NOT NULL,
	`value` text NOT NULL,
	`serialized` tinyint(1) NOT NULL,
	PRIMARY KEY (`setting_id`)
)");
$link->query("INSERT INTO oc_setting SET store_id = 0, code = 'my_module', `key` = 'my_module_status', value = '1', serialized = 0");

$registry = new ServicesTestRegistry();
$registry->set('db', new ServicesTestDb($link));
KaGlobal::init($registry);

check(HiddenSettings::get('my_module', 'missing', 'def') === 'def', 'default value of a missing key');

HiddenSettings::set('my_module', 'version', '1.0.0.0');
check(HiddenSettings::get('my_module', 'version') === '1.0.0.0', 'string value');

HiddenSettings::set('my_module', 'products', array('P' => 'EXPRESS WORLDWIDE', 'U' => "it's"));
$value = HiddenSettings::get('my_module', 'products');
check(is_array($value) && $value['U'] == "it's", 'array value is stored as JSON');
$row = $link->query("SELECT * FROM oc_setting WHERE `key` = 'my_module_hidden_products'")->fetch_assoc();
check($row && $row['code'] == 'my_module_hidden' && $row['serialized'] == 1 && $row['store_id'] == 0, 'group, key, serialized flag and store');

HiddenSettings::set('my_module', 'version', '1.0.1.0');
$count = $link->query("SELECT COUNT(*) c FROM oc_setting WHERE `key` = 'my_module_hidden_version'")->fetch_assoc();
check(HiddenSettings::get('my_module', 'version') === '1.0.1.0' && $count['c'] == 1, 'set() updates the row');

check(HiddenSettings::increase('my_module', 'requests') == 1, 'increase() creates a missing value');
check(HiddenSettings::increase('my_module', 'requests', 5) == 6, 'increase() adds');

HiddenSettings::set('my_module', 'version', null);
check(HiddenSettings::get('my_module', 'version') === null, 'null removes the key');

$row = $link->query("SELECT value FROM oc_setting WHERE `key` = 'my_module_status'")->fetch_assoc();
check($row && $row['value'] == '1', 'settings of the module group are not touched');

$link->query("DROP DATABASE IF EXISTS `$db_name`");

echo "\nPassed: $passed, failed: $failures\n";
exit($failures ? 1 : 0);
