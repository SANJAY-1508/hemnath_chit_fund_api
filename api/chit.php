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

    // Query to get overdue records
    $query = "SELECT * FROM `chit` WHERE `deleted_at`= 0 AND `name` LIKE '%$search_text%' AND `due_date` < '$timestamp' AND `payment_status` = 'pending'";
    $overdue_result = $conn->query($query);

    // Query to get current day due records
    $query = "SELECT * FROM `chit` WHERE `deleted_at`= 0 AND `name` LIKE '%$search_text%' AND `due_date` = CURDATE() AND `payment_status` = 'pending'";
    $current_due_result = $conn->query($query);

    $query = "SELECT * FROM `chit` WHERE `name` LIKE '%$search_text%' AND `payment_status` = 'paid'";
    $paid_result = $conn->query($query);

    //  $query = "SELECT * FROM `chit` WHERE `customer_no` LIKE '%$search_text%'";
    //  $all_result = $conn->query($query);

    $query = "
    SELECT 
    chit_id, 
    chit_no, 
    due_amt,
    customer_id, 
    chit_type_id,
    name, 
    chit_type,
    customer_no, 
    MIN(create_at) AS create_at,
    freeze_at,
    freeze_command
FROM 
    chit
WHERE `name` LIKE '%$search_text%' GROUP BY 
    chit_id, 
    chit_no,
    chit_type,
    due_amt,
    customer_id, 
    name, 
    customer_no
