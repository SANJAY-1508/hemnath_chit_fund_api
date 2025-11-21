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
    // <<<<<<<<<<===================== This is to list customers =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `customer_id`,`customer_no`,`name`, `phone`, `address`, `place`, `img`, `proof_img` 
            FROM `customer` 
            WHERE `deleted_at`= 0 AND `name` LIKE '%$search_text%'";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["customer"][$count] = $row;
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/customer/" . $row["img"];
                $output["body"]["customer"][$count]["img"] = $imgLink;
            } else {
                $output["body"]["customer"][$count]["img"] = $imgLink;
            }
            $imgLink1 = null;
            if ($row["proof_img"] != null && $row["proof_img"] != 'null' && strlen($row["proof_img"]) > 0) {
                $imgLink1 = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/customerprof/" . $row["proof_img"];
                $output["body"]["customer"][$count]["proof_img"] = $imgLink1;
            } else {
                $output["body"]["customer"][$count]["proof_img"] = $imgLink1;
            }
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Customer Details Not Found";
        $output["body"]["customer"] = [];
    }
} else if (isset($obj->name) && isset($obj->phone) && isset($obj->current_user_id)) {
    // <<<<<<<<<<===================== This is to Create and Edit customers =====================>>>>>>>>>>
    $name = $obj->name;
    $phone = $obj->phone;
    $current_user_id = $obj->current_user_id;
    $current_user_name = getUserName($current_user_id);
    $address = isset($obj->address) ? $obj->address : "";
    $place = isset($obj->place) ? $obj->place : "";
    $img = isset($obj->img) ? $obj->img : "";
    $proof_img = isset($obj->proof_img) ? $obj->proof_img : "";

    if (!empty($name) && !empty($phone) && !empty($current_user_name)) {
        if (isset($obj->edit_customer_id)) {
            $edit_id = $obj->edit_customer_id;
            if ($edit_id) {
                // Fetch old customer data for logging
                $checkOld = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$edit_id' AND `deleted_at`=0 LIMIT 1");
                $old_customer = null;
                if ($checkOld && $checkOld->num_rows > 0) {
                    $old_customer = $checkOld->fetch_assoc();
                }

                $updateCustomer = "";
                if (!empty($img) && !empty($proof_img)) {
                    $outputFilePathcustomer = "../uploads/customer/";
                    $outputFilePathcustomerprof = "../uploads/customerprof/";
                    $profile_pathcutomer = pngImageToWebP($img, $outputFilePathcustomer);
                    $profile_pathcutomerprof = pngImageToWebP($proof_img, $outputFilePathcustomerprof);
                    $updateCustomer = "UPDATE `customer` 
                                   SET `name`='$name', `phone`='$phone', `address`='$address', `place`='$place', `img`='$profile_pathcutomer', `proof_img`='$profile_pathcutomerprof' 
                                   WHERE `customer_id`='$edit_id'";
                } else {
                    $updateCustomer = "UPDATE `customer` 
                                   SET `name`='$name', `phone`='$phone', `address`='$address', `place`='$place' 
                                   WHERE `customer_id`='$edit_id'";
                }
                if ($conn->query($updateCustomer)) {
                    // Fetch or construct new customer data for logging
                    $checkNew = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$edit_id' LIMIT 1");
                    $new_customer = null;
                    if ($checkNew && $checkNew->num_rows > 0) {
                        $new_customer = $checkNew->fetch_assoc();
                    }
                    // Log history if old data exists
                    if ($old_customer && $new_customer) {
                        $remarks = 'Customer updated by ' . $current_user_name;
                        logCustomerHistory($edit_id, $new_customer['customer_no'], 'customer_update', $old_customer, $new_customer, $remarks, $current_user_id, $current_user_name);
                    }

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Customer Details Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to update. Please try again." . $conn->error;
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Customer not found.";
            }
        } else {
            $mobileCheck = $conn->query("SELECT `id` FROM `customer` WHERE `phone`='$phone' AND deleted_at = 0");

            if ($mobileCheck->num_rows == 0) {
                $createCustomer = "";
                if (!empty($img) && !empty($proof_img)) {
                    $outputFilePathcustomer = "../uploads/customer/";
                    $outputFilePathcustomerprof = "../uploads/customerprof/";
                    $profile_pathcutomer = pngImageToWebP($img, $outputFilePathcustomer);
                    $profile_pathcutomerprof = pngImageToWebP($proof_img, $outputFilePathcustomerprof);

                    $createCustomer = "INSERT INTO `customer`(`customer_id`, `name`, `phone`, `address`, `place`, `img`, `proof_img`, `create_at`, `deleted_at`) 
                               VALUES ('', '$name', '$phone', '$address', '$place', '$profile_pathcutomer', '$profile_pathcutomerprof', '$timestamp', '0')";
                } else {

                    $createCustomer = "INSERT INTO `customer`(`customer_id`, `name`, `phone`, `address`, `place`, `create_at`, `deleted_at`) 
                                   VALUES ('', '$name', '$phone', '$address', '$place', '$timestamp', '0')";
                }
                if ($conn->query($createCustomer)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('Customer', $id);
                    $customer_no = generateCustomerNo($id);
                    $update = "UPDATE `customer` SET `customer_id`='$enid',`customer_no`='$customer_no' WHERE `id` = $id";
                    $conn->query($update);

                    // Fetch new customer data for logging
                    $checkNew = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$enid' LIMIT 1");
                    $new_customer = null;
                    if ($checkNew && $checkNew->num_rows > 0) {
                        $new_customer = $checkNew->fetch_assoc();
                    }
                    // Log history
                    if ($new_customer) {
                        $remarks = 'Customer created by ' . $current_user_name;
                        logCustomerHistory($enid, $customer_no, 'customer_create', null, $new_customer, $remarks, $current_user_id, $current_user_name);
                    }

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Customer Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to create. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Mobile Number Already Exists.";
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_customer_id) && isset($obj->proof_image_delete) && isset($obj->current_user_id)) {
    $delete_customer_id = $obj->delete_customer_id;
    $current_user_id = $obj->current_user_id;
    $current_user_name = getUserName($current_user_id);
    $image_delete = $obj->proof_image_delete;

    if (!empty($delete_customer_id) && !empty($current_user_name)) {

        if ($image_delete === true) {
            // Fetch old customer data for logging
            $checkOld = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$delete_customer_id' AND `deleted_at`=0 LIMIT 1");
            $old_customer = null;
            if ($checkOld && $checkOld->num_rows > 0) {
                $old_customer = $checkOld->fetch_assoc();
            }

            $status = ImageRemove('customer_proof', $delete_customer_id);
            if ($status == "customer Image Removed Successfully") {
                // Fetch new customer data for logging
                $checkNew = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$delete_customer_id' LIMIT 1");
                $new_customer = null;
                if ($checkNew && $checkNew->num_rows > 0) {
                    $new_customer = $checkNew->fetch_assoc();
                }
                // Log history if old data exists
                if ($old_customer && $new_customer) {
                    $remarks = 'Proof image deleted by ' . $current_user_name;
                    logCustomerHistory($delete_customer_id, $new_customer['customer_no'], 'proof_image_delete', $old_customer, $new_customer, $remarks, $current_user_id, $current_user_name);
                }

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "successfully customer Image deleted !.";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "faild to deleted.please try againg.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "customer not found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_customer_id) && isset($obj->image_delete) && isset($obj->current_user_id)) {
    $delete_customer_id = $obj->delete_customer_id;
    $current_user_id = $obj->current_user_id;
    $current_user_name = getUserName($current_user_id);
    $image_delete = $obj->image_delete;

    if (!empty($delete_customer_id) && !empty($current_user_name)) {

        if ($image_delete === true) {
            // Fetch old customer data for logging
            $checkOld = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$delete_customer_id' AND `deleted_at`=0 LIMIT 1");
            $old_customer = null;
            if ($checkOld && $checkOld->num_rows > 0) {
                $old_customer = $checkOld->fetch_assoc();
            }

            $status = ImageRemove('customer', $delete_customer_id);
            if ($status == "customer Image Removed Successfully") {
                // Fetch new customer data for logging
                $checkNew = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$delete_customer_id' LIMIT 1");
                $new_customer = null;
                if ($checkNew && $checkNew->num_rows > 0) {
                    $new_customer = $checkNew->fetch_assoc();
                }
                // Log history if old data exists
                if ($old_customer && $new_customer) {
                    $remarks = 'Image deleted by ' . $current_user_name;
                    logCustomerHistory($delete_customer_id, $new_customer['customer_no'], 'image_delete', $old_customer, $new_customer, $remarks, $current_user_id, $current_user_name);
                }

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "successfully customer Image deleted !.";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "faild to deleted.please try againg.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "customer not found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_customer_id) && isset($obj->current_user_id)) {
    // <<<<<<<<<<===================== This is to Delete the customer =====================>>>>>>>>>>
    $delete_customer_id = $obj->delete_customer_id;
    $current_user_id = $obj->current_user_id;
    $current_user_name = getUserName($current_user_id);
    if (!empty($delete_customer_id) && !empty($current_user_name)) {
        // Fetch old customer data for logging
        $checkOld = $conn->query("SELECT `id`, `customer_id`, `customer_no`, `name`, `phone`, `address`, `place`, `img`, `proof_img` FROM `customer` WHERE `customer_id`='$delete_customer_id' AND `deleted_at`=0 LIMIT 1");
        $old_customer = null;
        if ($checkOld && $checkOld->num_rows > 0) {
            $old_customer = $checkOld->fetch_assoc();
        }

        $deleteCustomer = "UPDATE `customer` SET `deleted_at`=1 WHERE `customer_id`='$delete_customer_id'";
        if ($conn->query($deleteCustomer) === true) {
            // Log history if old data exists
            if ($old_customer) {
                $remarks = 'Customer deleted by ' . $current_user_name;
                logCustomerHistory($delete_customer_id, $old_customer['customer_no'], 'customer_delete', $old_customer, null, $remarks, $current_user_id, $current_user_name);
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Customer Deleted.";
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
