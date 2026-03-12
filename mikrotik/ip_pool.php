<?php
// mikrotik/ip_pool.php

function get_ip_pools($client) {
    return mikrotik_query($client, '/ip/pool', 'print');
}

function add_ip_pool($client, $name, $ranges) {
    $data = [
        'name'   => $name,
        'ranges' => $ranges
    ];
    return mikrotik_query($client, '/ip/pool', 'add', $data);
}

function delete_ip_pool($client, $id) {
    return mikrotik_query($client, '/ip/pool', 'remove', [], $id);
}