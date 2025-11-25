<?php
session_start();
require_once('../connection/db.php');
require_once '../vendor/autoload.php';

$taxQuery = "SELECT `rate` FROM `tbl_tax` LIMIT 1";
$taxResult = $conn->query($taxQuery);
$tax = 0;

if ($taxResult && $taxResult->num_rows > 0) {
    $row = $taxResult->fetch_assoc();
    $tax = $row['rate'];
}

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);

$today = date('Y-m-d');
$today2 = date('Y/m');
$last_two_digits = substr($today2, 2);
$recordID = $_GET['id'];

$empty = 'null';
$fulltot = 0;
$discount = 0;
$totaloutstanding = 0;
$fulloutstanding = 0;
$totalpayment = 0;
$net_total = 0;
$newtemp = 0;

$sqlinvoiceinfo = "
SELECT 
  `tbl_invoice`.`discount`, 
  `tbl_invoice`.`idtbl_invoice`, 
  `tbl_invoice`.`invoiceno`, 
  `tbl_invoice`.`date`, 
  `tbl_invoice`.`total`, 
  `tbl_invoice`.`paymentcomplete`, 
  `tbl_locations`.`idtbl_locations`, 
  `tbl_locations`.`locationname`, 
  `tbl_customer`.`name`, 
  `tbl_customer`.`address`, 
  `tbl_customer`.`phone`, 
  `tbl_customer`.`vat_num`,
  `tbl_employee`.`name` AS `saleref`, 
  `tbl_employee`.`phone` AS 'salesrepphone', 
  `tbl_area`.`area`, 
  `tbl_user`.`name` as `username`, 
  `tbl_invoice`.`tbl_customer_idtbl_customer`, 
  `tbl_customer_order`.`cuspono` 
FROM `tbl_invoice` 
LEFT JOIN `tbl_locations` ON `tbl_locations`.`idtbl_locations`=`tbl_invoice`.`tbl_locations_idtbl_locations` 
LEFT JOIN `tbl_customer` ON `tbl_customer`.`idtbl_customer`=`tbl_invoice`.`tbl_customer_idtbl_customer` 
LEFT JOIN `tbl_customer_order` ON `tbl_customer_order`.`idtbl_customer_order`=`tbl_invoice`.`tbl_customer_order_idtbl_customer_order` 
LEFT JOIN `tbl_employee` ON `tbl_employee`.`idtbl_employee`=`tbl_customer_order`.`tbl_employee_idtbl_employee` 
LEFT JOIN `tbl_area` ON `tbl_area`.`idtbl_area`=`tbl_invoice`.`tbl_area_idtbl_area` 
LEFT JOIN `tbl_user` ON `tbl_user`.`idtbl_user`=`tbl_invoice`.`tbl_user_idtbl_user`
WHERE `tbl_invoice`.`status`=1 AND `tbl_invoice`.`idtbl_invoice`='$recordID'";

$resultinvoiceinfo = $conn->query($sqlinvoiceinfo);

if (!$resultinvoiceinfo) {
    die("Database Error: " . $conn->error . "<br>Query: " . $sqlinvoiceinfo);
}

if ($resultinvoiceinfo->num_rows == 0) {
    die("No invoice found with ID: " . $recordID);
}

$rowinvoiceinfo = $resultinvoiceinfo->fetch_assoc();

$customerID = $rowinvoiceinfo['tbl_customer_idtbl_customer'];
$customerPhone = $rowinvoiceinfo['phone'];
$customername = $rowinvoiceinfo['name'];
$location = $rowinvoiceinfo['locationname'];
$customeraddress = $rowinvoiceinfo['address'];
$paymentcomplete = $rowinvoiceinfo['paymentcomplete'];
$invoID = $rowinvoiceinfo['idtbl_invoice'];
$invoiceno = $rowinvoiceinfo['invoiceno'];
$pono = $rowinvoiceinfo['cuspono'];
$salesrepphone = $rowinvoiceinfo['salesrepphone'];
$vat_num = isset($rowinvoiceinfo['vat_num']) ? trim($rowinvoiceinfo['vat_num']) : '';

