<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 286 $)
*/
	
namespace Opencart\Extension\KaExtensions\System;

/**
	This class contains essential parts of the module installation. It reads the extension manifest
	(install.json), checks compatibility with the Ka Extensions library and the database, creates
	tables and grants permissions to the extension pages. Module settings pages inherit from it
	through ControllerSettings.

	@package Controller
*/
abstract class ControllerInstaller extends Controller {

	// contstants
	//
	public static $ka_extensions_version = '5.1.1.1';

	// this field has to be specified by the extension
	protected $ext_code  = '';

	// these fields are filled in from the manifest
	protected $min_ka_extensions_version = '0.0.0.0';
	protected $extension_version         = '1.0.0.0';
	protected $ext_link  = '';
	protected $docs_link = '';

	protected $tables;
	protected $ini;
	
	protected $kamodel_common;
	protected $kamodel_patchdb;
	
	/**
	 Returns a list of extension routes which should get access and modify permissions on extension
	 installation. The routes are relative to 'extension/<ext_code>/'.

	 @return array
	*/
	protected function getExtensionPages() {
		return array();
	}
	
	/**
	 Loads required models and the extension manifest (install.json) and fills in the extension
	 version, links and the minimum Ka Extensions version from it.

	 @throws \Exception If ext_code is not defined or the manifest is not found.
	 @return void
	*/
	protected function onLoad() {
	
		if (empty($this->ext_code)) {
			throw new \Exception("ext_code property is not defined for the extension");
		}
	
		$this->kamodel_common = $this->load->kamodel('extension/ka_extensions/common');
		$this->kamodel_patchdb = $this->load->kamodel('extension/ka_extensions/patchdb');
		
		$this->load->model('setting/setting');
		$this->load->model('user/user_group');

		parent::onLoad();

		$this->ini = $this->model_extension_ka_extensions_common->loadManifest($this->ext_code);
		
		if (empty($this->ini)) {
			$this->log->write($error = "Manifest (install.json) was not found for " . get_class($this));
			throw new \Exception($error);
		}

		$this->extension_version = $this->ini['version'];
		$this->ext_link          = $this->ini['link'];
		$this->min_ka_extensions_version = $this->ini['ka_extensions_version'];
		
		if (!empty($this->ini['documentation_link'])) {
			$this->docs_link = $this->ini['documentation_link'];
		}
	}	
	
	/**
	 Checks that the extension is compatible with the installed Ka Extensions library and the database.

	 $tables — tables definition to check.
	 $messages — receives error messages.

	 @return bool True if the extension is compatible.
	*/
	protected function checkCompatibility(&$tables, &$messages) {
	
		// check ka_extensions version 
		if (version_compare(static::$ka_extensions_version, $this->min_ka_extensions_version, '<')) {
			$messages[] = "The module is not compatible with the installed Ka Extensions library.
				The minimum Ka Extensions library version is " . $this->min_ka_extensions_version .
				". Please update the Ka Extensions library up to the latest version.";
			return false;
		}
		
		$max_version = explode(".", $this->min_ka_extensions_version);
		$max_version[3] = 99;
		$max_version = implode(".", $max_version);
		
		if (version_compare(static::$ka_extensions_version, $max_version, '>')) {
			$messages[] = "The module is not compatible with the installed Ka Extensions library.
				The maximum Ka Extensions library version is " . $max_version . 
				". Please update the library up to the latest version.";
			return false;
		}
				
		//check database
		//
		if (!$this->model_extension_ka_extensions_patchdb->checkDBCompatibility($tables, $messages)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 Installs the extension: checks compatibility, patches the database by the tables definition and
	 grants permissions to the extension pages for the current user group.

	 $params — for now it is just a way to prevent calling this method from url. Later the extension
	 manager can pass some information through this array.

	 @return bool True on success.
	*/
	public function install($params) {

		if (!$this->checkCompatibility($this->tables, $messages)) {
			$this->addTopMessage($messages, 'E');
			return false;
		}
		
		if (!$this->model_extension_ka_extensions_patchdb->patchDB($this->tables, $messages)) {
			$this->addTopMessage($messages, 'E');
			return false;
		}

		// grant permissions to extension pages
		//
		$routes = $this->getExtensionPages();
		if (!empty($routes)) {
			foreach($routes as $r) {
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/' . $this->ext_code . '/' . $r);
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $this->ext_code . '/' . $r);
			}
		}
		
		return true;
	}
	

	/**
	 Uninstalls the extension and revokes permissions to the extension pages from all user groups.

	 $params — for now it is just a way to prevent calling this method from url. Later the extension
	 manager can pass some information through this array.

	 @return bool
	*/
	public function uninstall($params) {

		// revoke permissions to extension pages
		//
		$user_groups = $this->model_user_user_group->getUserGroups();
		$routes = $this->getExtensionPages();

		if (!empty($routes)) {
			foreach ($user_groups as $ug) {
				foreach($routes as $r) {
					try {
						$this->model_user_user_group->removePermission($ug['user_group_id'], 'access', 'extension/' . $this->ext_code . '/' . $r);
						$this->model_user_user_group->removePermission($ug['user_group_id'], 'modify', 'extension/' . $this->ext_code . '/' . $r);
					} catch (\TypeError $e) {
						// ignore the type error. It may happen on incorrect json_decode inside removePermission()
					}
				}
			}
		}
		
		return true;
	}
	
	
	/**
	 Returns the extension title with its version, e.g. 'My Module (ver.1.0.0.0)'.

	 @return string
	*/
	public function getTitle() {
		$str = $this->ini['name'] . ' (ver.' . $this->ini['version'] . ')';
		
		return $str;
	}	
	
	
	/**
	 Returns the extension version from the manifest.

	 @return string
	*/
	public function getVersion() {
		return $this->extension_version;
	}
	
	
	/**
	 Returns the extension link from the manifest.

	 @return string
	*/
	public function getExtLink() {
		return $this->ext_link;
	}
	
	/**
	 Returns the documentation link from the manifest.

	 @return string
	*/
	public function getDocsLink() {
		return $this->docs_link;
	}	
}