<?php
$insert_view = elgg_view('webservicessettings/extend');

$key_string = elgg_echo('Enter Google GCM Server API Key here');
$key_view = elgg_view('input/text', array(
	'name' => 'params[google_api_key]',
	'value' => $vars['entity']->google_api_key,
	'class' => 'text_input',
));

$sender_id_string = elgg_echo('Enter Google GCM Sender Id here');
$sender_id_view = elgg_view('input/text', array(
	'name' => 'params[google_sender_id]',
	'value' => $vars['entity']->google_sender_id,
	'class' => 'text_input',
));

$logo_string = elgg_echo('Enter site logo url here. <i>(Example: http://domain.com/some/path/to/image.jpg)</i>');
$logo_view = elgg_view('input/url', array(
	'name' => 'params[ws_get_logo]',
	'value' => $vars['entity']->ws_get_logo,
	'class' => 'text_input',
));

$settings = <<<__HTML
<div>$insert_view</div>
<div>$key_string $key_view</div>
<div>$sender_id_string $sender_id_view</div>
<div>$logo_string $logo_view</div>
__HTML;

echo $settings;