$isTaxCustomer = !empty($vat_num);

$sqlinvoicedetail = "
SELECT 
  `tbl_product`.`product_name`, 
  `tbl_product`.`product_code`, 
  `tbl_product`.`idtbl_product`, 
  `tbl_invoice_detail`.`qty`, 
  `tbl_invoice_detail`.`saleprice`, 
  `tbl_invoice_detail`.`discount` 
FROM `tbl_invoice_detail` 
LEFT JOIN `tbl_product` ON `tbl_product`.`idtbl_product`=`tbl_invoice_detail`.`tbl_product_idtbl_product` 
WHERE `tbl_invoice_detail`.`tbl_invoice_idtbl_invoice`='$recordID' AND `tbl_invoice_detail`.`status`=1";

$resultinvoicedetail = $conn->query($sqlinvoicedetail);

if (!$resultinvoicedetail) {
    die("Database Error: " . $conn->error . "<br>Query: " . $sqlinvoicedetail);
}

$sqlpayments = "SELECT SUM(amount) as total_paid FROM tbl_payment WHERE tbl_invoice_idtbl_invoice='$recordID' AND status=1";
$resultpayments = $conn->query($sqlpayments);

if ($resultpayments) {
    $rowpayments = $resultpayments->fetch_assoc();
    $totalpayment = $rowpayments['total_paid'] ?? 0;
} else {
    $totalpayment = 0;
}

$html = '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EVEREST Hardware Co - Invoice</title>
<style>
    @page {
        margin: 0;
        size: 22cm 29.7cm;
    }
    * { 
        font-size: 9px; 
        margin: 0; 
        padding: 0;
        font-family: Arial, sans-serif;
    }
    body {
        margin: 0;
        padding: 0;
        width: 22cm;
    }
   
    .tax-invoice-label {
        position: absolute;
        top: 2.9cm;
        left: 11.7cm;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        width: 3cm;
    }
    
    .customer-section {
        position: absolute;
        left: 2cm;
        top: 2.8cm;
        width: 9cm;
        line-height: 1.5;
        font-size: 8.5px;
    }
    
    .invoice-details {
        position: absolute;
        right: 0.8cm;
        top: 2.8cm;
        width: 5.5cm;
    }
    
    .invoice-details table {
        margin-left: 100px;
        width: 100%;
        border-collapse: collapse;
    }
    
    .invoice-details td {
        padding: 2px 0;
        font-size: 8.5px;
        border: none;
    }
    
    .items-table-wrapper {
        left: 0.7cm;
        top: 6.6cm;
        width: 20.6cm;
        margin-top: 6.6cm; 
    }
    
    table.items {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: auto;
    }
    
    table.items tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    table.items td {
        padding: 0.1cm 0.05cm;
        font-size: 8.5px;
        border: none;
        vertical-align: top;
    }

    .col-code { width: 2.9cm; padding-left: 0.15cm !important; }
    .col-description { width: 5.2cm; }
    .col-qty { width: 1.6cm; text-align: center; }
    .col-unit-price { width: 2.6cm; text-align: right; }
    .col-discount { width: 1.9cm; text-align: right; }
    .col-amount { width: 3.1cm; text-align: right; padding-right: 0.2cm !important; }
    
    /* Totals section */
    .totals-section {
        position: absolute;
        right: 0.8cm;
        bottom: 10cm;
        width: 7cm;
    }
    
    .totals-row {
        padding: 0.12cm 0.3cm;
        text-align: right;
        font-size: 9px;
        margin-bottom: 0.08cm;
    }
</style>
</head>
<body>

<div class="tax-invoice-label" style="color:red">';
// if ($isTaxCustomer) {
//     $html .= 'TAX<br>INVOICE';
// } else {
//     $html .= 'INVOICE';
// }
$html .= '</div>

