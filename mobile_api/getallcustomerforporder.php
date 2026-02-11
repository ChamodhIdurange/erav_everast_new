<?php

require_once('dbConnect.php');

$taxQuery = "SELECT `rate` FROM `tbl_tax` LIMIT 1";
$taxResult = $con->query($taxQuery);
$tax_rate = 0;
if ($taxResult && $taxResult->num_rows > 0) {
    $taxRow = $taxResult->fetch_assoc();
    $tax_rate = $taxRow['rate'];
}

$vat_cus = false;

$sql="SELECT * FROM `tbl_customer`  WHERE `status`='1' ";
$res = mysqli_query($con, $sql);
$result = array();

while ($row = mysqli_fetch_array($res)) {
    if($row['vat_num'] != '') {
        $vat_cus = true;
        $customer_tax = $tax_rate;
    } else {
        $vat_cus = false;
        $customer_tax = 0;
    }
    array_push($result, array( "id" => $row['idtbl_customer'], "customername" => $row['name'], "vat_cus" => $vat_cus, "tax_rate" => $customer_tax));
}

print(json_encode($result));
mysqli_close($con);

?>