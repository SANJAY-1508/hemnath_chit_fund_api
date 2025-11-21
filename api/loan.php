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

if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `loan` WHERE `deleted_at`= 0 AND (`name` LIKE '%$search_text%' OR `loan_id` LIKE '%$search_text%')";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["loan"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Loan Details Not Found";
        $output["body"]["loan"] = [];
    }
} else if (isset($obj->name) && isset($obj->phone)) {

    $name = $obj->name;
    $phone = $obj->phone;
    $product = isset($obj->product) ? $obj->product : "";
    $product_cost = isset($obj->product_cost) ? $obj->product_cost : 0;
    $advance = isset($obj->advance) ? $obj->advance : 0;
    $balance = $product_cost - $advance;
    $create_by = isset($obj->create_by) ? $obj->create_by : "";
    $address = isset($obj->address) ? $obj->address : "";


    // Check if it's an update request
    if (isset($obj->edit_loan_id)) {
        $edit_id = $obj->edit_loan_id;

        if ($edit_id) {
            // Update existing loan
            $updateLoan = "UPDATE `loan` 
                           SET `name`='$name', `phone`='$phone', `product`='$product', `product_cost`='$product_cost', 
                               `advance`='$advance', `balance`='$balance',address='$address'
                           WHERE `loan_id`='$edit_id'";

            if ($conn->query($updateLoan)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Loan Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to update loan. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Loan not found.";
        }
    } else {
        // Check if the loan already exists
        $loanCheck = $conn->query("SELECT `loan_id` FROM `loan` WHERE `phone`='$phone' AND `deleted_at` = 0");

        if ($loanCheck->num_rows == 0) {
            // Insert new loan
            $createLoan = "INSERT INTO `loan`(`name`, `phone`, `product`, `product_cost`, `advance`, `balance`, `create_by`, `create_at`, `deleted_at`,`address`)
                           VALUES ('$name', '$phone', '$product', '$product_cost', '$advance', '$balance', '$create_by', '$timestamp', 0,'$address')";

            if ($conn->query($createLoan)) {
                $id = $conn->insert_id;
                $loan_id = "LN" . str_pad($id, 5, '0', STR_PAD_LEFT);
                $update = "UPDATE `loan` SET `loan_id`='$loan_id' WHERE `id` = $id";
                $conn->query($update);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Loan Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to create loan. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Phone Number Already Exists.";
        }
    }
} else if (isset($obj->loan_id) && isset($obj->payment_amount) && isset($obj->paid_at)) {
    $loan_id = $conn->real_escape_string($obj->loan_id);
    $payment_amount = $conn->real_escape_string($obj->payment_amount);
    $paid_at = $obj->paid_at;
    $timestamp = date('Y-m-d H:i:s');

    // Fetch the current loan details from the `loan` table
    $loanQuery = "SELECT `balance`, `name`, `phone` FROM `loan` WHERE `loan_id` = '$loan_id'";
    $loanResult = $conn->query($loanQuery);

    if ($loanResult->num_rows > 0) {
        // Get the loan details
        $loanData = $loanResult->fetch_assoc();
        $current_balance = $loanData['balance'];
        $name = $loanData['name'];
        $phone = $loanData['phone'];

        // Calculate the new balance
        $new_balance = $current_balance - $payment_amount;

        // Insert the payment into the `loan_payment` table
        $insertPaymentQuery = "INSERT INTO `loan_payment`(`loan_payment_id`, `loan_id`, `name`, `phone`, `payment_amount`, `balance`, `payment_status`, `create_at`,`paid_at`) 
                               VALUES (UUID(), '$loan_id', '$name', '$phone', '$payment_amount', '$new_balance', 'Paid', '$timestamp','$paid_at')";

        if ($conn->query($insertPaymentQuery)) {
            // Update the balance in the `loan` table
            $updateLoanQuery = "UPDATE `loan` SET `balance`='$new_balance' WHERE `loan_id`='$loan_id'";
            if ($conn->query($updateLoanQuery)) {
                // Payment and loan update successful
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Payment recorded successfully and loan balance updated.";
            } else {
                // Failed to update loan balance
                $output["head"]["code"] = 500;
                $output["head"]["msg"] = "Failed to update loan balance.";
            }
        } else {
            // Failed to insert payment
            $output["head"]["code"] = 500;
            $output["head"]["msg"] = "Failed to record payment.";
        }
    } else {
        // Loan not found
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "Loan not found.";
    }
} else if (isset($obj->from_date) && isset($obj->to_date) && isset($obj->staff_name)) {
    $from_date = $obj->from_date;
    $to_date = $obj->to_date;
    $staff_name = isset($obj->staff_name) ? $obj->staff_name : '';

    // SQL query to fetch customer and payment data
    $query = "SELECT 
               
                l.name AS customer_name, 
                l.phone AS customer_phone, 
                l.address AS customer_address, 
                l.product AS customer_product, 
                l.product_cost AS customer_product_cost, 
                l.advance AS customer_advance, 
                l.balance AS customer_balance, 
                lp.payment_amount, 
                lp.create_at AS payment_date 
              FROM 
                loan l 
              LEFT JOIN 
                loan_payment lp ON l.loan_id = lp.loan_id 
              WHERE 
                lp.create_at BETWEEN ? AND ?";

    // Initialize an array to hold the bind parameters
    $params = [];
    $types = "ss"; // 2 string parameters for from_date and to_date
    $params[] = $from_date;
    $params[] = $to_date;

    // Add staff name filter if provided
    if (!empty($staff_name)) {
        $query .= " AND lp.paid_at = ?";
        $params[] = $staff_name; // Add staff name to parameters
        $types .= "s"; // Add one more string type
    }

    // Prepare the statement
    $stmt = $conn->prepare($query);

    // Bind parameters based on the collected data
    // Use the `call_user_func_array` to bind the parameters dynamically
    $stmt->bind_param($types, ...$params);

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if any records are found
    if ($result->num_rows > 0) {
        $reportData = array();

        // Fetch results as an associative array
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Successfully retrieved loan paid report.";
        $output["data"] = $reportData;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No data found.";
        $output["data"] = [];
    }

    // Close the statement
    $stmt->close();
} else if (isset($obj->loanId)) {
    $loanId = $conn->real_escape_string($obj->loanId);

    // Query the `loan_payment` table to get payment history for the provided `loanId`
    $paymentHistoryQuery = "SELECT `id`, `loan_payment_id`, `loan_id`, `name`, `phone`, `payment_amount`, `balance`, `payment_status`, `delete_at`, `create_at` 
                            FROM `loan_payment` WHERE `loan_id` = '$loanId'";

    $result = $conn->query($paymentHistoryQuery);

    if ($result->num_rows > 0) {
        // Fetch all payment records and return them
        $paymentHistory = [];
        while ($row = $result->fetch_assoc()) {
            $paymentHistory[] = $row;
        }

        // Send response with payment history
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Payment history retrieved successfully.";
        $output["data"] = $paymentHistory;
    } else {
        // No payment history found for this loanId
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No payment history found for this loan.";
    }
} else if (isset($obj->delete_loan_id)) {
    // <<<<<<<<<<===================== This is to Delete the customer =====================>>>>>>>>>>
    $delete_loan_id = $obj->delete_loan_id;
    if (!empty($delete_loan_id)) {
        $deleteLoan = "UPDATE `loan` SET `deleted_at`=1 WHERE `loan_id`='$delete_loan_id'";
        if ($conn->query($deleteLoan) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Loan Deleted.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid data.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);
