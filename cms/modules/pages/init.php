<?php defined('SYSPATH') or die('No direct access allowed.');

Observer::observe('frontpage_found', function($page) {
	if($page->is_password_protected() AND ! AuthUser::isLoggedIn())
	{
		throw new HTTP_Exception_Front_401;
	}
	
	Meta::clear();
	Context::instance()->meta(Meta::factory($page));
});