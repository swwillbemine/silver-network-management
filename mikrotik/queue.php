<?php
// mikrotik/queue.php

function get_queues($client, $filter = []) {
    return mikrotik_query($client, '/queue/simple', 'print', $filter);
}

function add_queue($client, $name, $target_ip, $max_upload, $max_download, $comment = '') {
    $max_limit = $max_upload . '/' . $max_download;
    
    $data = [
        'name'      => $name,
        'target'    => $target_ip,
        'max-limit' => $max_limit,
        'comment'   => $comment
    ];
    return mikrotik_query($client, '/queue/simple', 'add', $data);
}

function update_queue_limit($client, $id, $max_upload, $max_download) {
    $max_limit = $max_upload . '/' . $max_download;
    return mikrotik_query($client, '/queue/simple', 'set', ['max-limit' => $max_limit], $id);
}

function delete_queue($client, $id) {
    return mikrotik_query($client, '/queue/simple', 'remove', [], $id);
}

function get_queues_by_prefix($client, $contains) {
    $allQueues = mikrotik_query($client, '/queue/simple', 'print');
    $filtered = [];
    foreach ($allQueues as $q) {
        if (isset($q['name']) && str_contains($q['name'], $contains)) {
            $filtered[] = $q;
        }
    }

    return $filtered;
}