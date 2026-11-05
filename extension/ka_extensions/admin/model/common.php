<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace Opencart\Admin\Model\Extension\KaExtensions;

use \Opencart\Extension\KaExtensions\System\Curl;
use \Opencart\Extension\KaExtensions\System\KaGlobal;

/**
	Common functions of the Ka Extensions admin pages: manifests, extension lists and settings.

	@internal
*/
class Common extends \Opencart\Extension\KaExtensions\System\Model {

	protected $kacurl;

	// the version of the license API of ka_licenses (api/checkModules, api/linkLicense)
	protected $license_api_version = 1;

	// the route of the license API of the ka_licenses module on the license server
	const LICENSE_API_ROUTE = 'extension/ka_extensions/ka_licenses/api/';


	protected function onLoad() {

		$this->kacurl = new Curl();
		
		$this->load->model('setting/setting');
		
		return true;
	}
	
	public function getExtensionInfo($extension) {
	
		$return = array();

		$ka_reg = $this->model_setting_setting->getSetting('kareg');

		$key = $this->getRegKey($extension);
		
		if (empty($ka_reg) || empty($ka_reg[$key])) {
			return $return;
		}
		
		return $ka_reg[$key];
	}

	
	public function getInstalledByType($type) {
	
		$extension_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension 
			WHERE `type` = '" . $this->db->escape($type) . "'
		");
		
		if (empty($query->rows)) {
			return $extension_data;
		}
		
		foreach ($query->rows as $row) {
			$extension_data[$row['code']] = $row;
		}
		
		return $extension_data;
	}

	
/*
	Returns

		$ka_st_data = array(
			'result' => 'OK'
		);

		$ka_st_data = array(
			'result'  => 'ERROR',
			'message' => 'The code is not valid.'
		);
	
*/	
	public function registerKey($key, $extension, $edition = null) {

		$ret = '';
		
		// send code to the remote server
		//
		$data = array(
			'key'       => $key,
			'url'       => HTTP_CATALOG,
			'extension' => $extension,
		);
		
		if (!empty($edition)) {
			$data['edition'] = $edition;
		}

		// the API of ka_licenses first, the old script for an old license server
		//
		$linked = $this->linkLicense($key, $extension, $edition);
		if (is_bool($linked)) {
			if ($linked) {
				$data['is_registered'] = true;
				if (!$this->saveReg($extension, $data)) {
					$this->lastError = 'saveReg failed';
					return false;
				}
			}
			return $linked;
		}

		$request_url = KaGlobal::getKaStoreURL() . '?route=extension/register_key';

		$result = $this->kacurl->request($request_url, $data);

		// process the response from the remote server
		//
		if (empty($result)) {
			$this->lastError = 'A request to the license registration server failed with this error:' 
				. $this->kacurl->getLastError()
				. '<br />Please try again later.<br /><br />If you cannot activate the license key within 24 ' 
				. 'hours please contact us at support@ka-station.com.
			';
				
			return false;
		}
		
		$result = json_decode($result, true);

		if (!empty($result['error'])) {
			$this->lastError = $result['error'];
			return false;
		}	


		
		if (empty($result['ext_code'])) {
			$this->lastError = 'Wrong request parameters:' . var_export($result, true);
			return false;
		}
		
		$data['is_registered'] = true;
		if (!$this->saveReg($result['ext_code'], $data)) {
			$this->lastError = 'saveReg failed';
			return false;
		}
		
		return true;
	}
	

	/*
		Links the license key to the store with the API of ka_licenses (api/linkLicense, api_version 1).
		Returns true / false (lastError is set) for the answer of the new server, null when the server
		does not answer in the format of the API (an old ka-station, the server is not available): the caller
		sends the old register_key request then.
	*/
	protected function linkLicense($key, $extension, $edition = null) {

		$data = array(
			'api_version'      => $this->license_api_version,
			'license_key'      => $key,
			'surface_uri'      => HTTP_CATALOG,
			'vendor'           => 'ka_extensions',
			'code'             => $extension,
			'edition'          => empty($edition) ? $this->getStoreEdition() : $edition,
			'opencart_version' => VERSION,
		);

		$result = $this->requestLicenseApi('linkLicense', $data);
		if (is_null($result)) {
			return null;
		}

		if ($result['result'] != 'ok') {
			$this->lastError = empty($result['message']) ? 'The license key was not accepted.' : $result['message'];
			return false;
		}

		return true;
	}


