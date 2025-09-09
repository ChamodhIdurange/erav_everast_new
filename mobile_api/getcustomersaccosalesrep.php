<?php 
require_once('../connection/db.php');

$employeeId=$_POST['employeeId'];

$sql="SELECT * FROM `tbl_customer` WHERE `status`='1' AND `ref`='$employeeId'";
$result = mysqli_query($conn, $sql);
$customerarray = array();

while ($row = mysqli_fetch_array($result)) {
    $customerId = $row['idtbl_customer'];
    $isenable = $row['enable_for_porder'];
    $haveOutstanding = 0;

    $customerOustandingCount = "SELECT MAX(DATEDIFF(CURDATE(), `i`.`date`)) AS max_date_diff, `i`.`idtbl_invoice` FROM `tbl_invoice` AS `i` WHERE `i`.`tbl_customer_idtbl_customer` = '$customerId' AND `i`.`paymentcomplete` = 0 AND `i`.`status` = 1";

    $outstandingresult = mysqli_query($conn, $customerOustandingCount);

    if ($outstandingresult && $rowresult = $outstandingresult->fetch_assoc()) {
        $maxDateDiff = $rowresult['max_date_diff'];
        $invoiceId = $rowresult['idtbl_invoice'];
        if (!is_null($maxDateDiff) && $maxDateDiff >= 90 && $isenable == 0) {
            $haveOutstanding = 1;
        }else{
            $haveOutstanding = 0;
        }
    } 

    array_push($customerarray, array("id" => $row['idtbl_customer'], "name" => $row['name'], "nic" => $row['nic'], "phone" => $row['phone'], "email" => $row['email'], "address" => $row['address'], "areaId" => $row['tbl_area_idtbl_area'], "haveOutstanding" => $haveOutstanding, "isenable" => $isenable, "maxDateDiff" => $maxDateDiff, "invoiceId" => $invoiceId ));
}

echo json_encode($customerarray);
?>
