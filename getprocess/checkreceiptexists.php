<?php
require_once('../connection/db.php');

$receiptNo = $_POST['receiptNo'];
$sql = "SELECT * FROM `tbl_invoice_payment_detail` WHERE `receiptno` = '$receiptNo'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    echo 'true';
} else {
    echo 'false';
}

?>
