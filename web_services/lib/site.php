<?php
/**
 * Created by PhpStorm.
 * User: mlui
 * Date: 1/12/2016
 * Time: 9:16 AM
 */

////////////////////////////////////////////////////////////////////////////////////
//get elgg site info
elgg_ws_expose_function('site.getinfo',
    "site_getinfo",
    array(),
    "Get site information",
    'GET',
    false,
    false);

/**
 * @return mixed
 */
function site_getinfo() {
    $site = elgg_get_config('site');
    $siteinfo['url'] = elgg_get_site_url();
    $siteinfo['sitename'] = $site->name;
    $siteinfo['logo'] = elgg_get_plugin_setting('ws_get_logo', 'web_services');
    if ($site->description == null) {
        $siteinfo['description'] = '';
    } else {
        $siteinfo['description'] = $site->description;
    }
    $siteinfo['time_created'] = time_ago($site->time_created);
    $siteinfo['language'] = elgg_get_config('language');

    return $siteinfo;
}


function site_get_list_plugin() {
    $plugins = elgg_get_plugins($status = 'active', $site_guid = null);
    $return = array(
        'messages' => false,
        'thewire' => false,
        'blog' => false,
        'tidypics' => false,
        'file' => false,
        'bookmarks' => false,
        'groups' => false,
    );
    foreach ($plugins as $plugin) {
        $a = $plugin->title;
        if (array_key_exists($plugin->title, $return)) {
            $return[$plugin->title] = true;
        }
    }

    return $return;
}

elgg_ws_expose_function('site.get_list_plugin',
    "site_get_list_plugin",
    array(),
    "Get list site Plugin",
    'GET',
    false,
    false);