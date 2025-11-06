<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header("Location:index.php");
}
require_once('../connection/db.php');

$userID = $_SESSION['userid'];
$today = date('Y-m-d');

$poID = $_POST['poID'];
$total = $_POST['total'];
$nettotal = $_POST['nettotal'];
$discount = $_POST['discount'];
$podiscountPrecentage = $_POST['podiscountPrecentage'];
$podiscountAmount = $_POST['podiscountAmount'];
$remarkVal = $_POST['remarkVal'];

$acceptanceType = $_POST['acceptanceType'];
$isChangeStatus = $_POST['isChangeStatus'];
$tableData = $_POST['tableData'];
$tableData = json_decode($tableData);

$updatedatetime = date('Y-m-d h:i:s');
$areaId = null;
$locationId = null;
$customerId = null;

$fullDiscount = $discount + $podiscountAmount;

// ------------------- CONFIRMED -------------------
if ($acceptanceType == 1) {
    $updatePoValues = "UPDATE `tbl_customer_order` 
        SET `date`='$today', 
            `podiscount`='$podiscountAmount', 
            `podiscountpercentage`='$podiscountPrecentage', 
            `discount`='$discount', 
            `nettotal`='$nettotal', 
            `total`='$total', 
            `confrimuser`='$userID', 
            `remark`='$remarkVal' 
        WHERE `idtbl_customer_order`='$poID'";

    $updatePoStatus = "UPDATE `tbl_customer_order` SET `confirm`='1' WHERE `idtbl_customer_order`='$poID'";

    $getporderdata = "SELECT * FROM `tbl_customer_order` WHERE `idtbl_customer_order` = '$poID'";
    $result = $conn->query($getporderdata);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $areaId = $row['tbl_area_idtbl_area'];
        $locationId = $row['tbl_locations_idtbl_locations'];
        $customerId = $row['tbl_customer_idtbl_customer'];

        if ($isChangeStatus == 1) {
            // Insert invoice record
            $insertInvoice = "INSERT INTO `tbl_invoice` 
                (`invoiceno`, `date`, `total`, `discount`, `vatamount`, `nettotal`, 
                 `paymentcomplete`, `status`, `updatedatetime`, 
                 `tbl_user_idtbl_user`, `tbl_area_idtbl_area`, 
                 `tbl_customer_idtbl_customer`, `tbl_locations_idtbl_locations`, 
                 `tbl_customer_order_idtbl_customer_order`) 
                VALUES('-', '$updatedatetime', '$total', '$fullDiscount', '0', '$nettotal', 
                        '0', '1', '$updatedatetime', '$userID', '$areaId', '$customerId', 
                        '$locationId', '$poID')";

            if ($conn->query($insertInvoice)) {
                $invoiceId = $conn->insert_id;

                // 🧾 VAT-based Invoice Number Generation
                $getCustomerVat = "SELECT vat_num FROM tbl_customer WHERE idtbl_customer = '$customerId'";
                $resultVat = $conn->query($getCustomerVat);

                if ($resultVat && $resultVat->num_rows > 0) {
                    $rowVat = $resultVat->fetch_assoc();
                    $isVatCustomer = !empty($rowVat['vat_num']); // TRUE if VAT number exists
                } else {
                    $isVatCustomer = false;
                }

                // Set prefix
                $prefix = $isVatCustomer ? 'VIV/' : 'IV/';
                $dateformat = date('y/m/');

                // Get last invoice with same prefix
                $query = "SELECT MAX(invoiceno) AS max_id 
                          FROM tbl_invoice 
                          WHERE invoiceno LIKE '$prefix" . date('y/m/') . "%'";
                $resultMax = $conn->query($query);

                if ($resultMax && $resultMax->num_rows > 0) {
                    $rowMax = $resultMax->fetch_assoc();
                    if ($rowMax['max_id']) {
                        preg_match('/(\d+)$/', $rowMax['max_id'], $matches);
                        $next_id = isset($matches[1]) ? $matches[1] + 1 : 1;
                    } else {
                        $next_id = 1;
                    }
                } else {
                    $next_id = 1;
                }

                $next_id_padded = str_pad($next_id, 4, '0', STR_PAD_LEFT);
                $invoiceNo = $prefix . $dateformat . $next_id_padded;

                $updateInvoiceNo = "UPDATE `tbl_invoice` 
                                    SET `invoiceno`='$invoiceNo' 
                                    WHERE `idtbl_invoice` = '$invoiceId'";
                $conn->query($updateInvoiceNo);
            }
        }
    }
}
// ------------------- DISPATCHED -------------------
else if ($acceptanceType == 2) {
    $updatePoValues = "UPDATE  `tbl_customer_order` 
        SET `podiscount`='$podiscountAmount', 
            `podiscountpercentage`='$podiscountPrecentage',  
            `discount`='$discount',
            `nettotal`='$nettotal', 
            `total`='$total', 
            `dispatchuser`='$userID', 
            `remark`='$remarkVal'  
        WHERE `idtbl_customer_order`='$poID'";
    $updatePoStatus = "UPDATE  `tbl_customer_order` SET `dispatchissue`='1' WHERE `idtbl_customer_order`='$poID'";

    $insertDispatch = "INSERT INTO `tbl_cutomer_order_dispatch`
        (`dispatchdate`, `vehicleno`, `drivername`, `trackingno`, `trackingwebsite`, 
         `currier`, `status`, `insertdatetime`, `tbl_user_idtbl_user`) 
        VALUES('$updatedatetime', '-', '-', '-', '-', '-', '1', '$updatedatetime', '$userID')";
    $conn->query($insertDispatch);
    $dispatchId = $conn->insert_id;

    $insertDispatchInfo = "INSERT INTO `tbl_cutomer_order_dispatch_has_tbl_customer_order`
        (`tbl_cutomer_order_dispatch_idtbl_cutomer_order_dispatch`, 
         `tbl_customer_order_idtbl_customer_order`) 
        VALUES('$dispatchId', '$poID')";
    $conn->query($insertDispatchInfo);
}
// ------------------- DELIVERED -------------------
else if ($acceptanceType == 3) {
    $updatePoValues = "UPDATE  `tbl_customer_order` 
        SET `podiscount`='$podiscountAmount', 
            `podiscountpercentage`='$podiscountPrecentage',  
            `ship`='1', 
            `discount`='$discount',
            `nettotal`='$nettotal', 
            `total`='$total', 
            `delivereduser`='$userID', 
            `shipuser`='$userID', 
            `remark`='$remarkVal'  
        WHERE `idtbl_customer_order`='$poID'";
    $updatePoStatus = "UPDATE  `tbl_customer_order` SET `delivered`='1' WHERE `idtbl_customer_order`='$poID'";

    $getinvoicedata = "SELECT * FROM `tbl_invoice` WHERE `tbl_customer_order_idtbl_customer_order` = '$poID'";
    $resultinvoice = $conn->query($getinvoicedata);

    if ($resultinvoice && $resultinvoice->num_rows > 0) {
        $rowinvoice = $resultinvoice->fetch_assoc();
        $invoiceId = $rowinvoice['idtbl_invoice'];
        $invoiceNo = $rowinvoice['invoiceno'];
    }

    $updateinvoicehead = "UPDATE `tbl_invoice` 
                          SET `total` = '$total', 
                              `discount` = '$fullDiscount', 
                              `nettotal` = '$nettotal', 
                              `updatedatetime` = '$updatedatetime' 
                          WHERE `idtbl_invoice`='$invoiceId'";
    $conn->query($updateinvoicehead);
}

