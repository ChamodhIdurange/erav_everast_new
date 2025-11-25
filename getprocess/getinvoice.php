<?php
include "../connection/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fromdate = $_POST['fromdate'];
    $todate = $_POST['todate'];
    $customerlist = $_POST['customerlist'];

    if (!is_array($customerlist)) {
        $customerlist = explode(',', $customerlist);
    }

    // VAT customers only
    if (!in_array('all', $customerlist)) {
        $escapedIds = array_map(function ($id) use ($conn) {
            return "'" . $conn->real_escape_string($id) . "'";
        }, $customerlist);

        $whereCustomers = "
            AND i.tbl_customer_idtbl_customer IN (" . implode(',', $escapedIds) . ")
            AND c.vat_num <> '' 
            AND c.vat_num IS NOT NULL
        ";
    } else {
        $whereCustomers = "
            AND c.vat_num <> '' 
            AND c.vat_num IS NOT NULL
        ";
    }

    // Main invoice + VAT rate
    $sql = "
        SELECT 
            i.idtbl_invoice, 
            i.date,
            i.invoiceno,
            c.name AS customer,
            c.vat_num,
            co.vat AS vat_rate,
            co.podiscountpercentage AS podiscountpercentage
        FROM tbl_invoice i
        INNER JOIN tbl_customer c 
            ON i.tbl_customer_idtbl_customer = c.idtbl_customer
        LEFT JOIN tbl_customer_order co 
            ON i.tbl_customer_order_idtbl_customer_order = co.idtbl_customer_order
        WHERE i.date BETWEEN '$fromdate' AND '$todate' 
        AND co.delivered = 1
        $whereCustomers
        ORDER BY i.invoiceno ASC
    ";

    $invoiceList = $conn->query($sql);

    if ($invoiceList && $invoiceList->num_rows > 0) {

        $output = '
        <table id="dataTable" class="display table table-striped table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th class="text-center">Invoice No</th>
                    <th class="text-center">Customer</th>
                    <th class="text-center">VAT Number</th>
                    <th class="text-center">VAT Rate(%)</th>
                    <th class="text-center">Invoice Value</th>
                    <th class="text-center">VAT Amount</th>
                </tr>
            </thead>
            <tbody>';

        $count = 1;
        $totalInvoice = 0;
        $totalVAT = 0;

        while ($inv = $invoiceList->fetch_assoc()) {

            $invoiceId = $inv['idtbl_invoice'];
            $vatRate = floatval($inv['vat_rate']);
            $podiscountpercentage = floatval($inv['podiscountpercentage']);

            // GET INVOICE ITEMS
            $itemQuery = "
                SELECT qty, saleprice, discount 
                FROM tbl_invoice_detail
                WHERE tbl_invoice_idtbl_invoice = '$invoiceId'
            ";
            $itemResult = $conn->query($itemQuery);

            $invoiceValue = 0;
            $invoiceVAT   = 0;

            while ($item = $itemResult->fetch_assoc()) {

                $qty       = floatval($item['qty']);
                $unitPrice = floatval($item['saleprice']);
                $discount  = floatval($item['discount']);

                // Apply discount to unit price
                $unitPriceAfterDisc = $unitPrice - $discount;

                // ✔ Remove VAT from unit price
                $baseUnit     = $unitPriceAfterDisc / (1 + ($vatRate / 100));
                $vatPerUnit   = $unitPriceAfterDisc - $baseUnit;

                // Multiply by qty
                $lineBase = $baseUnit * $qty;
                $lineVAT  = $vatPerUnit * $qty;

                $invoiceValue += $lineBase;
                $invoiceVAT   += $lineVAT;
            }

            $totalInvoice += $invoiceValue;
            $totalVAT += $invoiceVAT;

            $output .= '
                <tr>
                    <td>' . $count++ . '</td>
                    <td>' . htmlspecialchars($inv['date']) . '</td>
                    <td class="text-center">' . htmlspecialchars($inv['invoiceno']) . '</td>
                    <td>' . htmlspecialchars($inv['customer']) . '</td>
                    <td>' . htmlspecialchars($inv['vat_num']) . '</td>
                    <td class="text-center">' . number_format($vatRate, 2) . '</td>
                    <td class="text-right">' . number_format(($invoiceValue - ($invoiceValue * ($podiscountpercentage / 100))), 2) . '</td>
                    <td class="text-right">' . number_format($invoiceVAT, 2) . '</td>
                </tr>';
    }

        $output .= '
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;">
                    <td colspan="6" class="text-right">Total</td>
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
