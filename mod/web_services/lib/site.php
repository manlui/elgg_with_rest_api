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
    $siteinfo['api_key'] = get_api_key();
    $siteinfo['logo'] = get_logo();
    if ($site->description == null) {
        $siteinfo['description'] = '';
    } else {
        $siteinfo['description'] = $site->description;
    }
    $siteinfo['time_created'] = time_ago($site->time_created);
    $siteinfo['language'] = elgg_get_config('language');

    return $siteinfo;
}

function get_logo($site_guid=1) {
    global $CONFIG;
    $query = "SELECT * from {$CONFIG->dbprefix}sites_entity"
        . " where guid=$site_guid";

    $site_data = get_data_row($query);

    $return = $site_data->logo;

    if ($return === null) {
        $return ='';
    }
    return $return;
}

function get_api_key() {
    $list = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'api_key',
    ));

    $api_key='';
    if ($list) {
        if(sizeof($list) === 1) {
            $entity = get_entity($list[0]->guid);
            $api_key = $entity->public;
        } else {
            foreach($list as $item){
                $entity = get_entity($item->get('guid'));
                if ($entity->title == 'android') {
                    $api_key = $entity->public;
                }
            }
        }

    }
    return $api_key;
}
