<?php
require_once('../connection/db.php');

$customerID = $_POST['customerID'];

$query = "SELECT phone, address, type, vat_num FROM tbl_customer WHERE idtbl_customer = '$customerID' LIMIT 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode($row);
} else {
    echo json_encode(['phone' => '', 'address' => '', 'type' => '', 'vat_num' => '']);
}
?>