<?php
session_start();
require_once('../connection/db.php');
require_once '../vendor/autoload.php';

ini_set('memory_limit', '999M');
ini_set('max_execution_time', '999');

use Dompdf\Dompdf;
use Dompdf\Options;

$taxQuery = "SELECT `rate` FROM `tbl_tax` LIMIT 1";
$taxResult = $conn->query($taxQuery);
$tax = 0;
if ($taxResult && $taxResult->num_rows > 0) {
    $taxRow = $taxResult->fetch_assoc();
    $tax = $taxRow['rate'];
}

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
$fulldiscount = 0;
$totaloutstanding = 0;
$fulloutstanding = 0;
$totalpayment = 0;
$net_total = 0;
$newtemp = 0;

// For VAT calculations
$subtotal_before_discount = 0;
$total_discount = 0;
$subtotal_after_discount = 0;
$vat_amount = 0;
$grand_total = 0;

$sqlinvoiceinfo = "
SELECT 
    `tbl_customer_order`.`confirm`,
    `tbl_customer_order`.`total`,
    `tbl_customer_order`.`podiscount`,
    `tbl_customer_order`.`vat`,
    `tbl_customer_order`.`nettotal`,
    `tbl_customer_order`.`dispatchissue`,
    `tbl_customer_order`.`delivered`,
    `tbl_customer_order`.`discount`,
    `tbl_customer_order`.`podiscount`,
    `tbl_customer_order`.`podiscountpercentage`,
    `tbl_customer_order`.`idtbl_customer_order`,
    `tbl_customer_order`.`cuspono`,
    `tbl_customer_order`.`date`,
    `tbl_locations`.`idtbl_locations`,
    `tbl_locations`.`locationname`,
    `tbl_customer`.`name`,
    `tbl_customer`.`vat_num`,
    `tbl_customer`.`address`,
    `tbl_customer`.`phone` AS 'customerphone',
    `tbl_employee`.`name` AS `saleref`,
    `tbl_employee`.`phone` AS 'salesrepphone',
    `tbl_area`.`area`,
    `tbl_user`.`name` as `username`,
    `tbl_customer_order`.`tbl_customer_idtbl_customer`,
    `tbl_customer_order`.`cuspono`
FROM `tbl_customer_order`
LEFT JOIN `tbl_locations` 
    ON `tbl_locations`.`idtbl_locations` = `tbl_customer_order`.`tbl_locations_idtbl_locations`
LEFT JOIN `tbl_customer` 
    ON `tbl_customer`.`idtbl_customer` = `tbl_customer_order`.`tbl_customer_idtbl_customer`
LEFT JOIN `tbl_employee` 
    ON `tbl_employee`.`idtbl_employee` = `tbl_customer_order`.`tbl_employee_idtbl_employee`
LEFT JOIN `tbl_area` 
    ON `tbl_area`.`idtbl_area` = `tbl_customer_order`.`tbl_area_idtbl_area`
LEFT JOIN `tbl_user` 
    ON `tbl_user`.`idtbl_user` = `tbl_customer_order`.`tbl_user_idtbl_user`
WHERE `tbl_customer_order`.`status` = 1 
  AND `tbl_customer_order`.`idtbl_customer_order` = '$recordID'
";

$resultinvoiceinfo = $conn->query($sqlinvoiceinfo);
$rowinvoiceinfo = $resultinvoiceinfo->fetch_assoc();

$customerID = $rowinvoiceinfo['tbl_customer_idtbl_customer'];
$customerPhone = $rowinvoiceinfo['customerphone'];
$salesrepPhone = $rowinvoiceinfo['salesrepphone'];
$customername = $rowinvoiceinfo['name'];
$location = $rowinvoiceinfo['locationname'];
$customeraddress = $rowinvoiceinfo['address'];
$cuspono = $rowinvoiceinfo['cuspono']; 
$pono = $rowinvoiceinfo['cuspono']; 
$porderDate = $rowinvoiceinfo['date']; 
$cusPorderId = $rowinvoiceinfo['idtbl_customer_order']; 

$confirm = $rowinvoiceinfo['confirm']; 
$dispatchissue = $rowinvoiceinfo['dispatchissue']; 
$delivered = $rowinvoiceinfo['delivered']; 
$qtyflag=0;

$vat_num = isset($rowinvoiceinfo['vat_num']) ? trim($rowinvoiceinfo['vat_num']) : '';
$isTaxCustomer = !empty($vat_num);

$sqlinvoicedetail = "SELECT `tbl_product`.`product_name`, `tbl_product`.`product_code`, `tbl_product`.`idtbl_product`, `tbl_customer_order_detail`.`orderqty`, `tbl_customer_order_detail`.`confirmqty`, `tbl_customer_order_detail`.`dispatchqty`, `tbl_customer_order_detail`.`qty`, `tbl_customer_order_detail`.`saleprice` AS `saleprice`, `tbl_product`.`saleprice` AS `prosaleprice`, `tbl_customer_order_detail`.`discount` FROM `tbl_customer_order_detail` LEFT JOIN `tbl_product` ON `tbl_product`.`idtbl_product`=`tbl_customer_order_detail`.`tbl_product_idtbl_product` WHERE `tbl_customer_order_detail`.`tbl_customer_order_idtbl_customer_order`='$recordID' AND `tbl_customer_order_detail`.`status`=1 ORDER BY `tbl_product`.`product_name` ASC";
$resultinvoicedetail = $conn->query($sqlinvoicedetail);

