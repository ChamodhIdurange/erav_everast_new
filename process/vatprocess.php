<?php
include "../connection/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vat_percentage'])) {
    $vat = floatval($_POST['vat_percentage']);

    $check = $conn->query("SELECT * FROM tbl_tax LIMIT 1");

    if ($check->num_rows > 0) {
        $conn->query("UPDATE tbl_tax SET rate = '$vat' WHERE id = (SELECT id FROM tbl_tax LIMIT 1)");
        echo json_encode(['status' => 'success', 'message' => 'VAT percentage updated successfully!']);
    } else {
        $conn->query("INSERT INTO tbl_tax (rate) VALUES ('$vat')");
        echo json_encode(['status' => 'success', 'message' => 'VAT percentage added successfully!']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request!']);
}

?>