<?php
session_start();
require_once('../connection/db.php');
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get tax rate
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

$invoiceId = $_GET['invoiceId'];

$sqlinvoice = "SELECT * FROM `tbl_invoice` WHERE `idtbl_invoice`='$invoiceId'";
$resultinvoice = $conn->query($sqlinvoice);
$rowinvoice = $resultinvoice->fetch_assoc();
$nettotal = $rowinvoice['nettotal'];
$total = $rowinvoice['total'];
$discount = $rowinvoice['discount'];
$invoiceno = $rowinvoice['invoiceno'];

// Get customer order details including VAT info
$sqlCustomerOrder = "
SELECT 
    co.*,
    c.vat_num
FROM tbl_customer_order co
LEFT JOIN tbl_customer c ON c.idtbl_customer = co.tbl_customer_idtbl_customer
WHERE co.idtbl_customer_order = (
    SELECT tbl_customer_order_idtbl_customer_order 
    FROM tbl_invoice 
    WHERE idtbl_invoice = '$invoiceId'
)";
$resultCustomerOrder = $conn->query($sqlCustomerOrder);
$rowCustomerOrder = $resultCustomerOrder->fetch_assoc();

$vat_num = isset($rowCustomerOrder['vat_num']) ? trim($rowCustomerOrder['vat_num']) : '';
$isTaxCustomer = !empty($vat_num);
$actualvat = isset($rowCustomerOrder['vat']) ? $rowCustomerOrder['vat'] : $tax;

// Get invoice detail for VAT calculations
$recordID = $rowCustomerOrder['idtbl_customer_order'];
$sqlinvoicedetail = "
SELECT 
    `tbl_customer_order_detail`.`qty`,
    `tbl_customer_order_detail`.`saleprice`,
    `tbl_customer_order_detail`.`discount`
FROM `tbl_customer_order_detail`
WHERE `tbl_customer_order_detail`.`tbl_customer_order_idtbl_customer_order`='$recordID' 
  AND `tbl_customer_order_detail`.`status`=1
";
$resultinvoicedetail = $conn->query($sqlinvoicedetail);

// Calculate using the same logic as invoice PDF
$fulltot = 0;
$fullbasetot = 0;

while ($rowinvoicedetail = $resultinvoicedetail->fetch_assoc()) {
    $qtyValue = $rowinvoicedetail['qty'];
    
    // EXACT SAME VAT LOGIC as invoice PDF
    if($isTaxCustomer){
        $base_price = $rowinvoicedetail['saleprice'] / (1 + ($actualvat / 100));
        $base_discount = $rowinvoicedetail['discount'] / (1 + ($actualvat / 100));
    } else {
        $base_price = $rowinvoicedetail['saleprice'];
        $base_discount = $rowinvoicedetail['discount'];
    }
    
    // Calculate line total (base price before VAT)
    $basetot = $qtyValue * $base_price;
    $line_total_base = ($qtyValue * $base_price) - $base_discount;
    $fulltot += $line_total_base;
    $fullbasetot += $basetot;
}

// VAT calculation variables
$subtotal_before_discount = 0;
$po_discount_amount = 0;
$subtotal_after_po_discount = 0;
$vat_amount = 0;
$grand_total = 0;
$final_payable_amount = 0;

if($isTaxCustomer){
    // 1. Subtotal (before any discounts)
    $subtotal_before_discount = $fulltot;
    
    // 2. Apply PO discount percentage
    $po_discount_amount = $subtotal_before_discount * ($rowCustomerOrder["podiscountpercentage"] / 100);
    
    // 3. Subtotal after PO discount
    $subtotal_after_po_discount = $subtotal_before_discount - $po_discount_amount;
    
    // 4. Calculate VAT on subtotal before discount
    $vat_amount = $subtotal_before_discount * ($actualvat / 100);
    
    // 5. Grand total (subtotal after PO discount + VAT)
    $grand_total = $subtotal_after_po_discount + $vat_amount;
    
    // 6. This is the final amount to be paid
    $final_payable_amount = $grand_total;
} else {
    // For non-VAT customers
    $final_payable_amount = $total - $discount;
}

$sqlpayment = "SELECT SUM(`ih`.`payamount`) AS 'paymentmade' FROM `tbl_invoice_payment` AS `ip` LEFT JOIN `tbl_invoice_payment_has_tbl_invoice` AS `ih` ON (`ip`.`idtbl_invoice_payment` = `ih`.`tbl_invoice_payment_idtbl_invoice_payment`) WHERE `ih`.`tbl_invoice_idtbl_invoice`='$invoiceId' GROUP BY `ih`.`tbl_invoice_idtbl_invoice`";
$resultpayment = $conn->query($sqlpayment);
$rowpayment = $resultpayment->fetch_assoc();
$paymentmade = $rowpayment['paymentmade'];

$sqlpaymentbank = "SELECT `id`.`method`, `id`.`amount`, `id`.`receiptno`, `id`.`chequeno` FROM `tbl_invoice_payment_detail` AS `id` LEFT JOIN `tbl_invoice_payment_has_tbl_invoice` AS `ih` ON (`id`.`tbl_invoice_payment_idtbl_invoice_payment` = `ih`.`tbl_invoice_payment_idtbl_invoice_payment`) WHERE `ih`.`tbl_invoice_idtbl_invoice`='$invoiceId'";
$resultpaymentbank = $conn->query($sqlpaymentbank);