	/*
		Sends a request to the API of ka_licenses. Returns the decoded answer with api_version and result or null.
	*/
	protected function requestLicenseApi($action, $data) {

		$result = $this->kacurl->request(KaGlobal::getKaStoreURL() . '?route=' . self::LICENSE_API_ROUTE . $action, $data);
		$result = empty($result) ? null : json_decode($result, true);
		if (!is_array($result) || !isset($result['api_version']) || empty($result['result'])) {
			return null;
		}

		return $result;
	}


	/*
		Returns the edition of license codes for this store: 'oc4' for Opencart 4.x
	*/
	public function getStoreEdition() {
		return 'oc' . (int)VERSION;
	}


	/*
		Returns modules of Ka Extensions in the store (extension folders with 'ka_type' = 'ka_extensions' in
		install.json): [code => ['vendor' => .., 'code' => .., 'edition' => ..]]. The edition comes from
		install.json ('edition'), the store edition by default.
	*/
	public function getLicenseModules() {

		$modules = array();
		foreach (glob(DIR_EXTENSION . '*/install.json') ?: array() as $file) {
			$info = json_decode((string)file_get_contents($file), true);
			if (empty($info['ka_type']) || $info['ka_type'] != 'ka_extensions') {
				continue;
			}
			$code = basename(dirname($file));
			$modules[$code] = array(
				'vendor'  => 'ka_extensions',
				'code'    => $code,
				'edition' => empty($info['edition']) ? $this->getStoreEdition() : $info['edition'],
			);
		}

		return $modules;
	}


	/*
		Requests the state of the modules from the API of ka_licenses (api/checkModules, api_version 1).
		Returns extensions in the format of the old domain_info answer (see saveRegAll()):
			[code => ['is_registered' => 1, ...] or ['is_free' => 1, ...]]
		Returns null when the server does not answer in the format of the API or on an error,
		the caller sends the old domain_info request then.
	*/
	public function checkModules() {

		$modules = $this->getLicenseModules();

		$data = array(
			'api_version'      => $this->license_api_version,
			'url'              => HTTP_CATALOG,
			'opencart_version' => VERSION,
			'modules'          => array_values($modules),
		);

		$result = $this->requestLicenseApi('checkModules', $data);
		if (is_null($result) || $result['result'] != 'ok' || !isset($result['modules']) || !is_array($result['modules'])) {
			return null;
		}

		return $this->getExtensionsByModules($result['modules'], $modules);
	}


	/*
		Converts modules of the checkModules answer to the old extensions format. Unregistered and expired
		modules are not in the list (as in the old format).
	*/
	protected function getExtensionsByModules($response_modules, $modules) {

		$codes = array_keys($modules);

		$extensions = array();
		foreach (array_values($response_modules) as $idx => $module) {
			if (!isset($codes[$idx]) || empty($module['status'])) {
				continue;
			}
			$code = $codes[$idx];

			if ($module['status'] == 'registered') {
				$masked_key = isset($module['masked_key']) ? $module['masked_key'] : '';
				$extensions[$code] = array(
					'masked_key'    => $masked_key,
					'license_key'   => $masked_key,
					'is_registered' => 1,
				);
				if (!empty($module['expiry_at'])) {
					$extensions[$code]['expiry_date'] = substr($module['expiry_at'], 0, 10);
				}
			} elseif ($module['status'] == 'free') {
				$extensions[$code] = array(
					'license_key' => '',
					'masked_key'  => '',
					'expiry_date' => '',
					'is_free'     => 1,
				);
			}
		}

		return $extensions;
	}


