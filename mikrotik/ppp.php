<?php
// mikrotik/ppp.php

// --- PPP SECRETS (AKUN PELANGGAN) ---

function get_ppp_secrets($client, $filter = []) {
    return mikrotik_query($client, '/ppp/secret', 'print', $filter);
}

function add_ppp_secret($client, $name, $password, $profile, $service = 'pppoe', $comment = '') {
    $data = [
        'name'     => $name,
        'password' => $password,
        'profile'  => $profile,
        'service'  => $service,
        'comment'  => $comment
    ];
    return mikrotik_query($client, '/ppp/secret', 'add', $data);
}

function update_ppp_secret($client, $id, $data) {
    return mikrotik_query($client, '/ppp/secret', 'set', $data, $id);
}

function delete_ppp_secret($client, $id) {
    return mikrotik_query($client, '/ppp/secret', 'remove', [], $id);
}

// --- PPP PROFILES ---

function get_ppp_profiles($client) {
    return mikrotik_query($client, '/ppp/profile', 'print');
}

function add_ppp_profile($client, $name, $local_addr, $remote_addr, $rate_limit = null) {
    $data = [
        'name' => $name,
        'local-address' => $local_addr,
        'remote-address' => $remote_addr
    ];
    if ($rate_limit) $data['rate-limit'] = $rate_limit;
    
    return mikrotik_query($client, '/ppp/profile', 'add', $data);
}

// --- PPP ACTIVE (MONITORING) ---

function get_ppp_active($client) {
    return mikrotik_query($client, '/ppp/active', 'print');
}

function kick_ppp_active($client, $id) {
    return mikrotik_query($client, '/ppp/active', 'remove', [], $id);
}

function get_active_pppoe_users($client) {
    return mikrotik_query($client, '/ppp/active', 'print', ['service' => 'pppoe']);
}


function get_all_pppoe_users($client) {
    return mikrotik_query($client, '/ppp/secret', 'print', ['service' => 'pppoe']);
}

function pppComment($name, $username) {
    $date = date('Y-m-d');
    return "[SNM-SECRET] {$name} | via Silver Network Management | {$date}";
}

/**
 * Generate standard comment for MikroTik PPP Profile (Packages)
 */
function pppProfileComment($packageName) {
    $date = date('Y-m-d');
    return "[SNM-PROFILE] {$packageName} | via Silver Network Management | {$date}";
}