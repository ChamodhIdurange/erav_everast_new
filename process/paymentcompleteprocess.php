<?php 
session_start();
if(!isset($_SESSION['userid'])){header ("Location:../index.php");}
require_once('../connection/db.php'); 

$userID=$_SESSION['userid'];
$record=$_GET['record'];
$type=$_GET['type'];
$currentDate = date('mdY');
$updatedatetime = date('Y-m-d h:i:s');


if($type==1){$value=1;}
else if($type==2){$value=2;}
else if($type==3){$value=3;}

$sqlcheck="SELECT `tbl_invoice`.`paymentcomplete`, `tbl_invoice`.`nettotal`, SUM(`tbl_invoice_payment_has_tbl_invoice`.`payamount`) AS `payamount` FROM `tbl_invoice` LEFT JOIN `tbl_invoice_payment_has_tbl_invoice` ON `tbl_invoice_payment_has_tbl_invoice`.`tbl_invoice_idtbl_invoice`=`tbl_invoice`.`idtbl_invoice` WHERE `tbl_invoice`.`status`=1 AND `tbl_invoice`.`paymentcomplete`=0 AND `tbl_invoice`.`idtbl_invoice` = '$record'";
$result=$conn->query($sqlcheck);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $nettotal = $row['nettotal'];
    $payamount = $row['payamount'];

    if($nettotal - $payamount == 0 ){
        $sql="UPDATE `tbl_invoice` SET `paymentcomplete`='$value',`tbl_user_idtbl_user`='$userID' WHERE `idtbl_invoice`='$record'";
        if($conn->query($sql)==true){
            header("Location:../invoiceview.php?action=$type");
        }
        else{
            header("Location:../invoiceview.php?action=5");
        }
    }else{
        header("Location:../invoiceview.php?action=5");
    }
}else {
    header("Location:../invoiceview.php?action=5");
}

?>