if($confirm == 1 && ($dispatchissue == null || $dispatchissue == 0) && ($delivered == null || $delivered == 0)){
    $qtyflag = 1;
}else if($confirm == 1 && $dispatchissue == 1  && ($delivered == null || $delivered == 0)){
    $qtyflag = 2;
}else if($confirm == 1 && $dispatchissue == 1 && $delivered == 1){
    $qtyflag = 3;
}

$invoiceNo = '-';

$getinvoicedata = "SELECT * FROM `tbl_invoice` WHERE `tbl_customer_order_idtbl_customer_order` = '$recordID'";
$resultinvoice = $conn->query($getinvoicedata);

if ($resultinvoice->num_rows > 0) {
    $rowinvoice = $resultinvoice->fetch_assoc();
    $invoiceNo = $rowinvoice['invoiceno'];
}

$html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EVEREST Hardware Co</title>
        <style>
            *{
                font-size: 10;
                margin:0.2px;
                font-family: \'San-Serif\', sans-serif;
            }
            header {
                position: fixed;
                top: 0cm;
                left: 0cm;
                right: 0cm;
                height:6cm;
            }
            footer {
                position: fixed; 
                bottom: 0cm; 
                left: 0cm; 
                right: 0.3cm;
                bottom: 7.1cm;
            }
            .leftboxtop{
                width:10.5cm;
                height:4.25cm;
            }
            .bottomtop{
                width:2.5cm;
            }
            .righttop{
                width:7.5cm;
            }
            .bottomtable{
                height:13.5cm;
            }
            .divclass{
                border-right:1px solid black;
            }
            .listView tr {
                line-height: 1;
            }
        </style>
    </head>
    <body style="margin-top:7cm; margin-bottom:5cm; height:14cm">

    
        <header>
        <table border="0" width="100%">
            <tr>
                <td colspan="3" height="1.8cm"></td>
            </tr>
            <tr>
                <td class="leftboxtop" width="10cm">';
                
                    if($isTaxCustomer){
                    $html .= '
                        <table border="0" width="100%" style="margin-top:10px; padding-left:0.3cm;">
                            <tr>
                                <td>Customer ID : ' . $customerID . '<br><span style="font-size: 1.2em; font-weight: bold;">' . $customername . '</span><br>' . $customeraddress . '<br>Tel : ' . $customerPhone . '<br><br>VAT No: ' . $vat_num . '</td>
                            </tr>
                        </table>';
                    } else {
                    $html .= '
                    <table border="0" width="100%" style="margin-top:-43px; padding-left:0.3cm;">
                        <tr>
                            <td>Customer ID : ' . $customerID . '<br><span style="font-size: 1.2em; font-weight: bold;">' . $customername . '</span><br>' . $customeraddress . '<br>Tel : ' . $customerPhone . '</td>
                        </tr>
                    </table>';
                    }
                
                    $html .= '
                    </td>
                    <td width="3cm">&nbsp;</td>
                    <td>';
                    
                    $rightTableStyle = $isTaxCustomer ? 'margin-top:15px;' : '';
                    
                    $html .= '
                        <table width="100%" height="100%" border="0" style="' . $rightTableStyle . '">
                            <tr><td width="55%" height="0.5cm"></td><td align="left">' . $porderDate . '</td></tr>
                            <tr><td height="0.5cm"></td><td align="left">' . $invoiceNo . '</td></tr>
                            <tr><td height="0.5cm"></td><td align="left">' . $cuspono . '</td></tr>
                            <tr><td height="0.5cm"></td><td align="left">' . $location . '</td></tr>
                            <tr><td height="0.5cm"></td><td align="left">' . $rowinvoiceinfo['saleref'] . '</td></tr>
                            <tr><td height="0.5cm"></td><td align="left">' . $salesrepPhone . '</td></tr>
                        </table>
                    </td>
            </tr>
        </table>
        </header>


    <main>
        <div class="">
            <table class="listView" width="100%" style="padding-left:0.3cm; padding-right:1cm; padding-top:0.2cm;">';
            $rowCount = mysqli_num_rows($resultinvoicedetail);
            $count = 0;
            $count1 = 0;
            $fullbasetot = 0;
            $actualvat = $rowinvoiceinfo['vat'];

            while ($rowinvoicedetail = $resultinvoicedetail->fetch_assoc()) {
              
                $count = $count + 1;
                $count1++;
                $qtyValue = 0;
                if ($qtyflag == 0) {
                    $qtyValue = $rowinvoicedetail['orderqty'];
                } else if ($qtyflag == 1) {
                    $qtyValue = $rowinvoicedetail['confirmqty'];
                } else if ($qtyflag == 2) {
                    $qtyValue = $rowinvoicedetail['dispatchqty'];
                } else if ($qtyflag == 3) {
                    $qtyValue = $rowinvoicedetail['qty'];
                }

                // CORRECT VAT LOGIC: Extract base price from VAT-inclusive price
                if($isTaxCustomer){
                    $base_price = $rowinvoicedetail['saleprice'] / (1 + ($actualvat / 100));
                    $base_discount = $rowinvoicedetail['discount'] / (1 + ($actualvat / 100));
                } else {
                    // For non-VAT customers, use price as-is
                    $base_price = $rowinvoicedetail['saleprice'];
                    $base_discount = $rowinvoicedetail['discount'];
                }
                
                // Calculate line total (base price before VAT)
                $basetot = $qtyValue * $base_price;
                $line_total_base = ($qtyValue * $base_price) - $base_discount;
                $fulltot += $line_total_base;

                $fullbasetot += $basetot;

                $html .= '
                    <tr>
                        <td style="width:2.3cm;">' . $count .' ' . $rowinvoicedetail['product_code'] . '</td>
                        <td style="width:8.86cm;">' . $rowinvoicedetail['product_name'] . '</td>
                        <td style="width:1.4cm;" align="center">' . $qtyValue . '</td>
                        <td style="width:2.5cm;" align="right">' . number_format($base_price, 2) . '</td>
                        <td style="width:1.3cm;" align="right">' . number_format($base_discount, 2) . '</td>
                        <td style="width:2.6cm;" align="right">' . number_format($line_total_base, 2) . '</td>
                    </tr>
                ';
                $temptotal = $qtyValue * $base_price;
                $newtemp += $temptotal;
                if ($count1 % 25 == 0) {
                    $html .= '
                        <tr>
                            <td colspan="5">This page Total Showing here. See the Next page Thank You</td>
                            <td style="width:2.6cm;" align="right">' . number_format($newtemp, 2) . '</td>
                        </tr>
                        </table>
                        <div style="page-break-before: always;"></div>
                        <table class="listView" width="100%" style="padding-left:0.3cm; padding-right:1cm; padding-top:0.2cm;">
                    ';
                    $newtemp = 0;
                }
            }
            $html .= '
            </table>';
            
            if ($resultinvoicedetail->num_rows == $count1) {
                if($isTaxCustomer){
                    // 1. Subtotal (before any discounts)
                    $subtotal_before_discount = $fulltot;
                    
                    // 2. Apply PO discount percentage
                    $po_discount_amount = $subtotal_before_discount * ($rowinvoiceinfo["podiscountpercentage"] / 100);
                    
                    // 3. Subtotal after PO discount
                    $subtotal_after_po_discount = $subtotal_before_discount - $po_discount_amount;
                    
                    // 4. Apply line item discount (already deducted in line items)
                    $line_discount = $rowinvoiceinfo["discount"] / (1 + ($actualvat / 100));
                    
                    // 5. Net amount before VAT
                    $net_before_vat = $subtotal_after_po_discount - $line_discount;
                    
                    // 6. Calculate VAT on net amount
                    $vat_amount = $subtotal_before_discount * ($actualvat / 100);
                    
                    // 7. Grand total (net + VAT)
                    $grand_total = $subtotal_after_po_discount + $vat_amount;
                    
                    $html .= '
                    <footer>
                        <div style="margin-top: -0.4cm;margin-right: -1.7cm; padding-right: 2.5cm;">
                            <table width="100%" height="100%" style="border-collapse: collapse;" border="0">
                                <tr>
                                    <td align="right">' . number_format($subtotal_before_discount, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;">' . number_format($po_discount_amount, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;font-weight: bold;">' . number_format($subtotal_after_po_discount, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;">' . number_format($vat_amount, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;font-weight: bold;">
                                        ' . number_format($grand_total, 2) . '
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </footer>';
                } else {
                    // NON-VAT INVOICE TOTALS (simpler calculation)
                    $subtotal = $rowinvoiceinfo["total"] - $rowinvoiceinfo["discount"];
                    $po_discount = $rowinvoiceinfo["podiscount"];
                    $final_total = $subtotal - $po_discount;
                    
                    $html .= '
                    <footer>
                        <div style="margin-top: -0.1cm;margin-right: -1.7cm; padding-right: 2.5cm;">
                            <table width="100%" height="100%" style="border-collapse: collapse;" border="0">
                                <tr>
                                    <td align="right">' . number_format($subtotal, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;">' . number_format($po_discount, 2) . '</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top:0.2cm;font-weight: bold;">' . number_format($final_total, 2) . '</td>
                                </tr>
                            </table>
                        </div>
                    </footer>';
                }
            }
            $html .= '  
        </div>
        
    </main>
    </body>
    </html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('21.5cm', '27.5cm', 'portrait');
$dompdf->render();
$dompdf->stream("Test.pdf", ["Attachment" => 0]);