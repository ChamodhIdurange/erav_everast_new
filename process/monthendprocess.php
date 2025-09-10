<?php
session_start();
require_once('../connection/db.php');

if (!isset($_POST['monthend_date']) || empty($_POST['monthend_date'])) {
  $_SESSION['msg'] = "Date is required.";
  header("Location: ../monthend.php");
  exit;
}

$selDate = $_POST['monthend_date'];
$ym = substr($selDate, 0, 7);
$monthFirst = $ym . "-01";          
$monthLast  = date("Y-m-t", strtotime($monthFirst)); 
$pretty     = date("F Y", strtotime($monthFirst));   

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

$conn->begin_transaction();

try {
  $stmt = $conn->prepare("SELECT 1 FROM tbl_month_end WHERE DATE_FORMAT(date, '%Y-%m') = ? LIMIT 1");
  $stmt->bind_param("s", $ym);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->rollback();
    $_SESSION['msg'] = "Month End already submitted for $pretty.";
    header("Location: ../monthend.php");
    exit;
  }
  $stmt->close();

  $sqlInsert = "
        INSERT INTO tbl_month_end (date, tbl_product_idtbl_product, qty, tbl_user_idtbl_user)
        SELECT ?, s.tbl_product_idtbl_product, SUM(s.qty) AS totalqty, ?
        FROM tbl_stock s
        WHERE DATE(s.updatedatetime) <= ?
        GROUP BY s.tbl_product_idtbl_product
    ";

  $stmt2 = $conn->prepare($sqlInsert);
  $stmt2->bind_param("sis", $selDate, $userId, $selDate);
  $stmt2->execute();
  $stmt2->close();

  $conn->commit();
  $_SESSION['msg'] = "✅ Month End successfully submitted for $pretty (up to $selDate).";
} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['msg'] = "❌ Error submitting Month End: " . $e->getMessage();
}

header("Location: ../monthend.php");
