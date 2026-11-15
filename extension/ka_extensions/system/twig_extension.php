<?php
/*
  Project : Ka Extensions
  Author  : karapuz team <support@ka-station.com>

  Version : 4 ($Revision: 260 $)
  
*/

namespace Opencart\Extension\KaExtensions\System;

use \Twig\TwigFunction;
use \Twig\TwigFilter;

/**
	Adds Ka Extensions functions to Twig templates.

	@internal
*/
class TwigExtension extends \Twig\Extension\AbstractExtension
{
	/**
	 Returns Twig functions: dir, t, html_entity_decode, get_language_image, has_t, linka.

	 @return array TwigFunction objects.
	*/
    public function getFunctions()
    {
        return array(
            new TwigFunction('dir', function($text) {
         		return dirname($text) . '/';
            }),
            new TwigFunction('t', function($text) {
         		return KaGlobal::t($text);
            }),
            new TwigFunction('html_entity_decode', function($text) {
        		return html_entity_decode($text);
            }),
            new TwigFunction('get_language_image', function($param) {
           		return KaGlobal::getLanguageImage($param);
            }),
            new TwigFunction('has_t', function($text) {
            	return KaGlobal::getRegistry()->get('language')->has($text);
            }),
            // getting links inside a template
            //
            new TwigFunction('linka', function($route, $params = '', $is_js = false) {
            	$registry = \KaGlobal::getRegistry();
            	$link = $registry->get('url')->linka($route, $params, true, $is_js);
        		return $link;
            }),
        );
    }
    
	/**
	 Returns Twig filters: ka_escape.

	 ka_escape escapes a value for HTML text and attributes (quotes too). Entities already present in
	 the value are not encoded again, because OpenCart saves posted values with htmlspecialchars().
	 Arrays and objects are returned as they are, null becomes an empty string.

	 @return array TwigFilter objects.
	*/
    public function getFilters()
    {
        return array(
            new TwigFilter('ka_escape', function($value) {
            	return static::escape($value);
            }),
        );
    }

	/**
	 Escapes a scalar value for HTML (see the ka_escape filter).

	 $value — value to escape.

	 @return mixed
	*/
    public static function escape($value)
    {
    	if (is_null($value)) {
    		return '';
    	}
    	if (!is_scalar($value)) {
    		return $value;
    	}
    	if (is_bool($value)) {
    		return $value ? '1' : '';
    	}

    	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

	/**
	 Returns the extension name.

	 @return string Extension name.
	*/
    public function getName()
    {
        return 'Ka Extensions';
    }
}