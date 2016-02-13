<?php
$insert_view = elgg_view('webservicessettings/extend');

$key_string = elgg_echo('Enter Google API Key here');
$key_view = elgg_view('input/text', array(
	'name' => 'params[google_api_key]',
	'value' => $vars['entity']->google_api_key,
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
<div style="font-size:12px"><b>Note:</b><br>Google API Key is required for push notification on your mobile. You can leave this blank if you don\'t intend to use the push notification service.<br>Enable "Site Notification" plugin to enable the push notification feature (Mandatory). <br>You can obtain your Google API Key from <a href="http://console.developers.google.com" target="_blank">here</a>. Select "Google Cloud Messaging" when requesting for the API Key.<br><br></div>
<div>$logo_string $logo_view</div>
__HTML;

echo $settings;