<?php 
session_start();
require_once('../connection/db.php');

$fromdate = $_POST['fromdate'];
$todate = $_POST['todate'];
$today = date("Y-m-d");
$sql =    "SELECT  
                `d`.`tbl_product_idtbl_product`, 
                `u`.`invoiceno`, 
                `pc`.`category`, 
                -- SUM(`d`.`unitprice` * `d`.`qty`) AS `total_unitprice`,
                SUM(IF(`d`.`unitprice` = 0, `p`.`unitprice`, `d`.`unitprice`) * `d`.`qty`) AS `total_unitprice`,
                SUM(`d`.`saleprice` * `d`.`qty`) AS `total_saleprice`,
                SUM((`d`.`saleprice` - IF(`d`.`unitprice` = 0, `p`.`unitprice`, `d`.`unitprice`)) * `d`.`qty`) AS `total_profit`,
                SUM(`d`.`qty`) AS `total_qty`,
                `p`.`product_name`
            FROM `tbl_invoice` AS `u`  
            LEFT JOIN `tbl_invoice_detail` AS `d` 
                ON `d`.`tbl_invoice_idtbl_invoice` = `u`.`idtbl_invoice` 
            LEFT JOIN `tbl_customer_order` AS `o` 
                ON `u`.`tbl_customer_order_idtbl_customer_order` = `o`.`idtbl_customer_order` 
            LEFT JOIN `tbl_product` AS `p` 
                ON `p`.`idtbl_product` = `d`.`tbl_product_idtbl_product` 
            LEFT JOIN `tbl_product_category` AS `pc` 
                ON `pc`.`idtbl_product_category` = `p`.`tbl_product_category_idtbl_product_category` 
            WHERE `u`.`status` IN (1) 
                AND `o`.`delivered` = '1'
                AND `o`.`status` = '1'
                AND `d`.`status` = '1'
                AND `u`.`date` BETWEEN '$fromdate' AND '$todate'
            GROUP BY `d`.`tbl_product_idtbl_product`";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo '<table class="table table-bordered table-striped table-sm nowrap" id="dataTable">
            <thead>
                 <tr>
                    <th>#</th>
                    <th class="text-center">Product</th>
                    <th class="text-center">Category</th>
                    <th class="text-center">Qty</th>
                    <th class="text-center">Total Unit Price</th>
                    <th class="text-center">Total Sale Price</th>
                    <th class="text-center">Profit</th>
                    <th class="text-center">Profit(%)</th>
                </tr>
            </thead>
            <tbody>';
    $c=0;
    $full_profit=0;
    $unit_profit=0;
    $sale_profit=0;
    
    while ($rowstock = $result->fetch_assoc()) {
        $full_profit += $rowstock['total_profit'];
        $unit_profit += $rowstock['total_unitprice'];
        $sale_profit += $rowstock['total_saleprice'];

        $c++;
        echo '<tr>
                <td class="text-center">' . $c . '</td>
                <td class="text-center">' . $rowstock['product_name'] . '</td>
                <td class="text-center">' . $rowstock['category'] . '</td>
                <td class="text-center">' . $rowstock['total_qty'] . '</td>
                <td class="text-right">' . number_format($rowstock['total_unitprice'], 2, '.', ',')  . '</td>
                <td class="text-right">' . number_format($rowstock['total_saleprice'], 2, '.', ',')  . '</td>
                <td class="text-right">' . number_format($rowstock['total_profit'], 2, '.', ',')  . '</td>
                <td class="text-right">' . number_format((($rowstock['total_profit'] / ($rowstock['total_saleprice'] ?: 1)) * 100), 2, '.', ',') . ' %</td>
            </tr>';
    }
    echo '</tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-center"><strong>Total</strong></td>
                        <td class="text-right"><strong>' . number_format($unit_profit, 2) . '</strong></td>
                        <td class="text-right"><strong>' . number_format($sale_profit, 2) . '</strong></td>
                        <td class="text-right"><strong>' . number_format($full_profit, 2) . '</strong></td>
                        <td class="text-right"><strong>' . number_format(($full_profit/$sale_profit) * 100, 2) . '%</strong></td>
                    </tr>
                </tfoot>
            </table>';
} else {
    echo '<div class="alert alert-info" role="alert">No records found.</div>';
}
?>
