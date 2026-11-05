<?php
/*
	Basic install tests of Ka Extensions on a clean OpenCart 4 store.

	The script builds the package from this working copy, installs a clean OpenCart store from
	the official archive and installs Ka Extensions through the admin area over HTTP, the way
	readme.ka_extensions.txt describes it. Then it checks the menu, the pages and the error log
	and uninstalls the package.

	Usage:
		php tests/run.php --oc=<opencart dir or zip> [--port=8096] [--work-dir=...] [--db=...]

	Environment:
		KA_TEST_DB_HOST (localhost), KA_TEST_DB_USER (root), KA_TEST_DB_PASSWORD ('')
*/

error_reporting(E_ALL);

class KaExtTest {

	protected $opt;
	protected $db;
	protected $base_url;
	protected $store_dir;
	protected $cookie_file;
	protected $token;
	protected $failures = 0;
	protected $passed   = 0;

	public function __construct($opt) {
		$this->opt = $opt;
	}

	/////////////////////// helpers ///////////////////////

	protected function log($msg) {
		echo $msg . "\n";
	}

	protected function fail($msg) {
		fwrite(STDERR, "FATAL: $msg\n");
		$this->stopServer();
		exit(1);
	}

	protected function check($cond, $msg) {
		if ($cond) {
			$this->passed++;
			$this->log("  ok   $msg");
		} else {
			$this->failures++;
			$this->log("  FAIL $msg");
		}
		return $cond;
	}

	protected function sh($cmd) {
		exec($cmd . ' 2>&1', $out, $ret);
		if ($ret) {
			$this->fail("Command failed: $cmd\n" . implode("\n", $out));
		}
		return implode("\n", $out);
	}

	protected function query($sql) {
		$res = $this->db->query($sql);
		if ($res === true) {
			return array();
		}
		return $res->fetch_all(MYSQLI_ASSOC);
	}

	protected function value($sql) {
		$rows = $this->query($sql);
		if (empty($rows)) {
			return null;
		}
		return reset($rows[0]);
	}

	protected function http($route, $post = null, $get = '') {
		$url = $this->base_url . 'admin/index.php?route=' . $route;
		if (!empty($this->token)) {
			$url .= '&user_token=' . $this->token;
		}
		if ($get !== '') {
			$url .= '&' . $get;
		}
		return $this->httpUrl($url, $post);
	}

