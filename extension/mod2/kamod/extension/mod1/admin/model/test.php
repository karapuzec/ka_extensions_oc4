<?php

namespace Opencart\Admin\Model\Extension\Mod2\Mod1;

class Test extends \Opencart\Admin\Model\Extension\Mod1\Test {
	
	public function getTitle() {
		$title = parent::getTitle();
		return $title . "22";
	}
}