	public function isRegistered($extension_code) {
		$ka_reg = $this->model_setting_setting->getSetting('kareg');

		if (empty($ka_reg)) {
			return false;
		}

		$key = $this->getRegKey($extension_code);
		
		if (isset($ka_reg[$key]['is_registered'])) {
			return true;
		}
		
		return false;
	}

	
	public function saveRegAll($data = array()) {

		if ($data === null) {
			return true;
		}
		
		// get installed extensions
		//
		$installed_extensions = $this->getInstalledByType('ka_extensions');
		$installed_extension_codes = array_keys($installed_extensions);
		
		$url = HTTP_CATALOG;
		$kareg = array(
			'kareg' => 1
		);
		
		// get existing registrations
		//
		$existing = $this->model_setting_setting->getSetting('kareg');
		
		if (!empty($data)) {
			foreach ($data as $k => $v) {
			
				$v['url']           = $url;
				$v['is_registered'] = 1;
			
				$key = $this->getRegKey($k);
				$kareg[$key] = $v;
				
				if (!empty($existing[$key])) {
					unset($existing[$key]);
				}
			}
		}
		
		// copy installed licenses to the new array
		//
		if (!empty($existing['kareg'])) {
			unset($existing['kareg']);
		}
		
		if (!empty($existing)) {
			foreach ($existing as $ek => $ev) {
				$ext_code = substr($ek, 5);
				if (in_array($ext_code, $installed_extension_codes)) {
					$ev['is_wrong_license'] = true;
					$kareg[$ek] = $ev;
				}
			}
		}

		$this->model_setting_setting->editSetting('kareg', $kareg);
		
		return true;
	}

	
	public function saveReg($ext_code, $data = array()) {

		$ka_reg = $this->model_setting_setting->getSetting('kareg');

		$url = HTTP_CATALOG;
		$key = $this->getRegKey($ext_code);
		
		$ka_reg[$key] = array();
		
		if (isset($data['is_registered'])) {
			$ka_reg[$key]['is_registered'] = 1;
			$ka_reg[$key]['url'] = $url;
		}
		
		$this->model_setting_setting->editSetting('kareg', $ka_reg);
		
		return true;
	}	

	
	/*
		Service function for updating or creating a new Opencart setting
	*/
	public function saveSetting($code, $key, $value) {
		$this->load->model('setting/setting');
		
		$setting = $this->model_setting_setting->getSetting($code);
		$setting[$key] = $value;
		$this->model_setting_setting->editSetting($code, $setting);
	}	
	
	
	protected function getRegKey($code) {
		$result = 'kareg' . $code;
		return $result;
	}
	
	
	public function loadManifest($ext_code) {
	
		$ini_file = DIR_OPENCART . 'extension/' . $ext_code . '/install.json';

		if (!file_exists($ini_file)) {
			return array();
		}

		$ini = file_get_contents($ini_file);		
		$ini = json_decode($ini, true);
		
		return $ini;
	}
	

	/**
		Patches system/framework.php file to include its ocmod-modified version where we insert our code
		for extending autoloader with our kamod feature
	*/
	public function checkFrameworkPatch() {
	
		$filename = DIR_SYSTEM . 'framework.php';
	
		if (!is_file($filename)) {
			throw new \RuntimeException('Framework file does not exist: ' . $filename);
		}

		if (!is_readable($filename)) {
			throw new \RuntimeException('Framework file is not readable: ' . $filename);
		}

		if (!is_writable($filename)) {
			throw new \RuntimeException('Framework file is not writable: ' . $filename);
		}

		$content = file_get_contents($filename);
		if ($content === false) {
			throw new \RuntimeException('Failed to read framework file: ' . $filename);
		}

		// Patch has already been applied
		if (strpos($content, "require_once(DIR_EXTENSION . '/ocmod/system/framework.php')") !== false) {
			return;
		}
		
		$patch = <<<'PATCH'
//ka: required by kamod
if (!defined('KAMOD_INCLUDED_FRAMEWORK') && file_exists(DIR_EXTENSION . '/ocmod/system/framework.php')) {
	define('KAMOD_INCLUDED_FRAMEWORK', true);
	;	require_once(DIR_EXTENSION . '/ocmod/system/framework.php');
	return;
}
///ka
PATCH;

		// Preserve "<?php" but make sure our patch starts on the next line.
		$newContent = str_replace("<?php", "<?php\n" . $patch, $content);

		// Optional backup before modifying
		$backup = $filename . '.' . oc_token(8) . '.bak';

		if (!file_exists($backup)) {
			if (!copy($filename, $backup)) {
				throw new \RuntimeException('Failed to create backup: ' . $backup);
			}
		}

		if (file_put_contents($filename, $newContent, LOCK_EX) === false) {
			throw new \RuntimeException('Failed to patch framework file: ' . $filename);
		}

		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($filename, true);
		}
	}	
}