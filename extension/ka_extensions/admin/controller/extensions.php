<?php
/* 
 $Project: Ka Extensions $
 $Author: karapuz team <support@ka-station.com> $
 $Version: 4.1.1.0 $ ($Revision: 285 $) 
*/

namespace Opencart\Admin\Controller\Extension\KaExtensions;

use \Opencart\Extension\KaExtensions\System\Curl;
use \Opencart\Extension\KaExtensions\System\KaGlobal;
use \Opencart\Extension\KaExtensions\System\ControllerInstaller;
use \extension\ka_extensions\KamodManager;

/**
	The 'Ka Extensions' admin page: lists Ka extensions and installs, uninstalls and rebuilds them.

	@internal
*/
class Extensions extends \Opencart\Extension\KaExtensions\System\Controller {

	protected function onLoad() {
		$this->load->language('extension/ka_extensions/common');
		$this->load->language('extension/ka_extensions/extensions');
		$this->load->model('extension/ka_extensions/common');

		parent::onLoad();
	}
	

	public function index() {
		$this->getList();
	}
	
			
	protected function getList() {

		$this->updateInfoByDomain();

		$installed_extension_codes = array();		
		$installed_extensions = $this->model_extension_ka_extensions_common->getInstalledByType('ka_extensions');

		if (!empty($installed_extensions)) {
			$installed_extension_codes = array_keys($installed_extensions);
			foreach ($installed_extensions as $key => $value) {
				if (!file_exists(DIR_EXTENSION . $key)) {
					$this->model_setting_extension->uninstall('ka_extensions', $key);
					unset($installed_extensions[$key]);
				}
			}
		}
	
		$this->data['extensions'] = array();
		$files = glob(DIR_EXTENSION . '*/install.json');
		
		if ($files) {
			foreach ($files as $file) {
			
				$contents = file_get_contents($file);
			
				$info = json_decode($contents, JSON_OBJECT_AS_ARRAY);
				if (empty($info)) {
					continue;
				}

				if (empty($info['ka_type']) || $info['ka_type'] != 'ka_extensions') {
					continue;
				}
				
				$code = basename(dirname($file));
				
				$ext = array(
					'name'      => $info['name'],
					'extension' => $code,
				);

				$keys = array('name', 'link','documentation_link', 'ka_license','version');
				foreach($keys as $v) {
					if (!empty($info[$v])) {
						$ext[$v] = $info[$v];
					}
				}

				$ext['is_registered'] = $this->model_extension_ka_extensions_common->isRegistered($code);
				$ext = array_merge($ext, $this->model_extension_ka_extensions_common->getExtensionInfo($code));

				if (!empty($ext['expiry_date'])) {
					$ext['expiry_date'] = date($this->language->get('date_format_long'), strtotime($ext['expiry_date']));
				}
				
				if (!in_array($code, $installed_extension_codes)) {
					$action['install'] = array(
						'text' => $this->language->get('button_install'),
						'href' => $this->url->linka('extension/ka_extensions/extensions|install', 'extension=' . $code)
					);
					
				} else {
					$ext['is_installed'] = true;
					$action['edit'] = array(
						'text' => $this->language->get('button_edit'),
						'href' => $this->url->linka('extension/' . $code . '/extension')
					);
					
					$action['uninstall'] = array(
						'text' => $this->language->get('button_uninstall'),
						'href' => $this->url->linka('extension/ka_extensions/extensions|uninstall', 'extension=' . $code)
					);
				}
				
				$ext['action'] = $action;
				
				$this->data['extensions'][] = $ext;
			}
		}

		//
		// view output
		// 
		$this->data['heading_title']   = $this->language->get('Ka Extensions');
		$this->data['text_confirm']    = $this->language->get('text_confirm');

		$this->data['extension_version'] = ControllerInstaller::$ka_extensions_version;
		
		$this->document->setTitle($this->data['heading_title']);
		
		$this->data['http_catalog'] = HTTP_CATALOG;
		$this->data['oc_version']   = VERSION;
		
		$this->data['breadcrumbs'] = array();
		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->linka('common/dashboard')
		);
		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get('Ka Extesions'),
			'href' => $this->url->linka('extension/ka_extensions/extensions')
		);

		$this->data['ka_station_url'] = KaGlobal::getKaStoreURL();

		$this->data['user_token'] = $this->session->data['user_token'];
		
		$this->data['is_ka_cache_valid'] = KamodManager::getInstance()->isKamodCacheValid();

		// safe mode
		//
		$config = KamodManager::getConfig();
		$this->data['safe_mode_code']        = isset($config['safe_mode_code']) ? $config['safe_mode_code'] : '';
		$this->data['safe_mode_url']         = HTTP_SERVER . 'index.php?route=ka_safe_mode&code=';
		$this->data['action_save_safe_mode'] = $this->url->linka('extension/ka_extensions/extensions|saveSafeModeCode');
		
		$this->showPage('extension/ka_extensions/extensions');
	}


	/*
		The same users who always see this page (see kamod startup/permission) or users with the modify
		permission for this page can change the safe mode code.
	*/
	protected function canModifySafeMode() {
		if ($this->user->hasPermission('modify', 'extension/ka_extensions/extensions')) {
			return true;
		}
		return $this->user->hasPermission('access', 'user/user_permission')
			&& $this->user->hasPermission('modify', 'user/user_permission');
	}


	/*
		Saves the safe mode code to the kamod config file. An empty code disables the safe mode.
	*/
	public function saveSafeModeCode() {

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {

			$code = isset($this->request->post['safe_mode_code']) ? trim((string)$this->request->post['safe_mode_code']) : '';

			if (!$this->canModifySafeMode()) {
				$this->addTopMessage($this->language->get('error_permission'), 'E');

			} elseif (!preg_match('/^[A-Za-z0-9]*$/', $code)) {
				$this->addTopMessage($this->language->get('error_safe_mode_code'), 'E');

			} else {
				$config = KamodManager::getConfig();
				if (empty($config['admin_dir'])) {
					$config['admin_dir'] = basename(DIR_APPLICATION);
				}
				$config['safe_mode_code'] = $code;
				KamodManager::saveConfig($config);

				$this->addTopMessage($this->language->get('txt_safe_mode_code_saved'));
			}
		}

		$this->response->redirect($this->url->linka('extension/ka_extensions/extensions'));
	}

	
	public function install() {

		$success = $this->load->controller('extension/' . $this->request->get['extension'] . '/extension|install', []);
		if ($success) {
			$this->model_setting_extension->install('ka_extensions', 'ka_extensions', $this->request->get['extension']);
			$this->addTopMessage($this->language->get('txt_installation_successful'));
		} else {
			$this->addTopMessage($this->language->get("txt_installation_failed"), 'E');
		}
		
		$this->response->redirect($this->url->linka('extension/ka_extensions/extensions'));
	}

	
	public function uninstall() {
	
		$success = $this->load->controller('extension/' . $this->request->get['extension'] . '/extension|uninstall', []);
		if ($success) {
			$this->model_setting_extension->uninstall('ka_extensions', $this->request->get['extension']);
			$this->addTopMessage($this->language->get('txt_uninstallation_successful'));
		} else {
			$this->addTopMessage($this->language->get('txt_uninstallation_failed'), 'E');
		}
		
		$this->response->redirect($this->url->linka('extension/ka_extensions/extensions'));
	}

	/*
		This function shows a 'License Registration' dialog
	*/	
	public function inputKey() {

		$this->data['user_token'] = $this->session->data['user_token'];
		$this->data['extension']  = $this->request->get['extension'];
	
		$this->template = 'extension/ka_extensions/input_key';

		$this->response->setOutput($this->render());
	}
	

	/*
		This function processes input of 'Extension Registration' dialog
	*/
	public function activateKey() {

		$json = array();
	
		$key       = $this->request->post['license_key'];
		$extension = $this->request->post['extension'];
		
		$ini = $this->model_extension_ka_extensions_common->loadManifest($extension);
		$edition = $ini['edition'] ?? null;
		
		if ($this->model_extension_ka_extensions_common->registerKey($key, $extension, $edition)) {
			$json['redirect'] = $this->url->linka('extension/ka_extensions/extensions', null, true);
			$this->addTopMessage('The license key was validated successfully.');
			$this->response->addHeader('Content-Type: application/json');	
			$this->response->setOutput(json_encode($json));
			return;
		}

		$json['error'] = $this->model_extension_ka_extensions_common->getLastError();
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	/*	
		Retrieve all extension information by domain.
		
		At this time it is supposed to return an array of registered extensions only. But maybe
		it will change in the future.
		
	*/
	protected function getInfoByDomain() {

		// the new request; null - an error or the server does not support it, the old request is sent
		//
		$extensions = $this->model_extension_ka_extensions_common->checkModules();
		if (!is_null($extensions)) {
			return $extensions;
		}

		$kacurl = new Curl();

		
		$request_url = KaGlobal::getKaStoreURL() . "?route=extension/domain_info";
		
		$data = array(
			'url' => HTTP_CATALOG
		);
		$result = $kacurl->request($request_url, $data);
		
		$info = var_export($request_url, true) . var_export($result, true) . var_export($data, true);
		
		// process the response from the remote server
		//
		if (empty($result)) {
			$this->lastError = 'A request to the license registration server failed with this error:'
				. $kacurl->getLastError();
				

			$this->log->write($this->lastError . ' extra:' . $info);
				
			return null;
		}
		
		$result = json_decode($result, true);
		if (!empty($result['error'])) {
			$this->lastError = $result['error'];
			return null;
		}

		if (empty($result['result']) || $result['result'] != 'ok') {
			$this->lastError = 'Server response does not contain a successful result.';
			return null;
		}
		
		if (!isset($result['extensions'])) {
			$this->lastError = 'Unknwon result format.';
			return null;
		}

		return $result['extensions'];
	}
	
	/*
		This function is called periodically to update the registration information of all extensions
		in the database. An updated info is retrieved from the ka-station server.
	*/
	protected function updateInfoByDomain() {
		$registered_extensions = $this->getInfoByDomain();
		if (!$this->model_extension_ka_extensions_common->saveRegAll($registered_extensions)) {
			$this->log->write("saveReg failed." . $this->model_extension_ka_extensions_common->getLastError());
			return false;
		}
		
		return true;
	}
}