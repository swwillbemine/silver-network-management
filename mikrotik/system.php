<?php
// mikrotik/system.php

function get_system_resource($client) {
    $res = mikrotik_query($client, '/system/resource', 'print');
    return $res[0] ?? [];
}

function get_routerboard_info($client) {
    $res = mikrotik_query($client, '/system/routerboard', 'print');
    return $res[0] ?? [];
}