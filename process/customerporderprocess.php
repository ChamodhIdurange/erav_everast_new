<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header("Location:index.php");
}
require_once('../connection/db.php');

$taxQuery = "SELECT `rate` FROM `tbl_tax` LIMIT 1";
$taxResult = $conn->query($taxQuery);
$tax = 0;
if ($taxResult && $taxResult->num_rows > 0) {
    $taxRow = $taxResult->fetch_assoc();
    $tax = $taxRow['rate'];
}

$userID = $_SESSION['userid'];
$recordOption = $_POST['recordOption'];
$recordID = $_POST['recordID'];
$orderdate = $_POST['orderdate'];
$remark = $_POST['remark'];
$discountpresentage = $_POST['discountpresentage'];
$total = $_POST['total']; // Subtotal WITHOUT VAT
$discount = $_POST['discount']; // Line discount
$podiscountamount = $_POST['podiscountamount'];
$nettotal = $_POST['nettotal']; // Final net total
$repname = $_POST['repname'];
$area = $_POST['area'];
$location = $_POST['location'];
$customer = $_POST['customer'];
$tableData = $_POST['tableData'];
$podiscount = $_POST['podiscount'];
$vatamount = isset($_POST['vatamount']) ? $_POST['vatamount'] : 0;

$tableData = json_decode($tableData);

$updatedatetime = date('Y-m-d H:i:s');

// Generate PO number
$query = "SELECT MAX(cuspono) AS max_id FROM tbl_customer_order WHERE cuspono LIKE 'CP/" . date('y/m/') . "%'";
$result = $conn->query($query);

$taxcus = "SELECT vat_num FROM tbl_customer WHERE idtbl_customer = '$customer'";
$taxcusresult = $conn->query($taxcus);
$taxcusrow = $taxcusresult->fetch_assoc();

if($taxcusrow['vat_num'] != ''){
    $tax = $tax;
}else{
    $tax = 0;
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['max_id']) {
        preg_match('/(\d+)$/', $row['max_id'], $matches);
        $next_id = isset($matches[1]) ? $matches[1] + 1 : 1;
    } else {
        $next_id = 1;
    }
} else {
    $next_id = 1;
}

$next_id_padded = str_pad($next_id, 4, '0', STR_PAD_LEFT);
$dateformat = date('y/m/');
$cuspono = 'CP/' . $dateformat . $next_id_padded;

