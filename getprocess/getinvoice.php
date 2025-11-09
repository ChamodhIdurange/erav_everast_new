<?php
include "../connection/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fromdate = $_POST['fromdate'];
    $todate = $_POST['todate'];
    $customerlist = $_POST['customerlist'];

    if (!is_array($customerlist)) {
        $customerlist = explode(',', $customerlist);
    }

    $taxQuery = "SELECT `rate` FROM `tbl_tax` LIMIT 1";
    $taxResult = $conn->query($taxQuery);
    $taxRate = 0;
    if ($taxResult && $taxResult->num_rows > 0) {
        $row = $taxResult->fetch_assoc();
        $taxRate = floatval($row['rate']);
    }

    if (!in_array('all', $customerlist)) {
        $escapedIds = array_map(function ($id) use ($conn) {
            return "'" . $conn->real_escape_string($id) . "'";
        }, $customerlist);

        $whereCustomers = "AND i.tbl_customer_idtbl_customer IN (" . implode(',', $escapedIds) . ") 
                           AND c.vat_num <> '' 
                           AND c.vat_num IS NOT NULL";
    } else {
        $whereCustomers = "AND c.vat_num <> '' 
                           AND c.vat_num IS NOT NULL";
    }

    $sql = "
        SELECT 
            i.idtbl_invoice, 
            i.invoiceno, 
            c.name AS customer, 
            c.vat_num, 
            i.nettotal AS invoice_value
        FROM tbl_invoice i
        INNER JOIN tbl_customer c 
            ON i.tbl_customer_idtbl_customer = c.idtbl_customer
        WHERE i.date BETWEEN '$fromdate' AND '$todate'
        $whereCustomers
        ORDER BY i.invoiceno ASC
    ";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $output = '
        <table id="dataTable" class="display table table-striped table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="text-center">Invoice No</th>
                    <th class="text-center">Customer</th>
                    <th class="text-center">VAT Number</th>
                    <th class="text-center">Invoice Value</th>
                    <th class="text-center">VAT Amount (' . number_format($taxRate, 2) . '%)</th>
                </tr>
            </thead>
            <tbody>';

        $count = 1;
        $totalInvoice = 0;
        $totalVAT = 0;

        while ($row = $result->fetch_assoc()) {
            $invoiceValue = floatval($row['invoice_value']);
            $vatAmount = $invoiceValue * ($taxRate / 100);
            $totalInvoice += $invoiceValue;
            $totalVAT += $vatAmount;

            $output .= '
            <tr>
                <td>' . $count++ . '</td>
                <td class="text-center">' . htmlspecialchars($row['invoiceno']) . '</td>
                <td class="text-center">' . htmlspecialchars($row['customer']) . '</td>
                <td class="text-center">' . htmlspecialchars($row['vat_num']) . '</td>
                <td class="text-right">' . number_format($invoiceValue, 2) . '</td>
                <td class="text-right">' . number_format($vatAmount, 2) . '</td>
            </tr>';
        }

        $output .= '
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;">
                    <td colspan="4" class="text-right">Total</td>
                    <td class="text-right">' . number_format($totalInvoice, 2) . '</td>
                    <td class="text-right">' . number_format($totalVAT, 2) . '</td>
                </tr>
            </tfoot>
        </table>';

        echo $output;
    } else {
        echo '<div class="alert alert-warning text-center">No invoices found for the selected period.</div>';
    }
}