";
    $all_result = $conn->query($query);
    // Initialize response arrays
    $overdue_records = [];
    $current_due_records = [];
    $paid_records = [];
    $all_records = [];

    if ($all_result->num_rows > 0) {
        while ($row = $all_result->fetch_assoc()) {
            $all_records[] = $row;
        }
    }

    // Fetch overdue records
    if ($overdue_result->num_rows > 0) {
        while ($row = $overdue_result->fetch_assoc()) {
            $overdue_records[] = $row;
        }
    }

    // Fetch current day due records
    if ($current_due_result->num_rows > 0) {
        while ($row = $current_due_result->fetch_assoc()) {
            $current_due_records[] = $row;
        }
    }
    if ($paid_result->num_rows > 0) {

        while ($row = $paid_result->fetch_assoc()) {
            $paid_records[] = $row;
        }
    }
    if (!empty($overdue_records) || !empty($current_due_records) || !empty($paid_records) || !empty($all_records)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "records found";
        $output["data"]["all"] = $all_records;
        $output["data"]["overdue"] = $overdue_records;
        $output["data"]["current_due"] = $current_due_records;
        $output["data"]["paid"] = $paid_records;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No records found";
        $output["data"]["all"] = [];
        $output["data"]["overdue"] = [];
        $output["data"]["current_due"] = [];
        $output["data"]["paid"] = [];
    }
} else if (isset($obj->dashboard)) {
    $dashboard = $obj->dashboard;

    // Get the current timestamp for overdue checks
    $timestamp = date('Y-m-d');

    // Query to get the count of all customers
    $query = "SELECT COUNT(*) AS customer_count FROM `customer` WHERE deleted_at =0";
    $customer_count_result = $conn->query($query);
    $customer_count = $customer_count_result->fetch_assoc()['customer_count'];

    // Query to get the count of overdue records
    $query = "SELECT COUNT(*) AS overdue_count FROM `chit` WHERE `deleted_at` = 0 AND `due_date` < '$timestamp' AND `payment_status` = 'pending'";
    $overdue_count_result = $conn->query($query);
    $overdue_count = $overdue_count_result->fetch_assoc()['overdue_count'];

    // Query to get the count of current day due records
    $query = "SELECT COUNT(*) AS current_due_count FROM `chit` WHERE `deleted_at` = 0 AND `due_date` = CURDATE() AND `payment_status` = 'pending'";
    $current_due_count_result = $conn->query($query);
    $current_due_count = $current_due_count_result->fetch_assoc()['current_due_count'];

    // Query to get the count of paid records
    $query = "SELECT COUNT(*) AS paid_count FROM `chit` WHERE `payment_status` = 'paid'";
    $paid_count_result = $conn->query($query);
    $paid_count = $paid_count_result->fetch_assoc()['paid_count'];

    // Prepare the response
    $output = [
        "head" => [
            "code" => 200,
            "msg" => "Dashboard data retrieved successfully"
        ],
        "data" => [
            "customer_count" => $customer_count,
            "overdue_count" => $overdue_count,
            "current_due_count" => $current_due_count,
            "paid_count" => $paid_count
        ]
    ];
} else if (isset($obj->chit_id) && isset($obj->due_no) && isset($obj->payment_method) && isset($obj->current_user_id) && isset($obj->paid_amount) && isset($obj->payment_date)) {
    $chit_id = $conn->real_escape_string($obj->chit_id);
    $due_no = $conn->real_escape_string($obj->due_no);
    $payment_method = $conn->real_escape_string($obj->payment_method);
    $paid_by = $conn->real_escape_string($obj->current_user_id);
    $paid_amt = $conn->real_escape_string($obj->paid_amount);
    $paid_at = $obj->payment_date;
    $payment_status = 'paid';
    $balance_amt = 0;

    $sql = "UPDATE `chit` 
            SET `balance_amt` = '$balance_amt', 
                `paid_amt` = '$paid_amt', 
                `payment_status` = '$payment_status', 
                `payment_method` = '$payment_method', 
                `paid_by` = '$paid_by', 
                `paid_at` = '$paid_at' 
            WHERE `chit_id` = '$chit_id' 
            AND `due_no` = '$due_no' 
            AND `deleted_at` = 0";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Payment updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error updating payment: " . $conn->error]);
    }
} elseif (isset($obj->chit_id)) {
    $chit_id = $obj->chit_id;

    // Query to get chit and customer records using JOIN
    $query = "
    SELECT chit.*, customer.phone, customer.address, customer.place
    FROM `chit`
    JOIN `customer` ON chit.customer_id = customer.customer_id
    WHERE chit.deleted_at = 0 
    AND chit.chit_id = '$chit_id'
    ORDER BY chit.chit_service_id ASC
";

    $chit_id_result = $conn->query($query);

    // Initialize response arrays
    $chit_id_records = [];

    // Fetch chit records along with customer details
    if ($chit_id_result->num_rows > 0) {
        while ($row = $chit_id_result->fetch_assoc()) {
            $chit_id_records[] = $row;
        }
    }

    // Check and set response
    if (!empty($chit_id_records)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Records found";
        $output["data"]["chit"] = $chit_id_records;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No records found";
    }
} else if (isset($obj->customer_no) && isset($obj->chit_type_id)) {
    $customer_no = $obj->customer_no;
    $chit_type_id = $obj->chit_type_id;

    // Query to get overdue records
    $query = "SELECT chit.*,customer.phone, customer.address, customer.place FROM `chit` JOIN `customer` ON chit.customer_id = customer.customer_id  WHERE chit.deleted_at = 0  AND chit.chit_no LIKE '%$customer_no%' AND chit.chit_type_id = '$chit_type_id'";
    $chit_id_result = $conn->query($query);

    // Initialize response arrays
    $chit_id_records = [];

    // Fetch overdue records
    if ($chit_id_result->num_rows > 0) {
        while ($row = $chit_id_result->fetch_assoc()) {
            $chit_id_records[] = $row;
        }
    }

    // Fetch current day due records
    if (!empty($chit_id_records)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "records found";
        $output["data"]["chit"] = $chit_id_records;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No records found";
    }
} else if (isset($obj->customer_details) && isset($obj->chit_type) && isset($obj->chit_due_amount) && isset($obj->emi_method) && isset($obj->current_user_id) && isset($obj->customer_id) && isset($obj->chit_type_id) && isset($obj->chit_no)) {

    // Split customer_details into customer_no and name
    $customer_details = explode(' - ', $obj->customer_details);
    if (count($customer_details) != 2) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid customer details format";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $customer_no = $customer_details[0];
    $name = $customer_details[1];

    $chit_due_amount = $obj->chit_due_amount;
    $customer_id = $obj->customer_id;
    $chit_type_id = $obj->chit_type_id;
    $create_by = $obj->current_user_id;
    $paid_amt = 0;
    $payment_status = 'pending';
    $deleted_at = 0;
    $chit_type = $obj->chit_type;
    $chit_no = $obj->chit_no;

    // Prepare the statement

    $mobileCheck = $conn->query("SELECT * FROM `chit` WHERE `chit_no` = '$chit_no' AND `chit_type` = '$chit_type'");

    if ($mobileCheck->num_rows == 0) {
        if ($obj->emi_method == 'Weekly') {
            $weekly_due_amount = $chit_due_amount;
            $enid = null;
            for ($i = 1; $i <= 52; $i++) {
                $due_no = $i;
                $due_date = date('Y-m-d', strtotime("+$i week"));
                $balance_amt = $weekly_due_amount;

                $stmt = ("INSERT INTO `chit` 
        (`customer_id`, `name`, `customer_no`, `chit_type`, 
        `chit_type_id`,`chit_no`, `due_no`, `due_amt`, `due_date`, `create_by`, `create_at`, `balance_amt`, 
        `paid_amt`, `payment_status`, `deleted_at`) 
        VALUES ('$customer_id', '$name', '$customer_no', '$chit_type', '$chit_type_id','$chit_no', '$due_no', '$weekly_due_amount', '$due_date', '$create_by', '$timestamp', '$balance_amt', '$paid_amt', '$payment_status','$deleted_at')");


                if ($conn->query($stmt)) {
                    if ($due_no == 1) {
                        $id = $conn->insert_id;
                        $enid = uniqueID('chit', $id);
                    }
                    $update = "UPDATE `chit` SET `chit_id`='$enid' WHERE `chit_service_id` = '$conn->insert_id'";
                    $conn->query($update);
                }
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Chit records created successfully for weekly EMI method";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "emi method not found";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Chit Number And Chit Type Already Exsits.!";
    }
} else if (isset($obj->chit_close_id) && isset($obj->close_reason)) {
    // <<<<<<<<<<===================== This is to Delete the customer =====================>>>>>>>>>>
    $chit_close_id = $obj->chit_close_id;
    $close_reason = $obj->close_reason;

    if (!empty($chit_close_id)) {
        $freezechit = "UPDATE `chit` SET `freeze_at`=1,`freeze_command`='$close_reason' WHERE `chit_id`='$chit_close_id'";
        if ($conn->query($freezechit) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Chit Closed.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid data.";
    }
} else if (isset($obj->from_date) && isset($obj->to_date) && isset($obj->payment_status)) {
    $from_date = $obj->from_date;
    $to_date = $obj->to_date;
    $staff_name = isset($obj->staff_name) ? $obj->staff_name : '';
    $chit_type = isset($obj->chit_type) ? $obj->chit_type : '';
    $payment_status = $obj->payment_status;

    // Build the query with the filters
    $query = "SELECT `chit_service_id`, `chit_id`, `chit_no`, `customer_id`, `name`, 
              `customer_no`, `chit_type`, `chit_type_id`, `due_no`, `due_amt`, `due_date`, 
              `create_by`, `create_at`, `balance_amt`, `paid_amt`, `payment_status`, 
              `payment_method`, `paid_by`, `paid_at`, `freeze_at`, `freeze_command`, `deleted_at` 
              FROM `chit` WHERE `due_date` BETWEEN ? AND ? AND `payment_status` = ?";

    // Initialize an array to hold the bind parameters
    $params = [];
    $types = "sss"; // 3 string parameters for from_date, to_date, and payment_status
    $params[] = $from_date;
    $params[] = $to_date;
    $params[] = $payment_status;

    // Add staff name filter if provided
    if (!empty($staff_name)) {
        $query .= " AND `create_by` = ?";
        $params[] = $staff_name; // Add staff name to parameters
        $types .= "s"; // Add one more string type
    }

    // Add chit type filter if provided
    if (!empty($chit_type)) {
        $query .= " AND `chit_type` = ?";
        $params[] = $chit_type; // Add chit type to parameters
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
        $output["head"]["msg"] = "Successfully";
        $output["data"] = $reportData;
        // Return the data in JSON format

    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "data not found.";
        $output["data"] = [];
        // No records found

    }

    // Close the statement
    $stmt->close();
} else if (isset($obj->get_monthly_data) || isset($obj->get_daily_data)) {

    date_default_timezone_set('Asia/Calcutta');
    $current_date = date('Y-m-d');

    /*-------------------------------------------
        1. DAILY DATA (User selected a month)
    --------------------------------------------*/
    if (isset($obj->get_daily_data) && isset($obj->month)) {

        $month = $obj->month;            // e.g. "2025-11"
        $from_date = $month . "-01";     // Start of month
        $to_date = date("Y-m-t", strtotime($from_date)); // End of month

        $query_daily = "
            SELECT 
                DATE(due_date) AS day,
                SUM(CASE WHEN payment_status = 'paid' THEN paid_amt ELSE 0 END) AS Paid,
                SUM(CASE WHEN payment_status = 'pending' THEN due_amt ELSE 0 END) AS UnPaid
            FROM chit
            WHERE due_date BETWEEN ? AND ? 
              AND deleted_at = 0
            GROUP BY DATE(due_date)
            ORDER BY DATE(due_date) ASC
        ";

        $stmt = $conn->prepare($query_daily);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $daily_data = [];
        while ($row = $result->fetch_assoc()) {
            $daily_data[$row['day']] = $row;
        }

        $stmt->close();

        // Generate full days for the month
        $full_daily = [];
        $start = new DateTime($from_date);
        $end = new DateTime($to_date);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $day_str = $date->format('Y-m-d');
            $full_daily[] = [
                'day' => $day_str,
                'Paid' => isset($daily_data[$day_str]) ? (int)$daily_data[$day_str]['Paid'] : 0,
                'UnPaid' => isset($daily_data[$day_str]) ? (int)$daily_data[$day_str]['UnPaid'] : 0
            ];
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Daily data retrieved successfully";
        $output["data"] = $full_daily;
    }
    /*-------------------------------------------
        2. MONTHLY DATA (Full Year: Jan to Dec)
    --------------------------------------------*/ else {

        $year = isset($obj->year) ? $obj->year : date('Y');
        $start_date = $year . '-01-01';
        $end_date = (intval($year) + 1) . '-01-01';

        $query_monthly = "
            SELECT 
                DATE_FORMAT(due_date, '%Y-%m') AS month_key,
                DATE_FORMAT(due_date, '%b') AS name,
                SUM(CASE WHEN payment_status = 'paid' THEN paid_amt ELSE 0 END) AS Paid,
                SUM(CASE WHEN payment_status = 'pending' THEN due_amt ELSE 0 END) AS UnPaid
            FROM chit
            WHERE due_date >= ? AND due_date < ?
              AND deleted_at = 0
            GROUP BY YEAR(due_date), MONTH(due_date)
            ORDER BY YEAR(due_date), MONTH(due_date)
        ";

        $stmt = $conn->prepare($query_monthly);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $monthly_data = [];
        while ($row = $result->fetch_assoc()) {
            $monthly_data[$row['month_key']] = $row;
        }

        $stmt->close();

        // Generate full 12 months for the year
        $full_monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $month_key = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            $name = date('M', mktime(0, 0, 0, $m, 1, $year));
            $full_monthly[] = [
                'month_key' => $month_key,
                'name' => $name,
                'Paid' => 0,
                'UnPaid' => 0
            ];
        }

        // Merge queried data into full months
        foreach ($full_monthly as &$item) {
            if (isset($monthly_data[$item['month_key']])) {
                $item['Paid'] = $monthly_data[$item['month_key']]['Paid'];
                $item['UnPaid'] = $monthly_data[$item['month_key']]['UnPaid'];
            }
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Monthly data retrieved successfully";
        $output["data"] = $full_monthly;
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);