// ------------------- MAIN PO UPDATE -------------------
if ($conn->query($updatePoValues) == true) {
    if ($isChangeStatus == 1) {
        $conn->query($updatePoStatus);
    }

    foreach ($tableData as $rowtabledata) {
        $productID = $rowtabledata->col_3;
        $podetailId = $rowtabledata->col_4;
        $qty = $rowtabledata->col_5;
        $linediscountprecentage = $rowtabledata->col_6;
        $linediscountamount = $rowtabledata->col_7;
        $saleprice = $rowtabledata->col_9;
        $status = $rowtabledata->col_11;
        $fullTotal = $rowtabledata->col_12;
        $newstatus = $rowtabledata->col_13;

        $netTotal = $fullTotal - $linediscountamount;

        if ($status == 3) {
            $deleteHoldStock = "UPDATE  `tbl_customer_order_hold_stock` 
                                SET `qty`='$qty', `status`='3', `invoiceissue`='1' 
                                WHERE `tbl_product_idtbl_product` = '$productID' 
                                AND `tbl_customer_order_idtbl_customer_order` = '$poID'";
            $conn->query($deleteHoldStock);
        }

        // Keep your existing line item update logic here as before
        // Example placeholder:
        // $updateHoldStock = "...";
        // $conn->query($updateHoldStock);
    }

    $actionObj = new stdClass();
    $actionObj->icon = 'fas fa-check-circle';
    $actionObj->title = '';
    $actionObj->message = 'Record Updated Successfully';
    $actionObj->url = '';
    $actionObj->target = '_blank';
    $actionObj->type = 'success';
    echo json_encode($actionObj);
} else {
    $actionObj = new stdClass();
    $actionObj->icon = 'fas fa-exclamation-triangle';
    $actionObj->title = '';
    $actionObj->message = 'Record Error';
    $actionObj->url = '';
    $actionObj->target = '_blank';
    $actionObj->type = 'danger';
    echo json_encode($actionObj);
}