if ($recordOption == 1) {
    // Handle direct customer if needed
    if (isset($_POST['directcustomer']) && $_POST['directcustomer'] != '') {
        $directcustomer = $_POST['directcustomer'];
        $customercontact = $_POST['customercontact'];
        $customeraddress = $_POST['customeraddress'];

        $insertcustomer = "INSERT INTO `tbl_customer`(`type`, `name`, `nic`, `phone`, `email`, `address`, `vat_num`, `s_vat`, `numofvisitdays`, `creditlimit`, `credittype`, `creditperiod`, `emergencydate`, `status`, `updatedatetime`, `tbl_user_idtbl_user`, `tbl_area_idtbl_area`) 
        VALUES ('1', '$directcustomer', '', '$customercontact', '', '$customeraddress', '', '', 0, 0, 0, 0, '', '1', '$updatedatetime', '$userID', '1')";
        $conn->query($insertcustomer);
        $customer = $conn->insert_id;
    }



    // Insert main order
    // total = subtotal WITHOUT VAT
    // vat = VAT amount
    // discount = line discount
    // podiscountamount = PO discount amount
    // nettotal = final amount after all calculations

    $insretorder = "INSERT INTO `tbl_customer_order`(
        `cuspono`, 
        `date`, 
        `total`, 
        `discount`, 
        `podiscount`, 
        `podiscountpercentage`, 
        `vat`, 
        `nettotal`, 
        `remark`, 
        `vatpre`, 
        `status`, 
        `insertdatetime`, 
        `tbl_user_idtbl_user`, 
        `tbl_area_idtbl_area`, 
        `tbl_employee_idtbl_employee`, 
        `tbl_locations_idtbl_locations`, 
        `tbl_customer_idtbl_customer`
    ) VALUES (
        '$cuspono', 
        '$orderdate',
        '$total',
        '$discount', 
        '$podiscountamount', 
        '$podiscount', 
        '$tax', 
        '$nettotal', 
        '$remark', 
        '$tax',
        '1', 
        '$updatedatetime', 
        '$userID', 
        '$area', 
        '$repname', 
        '$location', 
        '$customer'
    )";

    if ($conn->query($insretorder) == true) {
        $orderID = $conn->insert_id;

        // Insert original order for backup
        $insretoriginalorder = "INSERT INTO `tbl_original_customer_order`(
            `cuspono`, 
            `date`, 
            `total`, 
            `discount`, 
            `podiscount`, 
            `podiscountpercentage`, 
            `vat`, 
            `nettotal`, 
            `remark`, 
            `vatpre`, 
            `status`, 
            `insertdatetime`, 
            `tbl_user_idtbl_user`, 
            `tbl_area_idtbl_area`, 
            `tbl_employee_idtbl_employee`, 
            `tbl_locations_idtbl_locations`, 
            `tbl_customer_idtbl_customer`, 
            `tbl_customer_order_idtblcustomer_order`
        ) VALUES (
            '$cuspono', 
            '$orderdate',
            '$total',
            '$discount', 
            '$podiscountamount', 
            '$podiscount', 
            '$vatamount', 
            '$nettotal', 
            '$remark', 
            '$tax',
            '1', 
            '$updatedatetime', 
            '$userID', 
            '$area', 
            '$repname', 
            '$location', 
            '$customer', 
            '$orderID'
        )";
        $conn->query($insretoriginalorder);
        $originalOrderID = $conn->insert_id;

        // Insert order details
        foreach ($tableData as $rowtabledata) {
            $productCount = $rowtabledata->col_1;
            $product = $rowtabledata->col_3;
            $unitprice = $rowtabledata->col_4;
            $saleprice = $rowtabledata->col_5; // Already VAT-excluded for VAT customers
            $newqty = $rowtabledata->col_6;
            $freeprodcutid = $rowtabledata->col_8;
            $freeqty = $rowtabledata->col_9;
            $linediscount = $rowtabledata->col_12;
            $total = $rowtabledata->col_13;
            $fulltotwithoutdiscount = $rowtabledata->col_14;

            $linediscountpercentage = ($fulltotwithoutdiscount > 0) ? ($linediscount * 100) / $fulltotwithoutdiscount : 0;

            // Insert order detail
            $insertorderdetail = "INSERT INTO `tbl_customer_order_detail`(
                `orderqty`, 
                `total`, 
                `confirmqty`, 
                `dispatchqty`, 
                `qty`, 
                `unitprice`, 
                `saleprice`, 
                `discountpresent`, 
                `discount`, 
                `status`, 
                `insertdatetime`, 
                `tbl_user_idtbl_user`, 
                `tbl_customer_order_idtbl_customer_order`, 
                `tbl_product_idtbl_product`
            ) VALUES (
                '$newqty', 
                '$total', 
                '$newqty', 
                '$newqty', 
                '$newqty', 
                '$unitprice',
                '$saleprice', 
                '$linediscountpercentage', 
                '$linediscount', 
                '1',
                '$updatedatetime',
                '$userID',
                '$orderID',
                '$product'
            )";
            $conn->query($insertorderdetail);

            // Insert original order detail
            $insertoriginalorderdetail = "INSERT INTO `tbl_original_customer_order_detail`(
                `orderqty`, 
                `total`, 
                `confirmqty`, 
                `dispatchqty`, 
                `qty`, 
                `unitprice`, 
                `saleprice`, 
                `discountpresent`, 
                `discount`, 
                `status`, 
                `insertdatetime`, 
                `tbl_user_idtbl_user`, 
                `tbl_original_customer_order_idtbl_original_customer_order`, 
                `tbl_product_idtbl_product`
            ) VALUES (
                '$newqty', 
                '$total', 
                '$newqty', 
                '$newqty', 
                '$newqty', 
                '$unitprice',
                '$saleprice', 
                '$linediscountpercentage', 
                '$linediscount', 
                '1',
                '$updatedatetime',
                '$userID',
                '$originalOrderID',
                '$product'
            )";
            $conn->query($insertoriginalorderdetail);

            // Insert hold stock
            $insertholdstock = "INSERT INTO `tbl_customer_order_hold_stock`(
                `qty`, 
                `invoiceissue`, 
                `status`, 
                `insertdatetime`, 
                `tbl_user_idtbl_user`, 
                `tbl_product_idtbl_product`, 
                `tbl_customer_order_idtbl_customer_order`
            ) VALUES (
                '$newqty', 
                '0', 
                '1', 
                '$updatedatetime', 
                '$userID', 
                '$product',
                '$orderID'
            )";
            $conn->query($insertholdstock);
        }

        $actionObj = new stdClass();
        $actionObj->icon = 'fas fa-check-circle';
        $actionObj->title = '';
        $actionObj->message = 'Order Added Successfully';
        $actionObj->url = '';
        $actionObj->target = '_blank';
        $actionObj->type = 'success';

        echo json_encode($actionObj);
    } else {
        $actionObj = new stdClass();
        $actionObj->icon = 'fas fa-exclamation-triangle';
        $actionObj->title = '';
        $actionObj->message = 'Record Error: ' . $conn->error;
        $actionObj->url = '';
        $actionObj->target = '_blank';
        $actionObj->type = 'danger';

        echo json_encode($actionObj);
    }
}
