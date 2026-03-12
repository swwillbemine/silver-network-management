<?php
// mikrotik/interface.php

function get_interfaces($client) {
    return mikrotik_query($client, '/interface', 'print');
}

function get_interface_by_name($client, $name) {
    return mikrotik_query($client, '/interface', 'print', ['name' => $name]);
}

function set_interface_comment($client, $id, $comment) {
    return mikrotik_query($client, '/interface', 'set', ['comment' => $comment], $id);
}