	protected function httpUrl($url, $post = null) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_USERAGENT, 'ka-ext-tests');
		if (!is_null($post)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		$resp = curl_exec($ch);
		if ($resp === false) {
			$this->fail("HTTP request failed: $url " . curl_error($ch));
		}
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$ret = array(
			'code'     => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'location' => '',
			'headers'  => substr($resp, 0, $header_size),
			'body'     => substr($resp, $header_size),
		);
		if (preg_match('/^Location:\s*(.*)$/mi', substr($resp, 0, $header_size), $m)) {
			$ret['location'] = html_entity_decode(trim($m[1]));
		}
		return $ret;
	}

	/*
		Returns PHP errors found in a page and new lines of the store error log.
	*/
	protected function getErrors($body = '') {
		$errors = array();
		if (preg_match_all('/<b>(Warning|Notice|Fatal error|Deprecated|Parse error)<\/b>:.*?<br \/>|(?:PHP )?(Warning|Notice|Deprecated|Fatal Error|Error): .{0,300}? in .{0,200}? on line \d+/s', $body, $m)) {
			$errors = array_merge($errors, array_map('strip_tags', $m[0]));
		}
		$log = $this->store_dir . '/system/storage/logs/error.log';
		if (is_file($log)) {
			// the library logs a failed request to the license server: the tests do not use a real one
			$lines = preg_split('/\n(?=\d{4}-\d\d-\d\d \d\d:\d\d:\d\d - )/', trim(file_get_contents($log)));
			$text = trim(implode("\n", array_filter($lines, function($line) {
				return strpos($line, 'checkModules failed: ') === false;
			})));
			if ($text !== '') {
				$errors[] = 'error.log: ' . substr($text, 0, 1500);
				file_put_contents($log, '');
			}
		}
		return $errors;
	}

	protected function checkNoErrors($resp, $title) {
		$errors = $this->getErrors($resp['body']);
		$this->check($resp['code'] == 200 && empty($errors), "$title: HTTP $resp[code]" . (empty($errors) ? '' : ', errors: ' . implode(' | ', $errors)));
	}

	/////////////////////// store setup ///////////////////////

	public function setup() {

		$port = $this->opt['port'];
		$this->base_url    = "http://localhost:$port/";
		$this->store_dir   = $this->opt['work-dir'] . '/store';
		$this->cookie_file = $this->opt['work-dir'] . '/cookies.txt';

		$this->sh('rm -rf ' . escapeshellarg($this->opt['work-dir']));
		mkdir($this->opt['work-dir'], 0777, true);
		$oc = $this->opt['oc'];
		if (is_file($oc)) {
			$this->sh('cd ' . escapeshellarg($this->opt['work-dir']) . ' && unzip -q ' . escapeshellarg(realpath($oc)) . ' -d oc_src');
			$oc = $this->opt['work-dir'] . '/oc_src';
		}
		$upload = trim($this->sh('find ' . escapeshellarg($oc) . ' -maxdepth 3 -type d -name upload | head -1'));
		if (empty($upload)) {
			$this->fail("'upload' directory is not found in $oc");
		}
		$this->sh('cp -r ' . escapeshellarg($upload) . ' ' . escapeshellarg($this->store_dir));
		rename($this->store_dir . '/config-dist.php', $this->store_dir . '/config.php');
		rename($this->store_dir . '/admin/config-dist.php', $this->store_dir . '/admin/config.php');

		$this->db = new mysqli($this->opt['db_host'], $this->opt['db_user'], $this->opt['db_password']);
		$this->db->query("DROP DATABASE IF EXISTS `" . $this->opt['db'] . "`");
		$this->db->query("CREATE DATABASE `" . $this->opt['db'] . "`");
		$this->db->select_db($this->opt['db']);

		$out = $this->sh('php ' . escapeshellarg($this->store_dir . '/install/cli_install.php') . ' install'
			. ' --username admin --password Admin12345 --email admin@example.com'
			. ' --http_server ' . escapeshellarg($this->base_url)
			. ' --language en-gb --db_driver mysqli'
			. ' --db_hostname ' . escapeshellarg($this->opt['db_host'])
			. ' --db_username ' . escapeshellarg($this->opt['db_user'])
			. ' --db_password ' . escapeshellarg($this->opt['db_password'])
			. ' --db_database ' . escapeshellarg($this->opt['db'])
			. ' --db_port 3306 --db_prefix oc_'
		);
		if (strpos($out, 'SUCCESS') === false) {
			$this->fail("OpenCart installation failed:\n$out");
		}
		$this->sh('rm -rf ' . escapeshellarg($this->store_dir . '/install'));
		$this->log("OpenCart " . $this->getOcVersion() . " installed");

		$this->stopServer();
		exec('cd ' . escapeshellarg($this->store_dir) . " && nohup php -S localhost:$port -t . > ../server.log 2>&1 &");
		usleep(700000);

		$this->login();
	}

	protected function stopServer() {
		// the bracket keeps pkill from matching its own shell
		$port = (string)$this->opt['port'];
		exec("pkill -f 'php -S localhost:[" . $port[0] . "]" . substr($port, 1) . "'");
	}

	protected function getOcVersion() {
		$src = file_get_contents($this->store_dir . '/admin/index.php');
		if (preg_match("/define\('VERSION', '([^']+)'\)/", $src, $m)) {
			return $m[1];
		}
		return '?';
	}

	protected function login($new_session = true) {
		$this->token = '';
		if ($new_session) {
			@unlink($this->cookie_file);
		}
		$resp = $this->http('common/login');
		if (!preg_match('/action="([^"]*login\.login[^"]*)"/', $resp['body'], $m)) {
			$this->fail('Login form is not found');
		}
		$resp = $this->httpUrl(html_entity_decode($m[1]), array('username' => 'admin', 'password' => 'Admin12345'));
		if (!preg_match('/user_token=([a-z0-9]+)/i', $resp['body'], $m)) {
			$this->fail('Cannot log in: ' . $resp['body']);
		}
		$this->token = $m[1];
	}

	protected function getInstallId() {
		return $this->value("SELECT extension_install_id FROM oc_extension_install WHERE code = 'ka_extensions' ORDER BY extension_install_id DESC LIMIT 1");
	}

	/*
		Follows the 'next' chain of installer json responses. Returns the error or an empty string.
	*/
	protected function runInstaller($route) {
		$url = $this->base_url . 'admin/index.php?route=' . $route . '&user_token=' . $this->token . '&extension_install_id=' . $this->getInstallId();
		while (!empty($url)) {
			$resp = $this->httpUrl($url);
			$json = json_decode($resp['body'], true);
			if (!is_array($json)) {
				return 'wrong response: ' . substr(strip_tags($resp['body']), 0, 1000);
			}
			if (!empty($json['error'])) {
				return is_array($json['error']) ? json_encode($json['error']) : $json['error'];
			}
			$url = isset($json['next']) ? html_entity_decode($json['next']) : '';
		}
		return '';
	}

	/*
		The modification page patches system/framework.php and kamod builds its cache on the next request.
		With opcache (revalidate_freq=2 by default) the old framework.php may still run for a moment, so
		the page is reloaded a few times like readme.ka_extensions.txt suggests.
	*/
	protected function getMenuHtml($expected = true) {
		for ($i = 0; $i < 5; $i++) {
			$resp = $this->http('common/dashboard');
			if ($this->hasMenuItem($resp['body']) == $expected) {
				break;
			}
			sleep(1);
		}
		if ($i) {
			$this->log("  (dashboard loaded " . ($i + 1) . " times)");
		}
		return $resp;
	}

	protected function hasMenuItem($body) {
		return strpos($body, 'route=extension/ka_extensions/extensions') !== false;
	}

	/////////////////////// tests ///////////////////////

	protected function testPackage() {
		$zip = new ZipArchive();
		$this->check($zip->open($this->opt['package']) === true, 'package is a valid zip');
		$names = array();
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$names[] = $zip->getNameIndex($i);
		}
		$expected = array('install.json', 'ocmod/ka_extensions.ocmod.xml', 'engine/startup.php',
			'admin/controller/extensions.php', 'kamod/admin/controller/common/column_left.php', 'system/controller.php'
		);
		foreach ($expected as $file) {
			$this->check(in_array($file, $names), "package contains $file");
		}
		$this->check(!in_array('active.kamod', $names), 'package does not contain active.kamod');
		$this->check(empty(preg_grep('#^(upload|extension|tests|mod1|mod2)/#', $names)), 'package files are at the archive root, no mod1/mod2/tests');

		$json = json_decode($zip->getFromName('install.json'), true);
		$this->check(!empty($json['version']), 'install.json is valid, version ' . (isset($json['version']) ? $json['version'] : '?'));
		$this->check(@simplexml_load_string($zip->getFromName('ocmod/ka_extensions.ocmod.xml')) !== false, 'ocmod xml is valid');
	}

	protected function testInstall() {
		$resp = $this->http('marketplace/installer.upload', array('file' => new CURLFile($this->opt['package'], 'application/zip', 'ka_extensions.ocmod.zip')));
		$this->check(strpos($resp['body'], 'success') !== false, 'package is uploaded: ' . substr(strip_tags($resp['body']), 0, 300));
		$this->check(!empty($this->getInstallId()), 'package is registered in oc_extension_install');

		$error = $this->runInstaller('marketplace/installer.install');
		$this->check($error === '', 'package is installed' . ($error ? ": $error" : ''));
		$this->check(is_file($this->store_dir . '/extension/ka_extensions/engine/startup.php'), 'files are copied to extension/ka_extensions');
		$this->check($this->value("SELECT status FROM oc_extension_install WHERE code = 'ka_extensions'") == 1, 'extension_install status is 1');
		$errors = $this->getErrors();
		$this->check(empty($errors), 'no errors in log after install' . ($errors ? ': ' . implode(' | ', $errors) : ''));
	}

	protected function testModification() {
		$id = $this->value("SELECT modification_id FROM oc_modification WHERE code = 'ka_extensions'");
		if (!$this->check(!empty($id), 'Ka Extensions Ocmod is registered in oc_modification')) {
			return;
		}
		$this->checkNoErrors($this->http('marketplace/modification.enable', null, 'modification_id=' . $id), 'modification is enabled');
		$this->check($this->value("SELECT status FROM oc_modification WHERE modification_id = '$id'") == 1, 'modification status is 1');

		$resp = $this->http('marketplace/modification.refresh');
		$json = json_decode($resp['body'], true);
		$this->check(is_array($json) && empty($json['error']), 'modification refresh: ' . substr(strip_tags($resp['body']), 0, 300));

		$cache = $this->store_dir . '/extension/ocmod/system/framework.php';
		$this->check(is_file($cache) && strpos(file_get_contents($cache), 'ka_extensions/engine/startup.php') !== false, 'framework.php is patched in the modification cache');

		$log = $this->store_dir . '/system/storage/logs/ocmod.log';
		$text = is_file($log) ? file_get_contents($log) : '';
		// operations marked error="skip" are logged as "NOT FOUND - OPERATION SKIPPED!" and are allowed
		$failed = preg_match('/NOT FOUND(?! - OPERATION SKIPPED)/', $text);
		$this->check(!$failed, 'all required ocmod operations are applied' . ($failed ? ":\n" . $text : ''));

		// readme: reload the Modification page two times
		$this->checkNoErrors($this->http('marketplace/modification'), 'modification page, 1st load');
		$this->checkNoErrors($this->http('marketplace/modification'), 'modification page, 2nd load');
	}

	protected function testMenu() {
		$resp = $this->getMenuHtml();
		$this->checkNoErrors($resp, 'admin dashboard');
		$this->check($this->hasMenuItem($resp['body']), "'Ka Extensions' item is in the Extensions menu");

		$resp = $this->http('extension/ka_extensions/extensions');
		$this->checkNoErrors($resp, 'Ka Extensions page');
		$this->check(strpos($resp['body'], '<h1>Ka Extensions') !== false, 'Ka Extensions page has the title');

		$resp = $this->http('marketplace/extension', null, 'type=module');
		$this->checkNoErrors($resp, 'Extensions > Modules page');
	}

	/*
		Switches to another browser session (cookie file). Returns the previous session.
	*/
	protected function switchSession($name) {
		$prev = array($this->cookie_file, $this->token);
		$this->cookie_file = $this->opt['work-dir'] . '/cookies_' . $name . '.txt';
		$this->token = '';
		return $prev;
	}

	protected function restoreSession($prev) {
		list($this->cookie_file, $this->token) = $prev;
	}

	protected function saveSafeModeCode($code) {
		return $this->http('extension/ka_extensions/extensions.saveSafeModeCode', array('safe_mode_code' => $code));
	}

	protected function getKamodConfig() {
		$file = $this->store_dir . '/system/config/kamod.php';
		$_ = array();
		if (is_file($file)) {
			include($file);
		}
		return $_;
	}

	/*
		Opens the safe mode URL with the code in a new browser session, logs in and returns the dashboard.
	*/
	protected function openSafeMode($session, $code) {
		$prev = $this->switchSession($session);
		@unlink($this->cookie_file);
		$resp = $this->httpUrl($this->base_url . 'admin/index.php?route=ka_safe_mode&code=' . urlencode($code));
		$this->login(false);
		$dashboard = $this->http('common/dashboard');
		$this->restoreSession($prev);
		return array('url' => $resp, 'dashboard' => $dashboard);
	}

	/*
		Opens the dashboard in an existing browser session.
	*/
	protected function sessionDashboard($session) {
		$prev = $this->switchSession($session);
		$this->login(false);
		$resp = $this->http('common/dashboard');
		$this->restoreSession($prev);
		return $resp;
	}

	protected function testSafeMode() {
		$resp = $this->saveSafeModeCode('bad code!');
		$this->check(empty($this->getKamodConfig()['safe_mode_code']), 'safe mode: a code with wrong characters is not saved');

		$this->saveSafeModeCode('test1a');
		$config = $this->getKamodConfig();
		$this->check(isset($config['safe_mode_code']) && $config['safe_mode_code'] == 'test1a' && !empty($config['admin_dir']), 'safe mode: the code is saved to config/kamod.php');
		$resp = $this->http('extension/ka_extensions/extensions');
		$this->checkNoErrors($resp, 'safe mode: Ka Extensions page with the code');
		$this->check(strpos($resp['body'], 'route=ka_safe_mode&amp;code=test1a') !== false || strpos($resp['body'], 'route=ka_safe_mode&code=test1a') !== false, 'safe mode: the page shows the safe mode URL');

		$res = $this->openSafeMode('safe1', 'test1a');
		$this->check($res['url']['code'] == 302 && preg_match('/^Set-Cookie: ka_safe_mode_code=test1a;.*HttpOnly/mi', $res['url']['headers']) && preg_match('/^Set-Cookie: ka_safe_mode_code=.*SameSite=Lax/mi', $res['url']['headers']), 'safe mode: the URL sets an httponly SameSite=Lax cookie and redirects');
		$this->check($res['dashboard']['code'] == 200 && !$this->hasMenuItem($res['dashboard']['body']), 'safe mode: the admin area works without kamod (no Ka Extensions menu item)');

		$prev = $this->switchSession('safe1');
		$this->login(false);
		$resp = $this->http('extension/ka_extensions/extensions');
		$this->restoreSession($prev);
		$this->check($resp['code'] == 200 && strpos($resp['body'], 'id="ka-safe-mode-active"') !== false && empty($this->getErrors($resp['body'])), 'safe mode: Ka Extensions page shows a safe mode message');

		$res = $this->openSafeMode('wrong', 'wrongcode');
		$this->check(stripos($res['url']['headers'], 'ka_safe_mode_code=wrongcode') === false && $this->hasMenuItem($res['dashboard']['body']), 'safe mode: a wrong code does not turn on safe mode');

		// kamod rebuild keeps the code
		$this->http('marketplace/modification.refresh');
		$this->getMenuHtml();
		$this->check(isset($this->getKamodConfig()['safe_mode_code']) && $this->getKamodConfig()['safe_mode_code'] == 'test1a', 'safe mode: the code is kept after the kamod cache rebuild');

		$this->saveSafeModeCode('test2b');
		$this->check($this->hasMenuItem($this->sessionDashboard('safe1')['body']), 'safe mode: the old code stops working after the code is changed');
		$this->check(!$this->hasMenuItem($this->openSafeMode('safe2', 'test2b')['dashboard']['body']), 'safe mode: the new code works at once');

		$this->openSafeMode('safe4', 'test2b');
		$prev = $this->switchSession('safe4');
		$this->httpUrl($this->base_url . 'admin/index.php?route=ka_safe_mode');
		$this->restoreSession($prev);
		$this->check($this->hasMenuItem($this->sessionDashboard('safe4')['body']), 'safe mode: the exit URL turns safe mode off');

		$this->saveSafeModeCode('');
		$this->check($this->hasMenuItem($this->sessionDashboard('safe2')['body']) && $this->hasMenuItem($this->openSafeMode('safe3', 'test2b')['dashboard']['body']), 'safe mode: an empty code disables safe mode');
		$this->checkNoErrors($this->httpUrl($this->base_url . 'index.php'), 'safe mode: storefront works');
	}

	protected function testStorefront() {
		$this->checkNoErrors($this->httpUrl($this->base_url . 'index.php'), 'storefront home');
		$this->checkNoErrors($this->httpUrl($this->base_url . 'index.php?route=product/category&path=20'), 'storefront category');
	}

	protected function testUninstall() {
		$id = $this->value("SELECT modification_id FROM oc_modification WHERE code = 'ka_extensions'");
		if ($id) {
			$this->http('marketplace/modification.disable', null, 'modification_id=' . $id);
		}
		$error = $this->runInstaller('marketplace/installer.uninstall');
		$this->check($error === '', 'package is uninstalled' . ($error ? ": $error" : ''));
		$this->check(!is_dir($this->store_dir . '/extension/ka_extensions/engine'), 'extension files are removed');
		$this->check($this->value("SELECT COUNT(*) FROM oc_modification WHERE code = 'ka_extensions'") == 0, 'modification is removed');
		$this->http('marketplace/modification.refresh');

		$resp = $this->getMenuHtml(false);
		$this->checkNoErrors($resp, 'admin dashboard after uninstall');
		$this->check(!$this->hasMenuItem($resp['body']), "'Ka Extensions' menu item is gone");
		$this->checkNoErrors($this->httpUrl($this->base_url . 'index.php'), 'storefront home after uninstall');
	}

	/*
		Starts the test license server (fixtures/license_server/router.php) and points KA_STORE_URL of the
		admin config to it. Returns [config file, log file].
	*/
	protected function startLicenseServer() {
		$port = (int)$this->opt['port'] + 1000;
		$config = $this->opt['work-dir'] . '/license_server.json';
		$log    = $this->opt['work-dir'] . '/license_server.log';
		file_put_contents($config, '{}');
		file_put_contents($log, '');
		exec('KA_TEST_LICENSE_SERVER_CONFIG=' . escapeshellarg($config) . ' KA_TEST_LICENSE_SERVER_LOG=' . escapeshellarg($log)
			. " nohup php -S 127.0.0.1:$port " . escapeshellarg(__DIR__ . '/fixtures/license_server/router.php')
			. ' > ' . escapeshellarg($this->opt['work-dir'] . '/license_server.out') . ' 2>&1 &');
		for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
			usleep(100000);
		}

		$admin_config = $this->store_dir . '/admin/config.php';
		$text = file_get_contents($admin_config);
		if (strpos($text, 'KA_STORE_URL') === false) {
			file_put_contents($admin_config, $text . "\ndefine('KA_STORE_URL', 'http://127.0.0.1:$port/');\n");
			// the opcache of the store server checks timestamps of files every 2 seconds
			sleep(3);
		}

		// test modules: only install.json is needed for the list of modules
		foreach (array('ka_lic_paid', 'ka_lic_free', 'ka_lic_expired', 'ka_lic_dup') as $code) {
			@mkdir($this->store_dir . '/extension/' . $code, 0777, true);
			file_put_contents($this->store_dir . '/extension/' . $code . '/install.json',
				json_encode(array('name' => 'License test ' . $code, 'version' => '1.0.0.0', 'ka_type' => 'ka_extensions')));
		}

		return array($config, $log, $port);
	}

	protected function stopLicenseServer($port) {
		exec("pkill -f 'php -S 127.0.0.1:" . $port . "'");
		$admin_config = $this->store_dir . '/admin/config.php';
		file_put_contents($admin_config, preg_replace("/\ndefine\('KA_STORE_URL'[^\n]*\n/", '', file_get_contents($admin_config)));
		sleep(3); // opcache checks the files every 2 seconds
		foreach (array('ka_lic_paid', 'ka_lic_free', 'ka_lic_expired', 'ka_lic_dup') as $code) {
			$this->sh('rm -rf ' . escapeshellarg($this->store_dir . '/extension/' . $code));
		}
		$this->query("DELETE FROM oc_setting WHERE `code` = 'kareg'");
	}

	protected function licenseRequests($log) {
		$result = array();
		foreach (file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$result[] = json_decode($line, true);
		}
		$old = array_intersect(array_column($result, 'route'), array('extension/domain_info', 'extension/register_key'));
		$this->check(empty($old), 'the old scripts domain_info, register_key are not called ' . implode(', ', $old));
		file_put_contents($log, '');
		return $result;
	}

	protected function kareg($code) {
		$value = $this->value("SELECT `value` FROM oc_setting WHERE `code` = 'kareg' AND `key` = 'kareg" . $this->db->real_escape_string($code) . "'");
		return $value ? json_decode($value, true) : null;
	}

	/*
		The license requests of the Ka Extensions page: the API of ka_licenses (api/checkModules with all modules
		and the edition oc4, api/linkLicense), the old domain_info / register_key scripts
		are never called, a license server that is not available (also an old server: 404 of the API).
	*/
	protected function testLicenseServer() {
		list($config, $log, $port) = $this->startLicenseServer();
		$api = 'extension/ka_extensions/ka_licenses/api/';
		$states = array('ka_lic_paid' => 'registered', 'ka_lic_free' => 'free', 'ka_lic_expired' => 'expired', 'ka_lic_dup@oc3' => 'registered');

		// the new server
		file_put_contents($config, json_encode(array('mode' => 'new', 'modules' => $states, 'key' => 'GOOD-KEY')));
		$resp = $this->http('extension/ka_extensions/extensions');
		$this->checkNoErrors($resp, 'Ka Extensions page with the new license server');
		$requests = $this->licenseRequests($log);
		$this->check(count($requests) == 1 && $requests[0]['route'] == $api . 'checkModules', 'one api/checkModules request ' . json_encode($requests));
		$post = $requests[0]['post'];
		$this->check(array_keys($post) == array('api_version', 'url', 'opencart_version', 'modules'), 'only the fields of checkModules');
		$this->check(($post['api_version'] ?? '') === '1' && ($post['opencart_version'] ?? '') === $this->getOcVersion(), 'api_version and opencart_version are sent');
		$found = array();
		foreach ($post['modules'] ?? array() as $module) {
			$found[$module['code']] = $module;
		}
		$this->check(count(array_intersect_key($found, array_flip(array('ka_lic_paid', 'ka_lic_free', 'ka_lic_expired', 'ka_lic_dup')))) == 4, 'all modules are sent');
		$this->check(($found['ka_lic_paid'] ?? null) === array('vendor' => 'ka_extensions', 'code' => 'ka_lic_paid', 'edition' => 'oc4'), 'vendor, code and edition oc4');
		$this->check(!empty($this->kareg('ka_lic_paid')['is_registered']) && ($this->kareg('ka_lic_paid')['expiry_date'] ?? '') == '2030-01-01', 'registered module is saved with the expiry date');
		$this->check(!empty($this->kareg('ka_lic_free')['is_free']), 'free module');
		$this->check(is_null($this->kareg('ka_lic_expired')), 'expired module is not registered');
		$this->check(is_null($this->kareg('ka_lic_dup')), 'the same code of the oc3 edition does not register the oc4 module');

		// registration of a key
		foreach (array('new' => array($api . 'linkLicense')) as $mode => $routes) {
			$this->query("DELETE FROM oc_setting WHERE `code` = 'kareg'");
			file_put_contents($config, json_encode(array('mode' => $mode, 'modules' => array(), 'key' => 'GOOD-KEY')));
			$resp = $this->http('extension/ka_extensions/extensions.activateKey', array('license_key' => 'WRONG', 'extension' => 'ka_lic_paid'));
			$json = json_decode($resp['body'], true);
			$this->check(!empty($json['error']), "$mode server: a wrong key is refused");
			$this->check(array_column($this->licenseRequests($log), 'route') == $routes, "$mode server: the requests of a wrong key");
			$resp = $this->http('extension/ka_extensions/extensions.activateKey', array('license_key' => 'GOOD-KEY', 'extension' => 'ka_lic_paid'));
			$json = json_decode($resp['body'], true);
			$this->check(!empty($json['redirect']), "$mode server: the right key is registered " . $resp['body']);
			$requests = $this->licenseRequests($log);
			$this->check(array_column($requests, 'route') == $routes, "$mode server: the requests " . json_encode($requests));
			$this->check($requests[0]['post'] == array('api_version' => '1', 'license_key' => 'GOOD-KEY', 'surface_uri' => $this->base_url, 'vendor' => 'ka_extensions',
					'code' => 'ka_lic_paid', 'edition' => 'oc4', 'opencart_version' => $this->getOcVersion()), 'linkLicense fields ' . json_encode($requests[0]['post']));
			$this->check(!empty($this->kareg('ka_lic_paid')['is_registered']), "$mode server: the registration is saved");
		}

		// the license server is not available: nothing is changed, modules are not disabled or shown as unlicensed
		$saved = array('masked_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX', 'expiry_date' => '2030-01-01',
			'is_registered' => 1, 'url' => $this->base_url);
		foreach (array('old', 'error', '500', 'empty', 'invalid', 'timeout', 'down') as $mode) {
			$this->query("DELETE FROM oc_setting WHERE `code` = 'kareg'");
			$this->query("INSERT INTO oc_setting SET store_id = 0, `code` = 'kareg', `key` = 'karegka_lic_paid', `value` = '"
				. $this->db->real_escape_string(json_encode($saved)) . "', serialized = 1");
			$extensions = $this->query("SELECT * FROM oc_extension ORDER BY extension_id");
			$settings   = $this->query("SELECT `code`, `key`, `value` FROM oc_setting WHERE `code` <> 'kareg' ORDER BY setting_id");
			if ($mode == 'down') {
				exec("pkill -f 'php -S 127.0.0.1:" . $port . "'");
				usleep(300000);
			} else {
				file_put_contents($config, json_encode(array('mode' => $mode, 'modules' => array('ka_lic_paid' => 'registered'), 'key' => 'GOOD-KEY')));
			}
			$resp = $this->http('extension/ka_extensions/extensions');
			$this->checkNoErrors($resp, "$mode: Ka Extensions page");
			if ($mode != 'down') {
				$this->check(array_column($this->licenseRequests($log), 'route') == array($api . 'checkModules'), "$mode: one request to the API");
			}
			$this->check(strpos($resp['body'], 'IJKL-XXXX-XXXX-XXXX-XXXX') !== false, "$mode: the module is shown as registered");
			$this->check($this->kareg('ka_lic_paid') === $saved, "$mode: the saved state is kept");
			$this->check($this->query("SELECT * FROM oc_extension ORDER BY extension_id") === $extensions, "$mode: no extension is uninstalled");
			$this->check($this->query("SELECT `code`, `key`, `value` FROM oc_setting WHERE `code` <> 'kareg' ORDER BY setting_id") === $settings, "$mode: settings (statuses of modules) are not changed");
			$resp = $this->http('extension/ka_extensions/extensions.activateKey', array('license_key' => 'GOOD-KEY', 'extension' => 'ka_lic_paid'));
			$json = json_decode($resp['body'], true);
			$this->check(!empty($json['error']) && $this->kareg('ka_lic_paid') === $saved, "$mode: the registration fails with a message, nothing is changed");
			$this->checkNoErrors($resp, "$mode: registration");
			if ($mode != 'down') {
				$this->check(array_column($this->licenseRequests($log), 'route') == array($api . 'linkLicense'), "$mode: registration: one request to the API");
			}
		}

		$this->stopLicenseServer($port);
	}

	public function run() {
		$this->testPackage();
		$this->setup();
		$tests = array('testInstall', 'testModification', 'testMenu', 'testLicenseServer', 'testSafeMode', 'testStorefront', 'testUninstall');
		if (!empty($this->opt['tests'])) {
			$tests = explode(',', $this->opt['tests']);
		} elseif (isset($this->opt['keep'])) {
			array_pop($tests);
		}
		foreach ($tests as $test) {
			$this->log($test);
			$this->$test();
		}
		if (!isset($this->opt['keep'])) {
			$this->stopServer();
		}
		$this->log(sprintf("\nOpenCart %s: %d passed, %d failed", $this->getOcVersion(), $this->passed, $this->failures));
		return $this->failures ? 1 : 0;
	}
}

$opt = getopt('', array('oc:', 'work-dir:', 'port:', 'db:', 'tests:', 'keep'));
if (empty($opt['oc'])) {
	fwrite(STDERR, "Usage: php tests/run.php --oc=<opencart zip or dir> [--port=8096] [--work-dir=...] [--db=...] [--tests=testInstall,testSafeMode] [--keep]\n");
	exit(1);
}
$opt += array('port' => '8096');
$opt += array(
	'work-dir'    => sys_get_temp_dir() . '/ka_ext_test_' . $opt['port'],
	'db'          => 'kat_ext_' . $opt['port'],
	'db_host'     => getenv('KA_TEST_DB_HOST') ?: 'localhost',
	'db_user'     => getenv('KA_TEST_DB_USER') ?: 'root',
	'db_password' => getenv('KA_TEST_DB_PASSWORD') ?: '',
);

$opt['package'] = $opt['work-dir'] . '_ka_extensions.ocmod.zip';
passthru('php ' . escapeshellarg(__DIR__ . '/build-package.php') . ' ' . escapeshellarg($opt['package']), $ret);
if ($ret) {
	exit(1);
}

$test = new KaExtTest($opt);
exit($test->run());
