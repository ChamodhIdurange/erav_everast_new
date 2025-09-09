<?php 
session_start();
require_once('../connection/db.php');

$fromdate = $_POST['fromdate'];
$todate = $_POST['todate'];
$replist = $_POST['replist'];
$replist = implode(", ", $replist);
$fulltotal = 0;
$fullbelow30total = 0;
$full30to60total = 0;
$full60to90total = 0;
$fullover90total = 0;
$today = date("Y-m-d");


$sqloutstanding =    "SELECT 
                    `e`.`name` AS 'empname',
                    `c`.`name` AS 'customername',
                    `c`.`address`,
                    `c`.`phone`,
                    `c`.`idtbl_customer`,
                    `co`.`date`,
                    `co`.`remark`,
                    DATEDIFF(CURDATE(), `co`.`date`) AS `date_difference`,
                    `i`.`invoiceno`,
                    `i`.`nettotal`,
                    `d`.`deliverDate`,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), co.date) < 30 THEN i.nettotal
                        ELSE 0 
                    END AS below_30,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), co.date) BETWEEN 30 AND 59 THEN i.nettotal
                        ELSE 0 
                    END AS between_30_60,

                    CASE 
                        WHEN DATEDIFF(CURDATE(), co.date) BETWEEN 60 AND 89 THEN i.nettotal
                        ELSE 0 
                    END AS between_60_90,

                    CASE 
                        WHEN DATEDIFF(CURDATE(), co.date) >= 90 THEN i.nettotal
                        ELSE 0 
                    END AS over_90,
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
                AND co.date BETWEEN '$fromdate' AND '$todate'
                AND co.delivered = '1'
                AND co.tbl_employee_idtbl_employee IN ($replist)
                AND i.paymentcomplete = '0'
                ORDER BY `c`.`name` ASC, `c`.`address` ASC, `co`.`date` ASC";
