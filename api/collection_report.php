<?php

include 'config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');



if (isset($obj->get_date_collection_report)) {
    $from_date = isset($obj->from_date) ? $conn->real_escape_string($obj->from_date) : '';
    $to_date = isset($obj->to_date) ? $conn->real_escape_string($obj->to_date) : '';
    $chit_type_filter = isset($obj->chit_type) ? $conn->real_escape_string($obj->chit_type) : '';
    $customer_no_filter = isset($obj->customer_no) ? $conn->real_escape_string($obj->customer_no) : '';
    $payment_status_filter = isset($obj->payment_status) ? $conn->real_escape_string($obj->payment_status) : '';

    // --- NEW: Check for mandatory customer_no filter ---
    if (empty($customer_no_filter)) {
        $output["head"]["code"] = 400; // Use 400 for a client error/missing parameter
        $output["head"]["msg"] = "Please fill the customer no";
    } else {
        // Only execute the query if customer_no is present
        $query = "SELECT cu.customer_no, cu.name, DATE(chit.due_date) AS collection_date, ct.chit_type, chit.due_no, chit.due_amt, chit.paid_amt, chit.balance_amt, chit.payment_status, chit.paid_at,
                    CASE WHEN chit.payment_status = 'pending' AND chit.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue,
                    CASE WHEN chit.payment_status = 'pending' AND chit.due_date < CURDATE() THEN chit.due_amt ELSE 0 END AS overdue_amount,
                    CASE WHEN chit.payment_status = 'pending' THEN chit.due_amt ELSE 0 END AS unpaid_amount
                    FROM chit
                    JOIN customer cu ON chit.customer_id = cu.customer_id
                    JOIN chit_type ct ON chit.chit_type_id = ct.chit_type_id
                    WHERE chit.deleted_at = 0 AND chit.freeze_at = 0 AND ct.deleted_at = 0";

        $params = [];
        $types = '';
        if (!empty($from_date) && !empty($to_date)) {
            $query .= " AND chit.due_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
            $types .= 'ss';
        }
        if (!empty($chit_type_filter)) {
            $query .= " AND ct.chit_type = ?";
            $params[] = $chit_type_filter;
            $types .= 's';
        }

        // --- MODIFIED: Apply customer_no filter unconditionally since it is now mandatory ---
        $query .= " AND cu.customer_no LIKE ?";
        $params[] = "%$customer_no_filter%";
        $types .= 's';

        if (!empty($payment_status_filter)) {
            $query .= " AND chit.payment_status = ?";
            $params[] = $payment_status_filter;
            $types .= 's';
        }

        $query .= " ORDER BY collection_date ASC, cu.name, chit.due_no";

        $stmt = $conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        $stmt->close();

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Date collection report retrieved successfully";
        $output["data"] = $report;
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}


echo json_encode($output, JSON_NUMERIC_CHECK);
