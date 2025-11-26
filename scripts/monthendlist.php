<?php
require_once('../connection/db.php');

$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

$sql = "
  SELECT 
    DATE_FORMAT(me.date, '%Y-%m') AS ym,
    DATE_FORMAT(me.date, '%M %Y') AS month_name,
    COUNT(DISTINCT me.tbl_product_idtbl_product) AS product_count,
    SUM(me.qty) AS total_qty
  FROM tbl_month_end me
  GROUP BY DATE_FORMAT(me.date, '%Y-%m')
";

$resAll = $conn->query($sql);
$totalRecords = $resAll ? $resAll->num_rows : 0;

$orderSql = " ORDER BY ym DESC ";
$limitSql = " LIMIT $start, $length ";

$res = $conn->query($sql . $orderSql . $limitSql);

$data = [];
$rownum = $start + 1;
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $statusBadge = '<span class="badge badge-success">Submitted</span>';
    $data[] = [
      "rownum"        => $rownum++,
      "month_name"    => $r['month_name'],
      "status_badge"  => $statusBadge,
      "product_count" => $r['product_count'],
      "total_qty"     => $r['total_qty'],
    ];
  }
}

echo json_encode([
  "draw" => $draw,
  "recordsTotal" => $totalRecords,
  "recordsFiltered" => $totalRecords,
  "data" => $data,
]);
