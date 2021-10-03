<?php
session_write_close();

require_once "../../vendor/autoload.php";
require_once "../../includes/config.php";
require_once "../../includes/functions.php";

$data = [];
$recordsTotal = 0;
$recordsFiltered = 0;
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;

// khusus untuk super admin yang sedang login
$login = new \login();
$userLogin = $login->cek_login();
if (!$userLogin || !is_admin()) {
    echo json_encode([
        'data' => $data,
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered
    ]);
    exit;
}

// kolom yang kan ditampilkan
$cols = ["id", "username", "ip", "useraganet", "created", "expired", "id"];

// datatables request
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) && intval($_GET['length']) > 0 ? intval($_GET['length']) : 25;
$orderBy = isset($_GET['order'][0]['column']) ? $cols[intval($_GET['order'][0]['column'])] : $cols[0];
$orderDir = isset($_GET['order'][0]['dir']) ? htmlspecialchars($_GET['order'][0]['dir']) : "asc";
$search = isset($_GET['search']['value']) ? htmlspecialchars($_GET['search']['value']) : "";

$where = '';
if (!empty($search)) {
    $where = " WHERE ip LIKE '%$search%'";
    $where .= " OR useragent LIKE '%$search%'";
    $where .= " OR created LIKE '%$search%'";
    $where .= " OR expired LIKE '%$search%'";
    $where .= " OR username LIKE '%$search%'";
}

// result
$list = $db->prepare("SELECT * FROM tb_sessions $where ORDER BY $orderBy $orderDir LIMIT $start, $length");
$list->execute();
$accounts = $list->fetchAll(PDO::FETCH_ASSOC);
foreach ($accounts as $acc) {
    $dt = [];
    $dt['DT_RowId'] = $acc['id'];
    $dt['id']       = $acc['id'];
    $dt['ip']       = $acc['ip'];
    $dt['username'] = $acc['username'];
    $dt['useragent'] = $acc['useragent'];
    $dt['created'] = date('M d, Y H:i', $acc['created']);
    $dt['expired'] = date('M d, Y H:i', $acc['expired']);
    $dt['actions'] = $acc['id'];
    $data[] = $dt;
}

$recordsTotal = (int) $db->query("SELECT COUNT(id) FROM tb_sessions")->fetchColumn();
$recordsFiltered = (int) $db->query("SELECT COUNT(id) FROM tb_sessions $where")->fetchColumn();

echo json_encode([
    'draw' => $draw,
    'data' => $data,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered
]);
