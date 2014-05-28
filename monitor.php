<?php
header('Content-type: application/json');

// load ZabbixApi
require 'PhpZabbixApi/ZabbixApiAbstract.class.php';
require 'PhpZabbixApi/ZabbixApi.class.php';
require 'date_function.php';

setlocale(LC_ALL, "pt_BR", "pt_BR.utf8", "portuguese");
date_default_timezone_set('America/Sao_Paulo');

$data = json_decode(file_get_contents("php://input"));

try {
    // connect to Zabbix API
    $api = new ZabbixApi('http://slcc/api_jsonrpc.php');
    $response = $api->userLogin(array('user'=>$data->user, 'password'=>$data->password));
} catch(Exception $e) {
    // Exception in ZabbixApi catched
    echo json_encode(array('erro' => $e->getMessage()));
    die();
}

// Retrieve host groups
$groups = $api->hostgroupGet(array('output' => 'groupid'), '');

$group_list = array();
foreach($groups as $group) {
    array_push($group_list, $group->groupid);
}

// Retrieve hosts
$hosts = $api->hostGet(array('groupids' => $group_list, 'output' => array('hostid', 'host')), '');

$host_list = array();
foreach($hosts as $host) {
    $host_list[$host->hostid] = $host->host;
}

// Retrieve triggers
$triggers = $api->triggerGet(
    array(
        'output' => 'extend',
        // Return only triggers that belong to the given hosts.
        'hostids' => array_keys($host_list),
        // Return only those results with problems. 
        'filter' => array('value' => 1),
        // Sort the result by the given properties.
        'sortfield' => array('lastchange', 'priority'),
        'sortorder' => 'DESC',
        // Skip triggers in a problem state that are dependent on other triggers.
        'skipDependent' => true,
        // Expand macros in the name of the trigger.
        'expandDescription' => true,
        // Return only triggers that have unacknowledged events.
        'withUnacknowledgedEvents' => true,
        // Return only enabled triggers that belong to monitored hosts and contain only enabled items.
        'monitored' => true
    ),
    ''
);

$priority = array(5 => 'disaster', 4 => 'high', 3 => 'average', 2 => 'warning', 1 => 'information', 0 => 'not_classified');

$last_issues = array();
foreach ($triggers as $trigger) {
    $issue = new stdClass();
    $issue->host = $host_list[$trigger->hostid];
    $issue->description = $trigger->description;
    $issue->lastchange = strftime("%d %b %Y %H:%M:%S", strtotime(date('d M Y H:i:s', $trigger->lastchange)));
    $issue->age = time_elapsed_string(date('Y-m-d H:i:s', $trigger->lastchange), true);
    $issue->priority = $priority[$trigger->priority];
    array_push($last_issues, $issue);
}

echo json_encode($last_issues);
$api->userLogout(array(), '', $response);
unset($api);
?>