<div class="customer-section">
    ' . htmlspecialchars($customerID) . '<br>
    <span style="font-size: 10px; font-weight: bold;">' . htmlspecialchars($customername) . '</span><br>
    ' . nl2br(htmlspecialchars($customeraddress)) . '<br>
    ' . htmlspecialchars($customerPhone) . '
</div>

<div class="invoice-details">
    <table>
        <tr>
            <td style="width: 0.5cm;"></td>
            <td>' . htmlspecialchars($rowinvoiceinfo['date']) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>' . htmlspecialchars($invoiceno) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>' . htmlspecialchars($pono) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>' . htmlspecialchars($location) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>' . htmlspecialchars($rowinvoiceinfo['saleref']) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>' . htmlspecialchars($salesrepphone) . '</td>
        </tr>
    </table>
</div>

<div class="items-table-wrapper">
    <table class="items">
        <tbody>';

$count = 0;
$subtotal_without_tax = 0;
$total_item_discount = 0;

while ($rowinvoicedetail = $resultinvoicedetail->fetch_assoc()) {
    $count++;
    $qty = (float)$rowinvoicedetail['qty'];
    $saleprice = (float)$rowinvoicedetail['saleprice'];
    $linediscount = (float)$rowinvoicedetail['discount'];

    $display_unit_price = 0;
    $line_total = 0;

    if ($isTaxCustomer) {
        $unit_ex_vat = $saleprice / (1 + ($tax / 100));
        $display_unit_price = $unit_ex_vat;
        $line_total = $unit_ex_vat * $qty;
        $subtotal_without_tax += $line_total;
    } else {
        $display_unit_price = $saleprice;
        $line_total = $saleprice * $qty;
        $subtotal_without_tax += $line_total;
    }

    $total_item_discount += $linediscount;
    $line_total_after_discount = $line_total - $linediscount;

    $html .= '
        <tr>
            <td class="col-code">' . htmlspecialchars($rowinvoicedetail['product_code']) . '</td>
            <td class="col-description">' . htmlspecialchars($rowinvoicedetail['product_name']) . '</td>
            <td class="col-qty">' . $qty . '</td>
            <td class="col-unit-price">' . number_format($display_unit_price, 2) . '</td>
            <td class="col-discount">' . number_format($linediscount, 2) . '</td>
            <td class="col-amount">' . number_format($line_total_after_discount, 2) . '</td>
        </tr>';
}

$html .= '
        </tbody>
    </table>
</div>
<div class="totals-section">';

$invoice_discount = (float)$rowinvoiceinfo["discount"];
$total_all_discounts = $total_item_discount + $invoice_discount;

if ($isTaxCustomer) {
    $subtotal = $subtotal_without_tax;
    $total_after_discount = $subtotal - $total_all_discounts;
    $vat_amount = $total_after_discount * ($tax / 100);
    $grand_total = $total_after_discount + $vat_amount;

    $html .= '
    <div class="totals-row">' . number_format($subtotal, 2) . '</div>
    <div class="totals-row">' . number_format($total_all_discounts, 2) . '</div>
    <div class="totals-row">' . number_format($total_after_discount, 2) . '</div>
    <div class="totals-row">' . number_format($vat_amount, 2) . '</div>
    <div class="totals-row" style="font-weight: bold;">' . number_format($grand_total, 2) . '</div>';
} else {
    $subtotal = $subtotal_without_tax;
    $grand_total = $subtotal - $total_all_discounts;

    $html .= '
    <div class="totals-row">' . number_format($subtotal, 2) . '</div>
    <div class="totals-row">' . number_format($total_all_discounts, 2) . '</div>
    <div class="totals-row">' . number_format($grand_total, 2) . '</div>
    <div class="totals-row">0.00</div>
    <div class="totals-row" style="font-weight: bold;">' . number_format($grand_total, 2) . '</div>';
}

$html .= '
</div>

</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper(array(0, 0, 623.622, 841.89), 'portrait');
$dompdf->render();
$dompdf->stream("Invoice_" . $invoiceno . ".pdf", ["Attachment" => 0]);
exit;