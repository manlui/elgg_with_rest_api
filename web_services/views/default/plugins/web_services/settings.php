<?php
$insert_view = elgg_view('webservicessettings/extend');

$key_string = elgg_echo('Enter Google API Key here');
$key_view = elgg_view('input/text', array(
	'name' => 'params[google_api_key]',
	'value' => $vars['entity']->google_api_key,
	'class' => 'text_input',
));

$settings = <<<__HTML
<div>$insert_view</div>
<div>$key_string $key_view</div>
__HTML;

echo $settings;