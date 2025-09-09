<?php 
session_start();
require_once('../connection/db.php');

$fromdate = $_POST['fromdate'];
$todate = $_POST['todate'];
$replist = $_POST['replist'];
$replist = implode(", ", $replist);
$fulltotal = 0;
$today = date("Y-m-d");

$sqloutstanding =   "SELECT 
                    `e`.`name` AS 'empname',
                    `c`.`name` AS 'customername',
                    `c`.`idtbl_customer`,
                    `co`.`date`,
                    `co`.`cuspono`,
                    `i`.`invoiceno`,
                    `i`.`nettotal`,
                    DATE(`d`.`deliverDate`) AS `deliverDate`,
                    `d`.`deliverRemarks`,
                    COALESCE((SELECT SUM(`r`.`payamount`) 
                            FROM tbl_invoice_payment_has_tbl_invoice r 
                            WHERE r.tbl_invoice_idtbl_invoice = i.idtbl_invoice), 0) AS payedamount
                FROM tbl_customer_order AS co
                LEFT JOIN tbl_employee AS e ON e.idtbl_employee = co.tbl_employee_idtbl_employee
                LEFT JOIN tbl_customer AS c ON c.idtbl_customer = co.tbl_customer_idtbl_customer
                LEFT JOIN tbl_invoice AS i ON i.tbl_customer_order_idtbl_customer_order = co.idtbl_customer_order
                LEFT JOIN tbl_customer_order_delivery_data AS d ON d.tbl_customer_order_idtbl_customer_order = co.idtbl_customer_order
                WHERE co.status = '1'  
                AND i.status = '1'
                AND d.deliverDate BETWEEN '$fromdate' AND '$todate'
                AND co.delivered = '1'
                AND co.tbl_employee_idtbl_employee IN ($replist)
                AND i.paymentcomplete = '0'
                ORDER BY `c`.`name` ASC";
$resultstock = $conn->query($sqloutstanding);
$c = 0;

if ($resultstock->num_rows > 0) {
    echo '<table class="table table-bordered table-striped table-sm nowrap" id="dataTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Order</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Employee</th>
                    <th>Delivery Date</th>
                    <th>Remark</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>';
    while ($row = $resultstock->fetch_assoc()) {
        $c += 1;
        echo '<tr>
                <td class="text-center" style="padding: 3px;">' . $c . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['cuspono'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['invoiceno'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['customername'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['empname'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['deliverDate'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['deliverRemarks'] . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format($row['nettotal'], 2, '.', ',') . '</td>
            </tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-info" role="alert">No records found.</div>';
}

?>