$resultstock = $conn->query($sqloutstanding);
if ($resultstock->num_rows > 0) {
    $c = 1;
    $customerId = -99;
    $oldCustomerId = -99;

    $netInvoiceAmount = 0;
    $netBelow30 = 0;
    $net30to60 = 0;
    $net60to90 = 0;
    $netover90 = 0;
    $netDeductions = 0;
    $netBalance = 0;
    $fulltotal = 0;
    $fullbelow30total = 0;
    $full30to60total = 0;
    $full60to90total = 0;
    $fullover90total = 0;

    echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
            <div style="text-align: left;">
                <h4 style="margin: 0; font-size: 14px;">EVEREST HARDWARE CO. (PVT) LTD</h4>
                <p style="margin: 2px 0; font-size: 10px;">
                    #363/10/01, Malwatte, Kal-Eliya (Mirigama) <br>
                    033 4 950 951 | <a href="mailto:info@everesthardware.lk" style="font-size: 10px;">info@everesthardware.lk</a>
                </p>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 16px;">Debtor Aging Details Report</h2>
            </div>
        </div>';

    while ($row = $resultstock->fetch_assoc()) {
        $customerId = $row['idtbl_customer'];

        if ($c != 1 && $oldCustomerId != $customerId) {
            echo '
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f1f1f1; font-weight: bold; font-size: 10px;">
                            <td colspan="4" class="text-center">Total</td>
                            <td class="text-right">' . number_format($netInvoiceAmount, 2) . '</td>
                            <td class="text-right">' . number_format($netBelow30, 2) . '</td>
                            <td class="text-right">' . number_format($net30to60, 2) . '</td>
                            <td class="text-right">' . number_format($net60to90, 2) . '</td>
                            <td class="text-right">' . number_format($netover90, 2) . '</td>
                        </tr>
                    </tfoot>
                </table>
            </div>';
    
            $fulltotal += $netBalance;
            $fullbelow30total += $netBelow30;
            $full30to60total += $net30to60;
            $full60to90total += $net60to90;
            $fullover90total += $netover90;

            $netInvoiceAmount = 0;
            $netBelow30 = 0;
            $net30to60 = 0;
            $net60to90 = 0;
            $netover90 = 0;
            $netDeductions = 0;
            $netBalance = 0;
            $c = 1;
        }
        $netInvoiceAmount += $row['nettotal'];
        $netBelow30 += ($row['below_30'] > 0) ? ($row['below_30'] - $row['payedamount']) : 0;
        $net30to60  += ($row['between_30_60'] > 0) ? ($row['between_30_60'] - $row['payedamount']) : 0;
        $net60to90  += ($row['between_60_90'] > 0) ? ($row['between_60_90'] - $row['payedamount']) : 0;
        $netover90  += ($row['over_90'] > 0) ? ($row['over_90'] - $row['payedamount']) : 0;
        $netDeductions += $row['payedamount'];
        $netBalance += $row['nettotal'] - $row['payedamount'];

        if ($oldCustomerId != $customerId) {
            $oldCustomerId = $customerId;

            echo '
               <div style="background: #fff; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 0px; margin-bottom: 3px; border-left: 3px solid #004085;">
                <h3 style="margin: 0 0 4px; font-size: 13px; font-weight: bold; color: #004085;">' . $row['customername'] . '</h3>
                <p style="margin: 0 0 6px; font-size: 10px; color: #555;">' . $row['address'] . '</p>
                <p style="margin: 0 0 6px; font-size: 10px; color: #555;">' . $row['phone'] . '</p>

                <table class="table table-bordered table-sm nowrap" style="background: #fff; font-size: 10px; width: 100%;">
                    <thead style="background: #004085; color: #fff;">
                        <tr>
                            <th class="text-center" style="padding: 3px; width: 40px;">No</th>
                            <th class="text-center" style="padding: 3px; width: 100px;">Date</th>
                            <th class="text-center" style="padding: 3px; width: 120px;">Invoice</th>
                            <th class="text-center" style="padding: 3px; width: 100px;">Rep</th>
                            <th class="text-right" style="padding: 3px; width: 100px;">Amount</th>
                            <th class="text-right" style="padding: 3px; width: 100px;">Below 30</th>
                            <th class="text-right" style="padding: 3px; width: 100px;">30 - 60</th>
                            <th class="text-right" style="padding: 3px; width: 100px;">60 - 90</th>
                            <th class="text-right" style="padding: 3px; width: 100px;">Over 90</th>
                        </tr>
                    </thead>
                <tbody>';
        }

        echo '
            <tr>
                <td class="text-center" style="padding: 3px;">' . $c . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['date'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['invoiceno'] . '</td>
                <td class="text-center" style="padding: 3px;">' . $row['empname'] . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format($row['nettotal'], 2, '.', ',') . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format(($row['below_30'] > 0 ? $row['below_30'] - $row['payedamount'] : 0), 2, '.', ',') . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format(($row['between_30_60'] > 0 ? $row['between_30_60'] - $row['payedamount'] : 0), 2, '.', ',') . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format(($row['between_60_90'] > 0 ? $row['between_60_90'] - $row['payedamount'] : 0), 2, '.', ',') . '</td>
                <td class="text-right" style="padding: 3px;">' . number_format(($row['over_90'] > 0 ? $row['over_90'] - $row['payedamount'] : 0), 2, '.', ',') . '</td>
            </tr>';
        $c++;
    }
    $fulltotal += $netBalance;
    $fullbelow30total += $netBelow30;
    $full30to60total += $net30to60;
    $full60to90total += $net60to90;
    $fullover90total += $netover90;

    echo '
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f1f1; font-weight: bold; font-size: 10px;">
                <td colspan="4" class="text-center">Total</td>
                <td class="text-right">' . number_format($netInvoiceAmount, 2) . '</td>
                <td class="text-right">' . number_format($netBelow30, 2) . '</td>
                <td class="text-right">' . number_format($net30to60, 2) . '</td>
                <td class="text-right">' . number_format($net60to90, 2) . '</td>
                <td class="text-right">' . number_format($netover90, 2) . '</td>
            </tr>
        </tfoot>
    </table>
    <div style="float: right; text-align: right;">
        <h4 style="margin-top: 10px; font-size: 12px; color: red; display: flex; justify-content: space-between; width: 250px;">
            <span>Below 30 Total:</span> <span>Rs.' . number_format($fullbelow30total, 2) . '</span>
        </h4>
        <h4 style="margin-top: 10px; font-size: 12px; color: orange; display: flex; justify-content: space-between; width: 250px;">
            <span>30 - 60 Total:</span> <span>Rs.' . number_format($full30to60total, 2) . '</span>
        </h4>
        <h4 style="margin-top: 10px; font-size: 12px; color: green; display: flex; justify-content: space-between; width: 250px;">
            <span>60 - 90 Total:</span> <span>Rs.' . number_format($full60to90total, 2) . '</span>
        </h4>
        <h4 style="margin-top: 10px; font-size: 12px; color: blue; display: flex; justify-content: space-between; width: 250px;">
            <span>Over 90 Total:</span> <span>Rs.' . number_format($fullover90total, 2) . '</span>
        </h4>
        <h4 style="margin-top: 10px; font-size: 12px; color: purple; display: flex; justify-content: space-between; width: 250px;">
            <span>Gross Total:</span> <span>Rs.' . number_format($fulltotal, 2) . '</span>
        </h4>
    </div>
</div>';
} else {
    echo '<div class="alert alert-info" role="alert" style="font-size: 12px;">No records found.</div>';
}

?>