$html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EVEREST Hardware Co</title>
        <style>
            body {
                margin: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans",
                    sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
                line-height: 1.5;
            }
            .tg  {border-collapse:collapse;border-spacing:0;}
            .tg td{font-family:Arial, sans-serif;font-size:14px;padding:5px 10px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:black;}
            .tg th{font-family:Arial, sans-serif;font-size:14px;font-weight:normal;padding:5px 10px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:black;}
            .tg .tg-btmp{font-weight:bold;color:#000;text-align:left;vertical-align:top}
            .tg .tg-0lax{text-align:left;vertical-align:top}

            .receipt-header {
                font-family: Arial, sans-serif;
                margin-bottom: 20px;
                text-align: right;
                position: relative;
            }

            .head-label {
                background-color: #000;
                color: #FFF;
                padding: 5px 15px;
                border-radius: 5px;
                width: 160px;
                text-align: center;
                position: absolute;
                top: -30px;
                right: 0;
            }

            h4 {
                margin: 30px 0 0;
                font-weight: bold;
            }

            p {
                margin: 0;
                line-height: 1.5;
            }
        </style>
    </head>
    <body>
        <table border="0" width="100%">
            <tr>
                <td style="vertical-align: bottom;padding-bottom: 17px;">Invoice No: ' . $invoiceno . '</td>
                <td style="text-align: right;">
                    <div class="receipt-header">
                        <div class="header-content">
                            <div class="head-label">PAYMENT RECEIPT</div>
                        </div>
                        <h4>Everest Hardware (Pvt) Ltd Test</h4>
                        <p>
                            Head Office : No.J174/20, Araliya Uyana, Kegalla.<br>
                            Branch : No.107, Paragammana, Kegalla.<br>
                            Tel: 0094-35-2232924 | Fax: 0094-77-9001546<br>
                            support@everesthardware.com
                        </p>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <hr style="border-color: #000;margin-top:5px; margin-bottom:5px;">
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table class="tg" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>';

if ($isTaxCustomer) {
    $html .= '
                                <th style="text-align: right">Subtotal</th>
                                <th style="text-align: right">PO Discount</th>
                                <th style="text-align: right">Subtotal After Disc</th>
                                <th style="text-align: right">VAT (' . number_format($actualvat, 0) . '%)</th>
                                <th style="text-align: right">Total</th>';
} else {
    $html .= '
                                <th style="text-align: right">Invoice Amount</th>
                                <th style="text-align: right">Discount</th>';
}

$html .= '
                                <th style="text-align: right">Payment</th>
                            </tr>
                        </thead>
                        <tbody>';

$i = 1;
$html .= '<tr>
            <td>' . $i . '</td>
            <td>' . $invoiceno . '</td>';

if ($isTaxCustomer) {
    $html .= '
            <td style="text-align: right">' . number_format($subtotal_before_discount, 2) . '</td>
            <td style="text-align: right">' . number_format($po_discount_amount, 2) . '</td>
            <td style="text-align: right">' . number_format($subtotal_after_po_discount, 2) . '</td>
            <td style="text-align: right">' . number_format($vat_amount, 2) . '</td>
            <td style="text-align: right">' . number_format($grand_total, 2) . '</td>';
} else {
    $html .= '
            <td style="text-align: right">' . number_format($total, 2) . '</td>
            <td style="text-align: right">' . number_format($discount, 2) . '</td>';
}

$html .= '
            <td style="text-align: right">' . number_format($final_payable_amount, 2) . '</td>
        </tr>';

$html .= '</tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td width="50%" style="vertical-align: top;">
                    <div style="margin-bottom: 15px; margin-top: 15px;">Payment Methods</div>
                    <table class="tg" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Receipt</th>
                                <th>Cheque No</th>
                                <th style="text-align: right">Payment</th>
                            </tr>
                        </thead>
                        <tbody>';

while ($rowpaymentbank = $resultpaymentbank->fetch_assoc()) {
    $html .= '<tr>
                <td>';
    if ($rowpaymentbank['method'] == 1) {
        $html .= 'Cash';
    } else if ($rowpaymentbank['method'] == 2) {
        $html .= 'Cheque';
    } else if ($rowpaymentbank['method'] == 3) {
        $html .= 'Credit Note';
    } else if ($rowpaymentbank['method'] == 4) {
        $html .= 'Exfess Note';
    }
    $html .= '</td>
                <td>' . $rowpaymentbank['receiptno'] . '</td>
                <td>' . $rowpaymentbank['chequeno'] . '</td>
                <td style="text-align: right">' . number_format($rowpaymentbank['amount'], 2) . '</td>
            </tr>';
}

$html .= '<tbody>
                    </table>
                </td>
                <td style="vertical-align: top;">
                    <table width="100%">
                        <tr>
                            <td width="65%" style="text-align: right">Payment Made</td>
                            <td style="text-align: right">Rs. ' . number_format($paymentmade, 2) . '</td>
                        </tr>
                        <tr>
                            <td width="65%" style="text-align: right">Balance</td>
                            <td style="text-align: right">Rs. ' . number_format($final_payable_amount - $paymentmade, 2) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream("Test.pdf", ["Attachment" => 0]);
?>