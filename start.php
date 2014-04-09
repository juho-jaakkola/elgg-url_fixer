<?php
/**
 * URL fixer
 */

elgg_register_event_handler('init', 'system', 'url_fixer_init');

function url_fixer_init() {
	$path = elgg_get_plugins_path() . 'url_fixer/';
	elgg_register_admin_menu_item('administer', 'url_fixer', 'administer_utilities');
	elgg_register_action('url_fixer/run', $path . 'actions/url_fixer/run.php', 'admin');
}