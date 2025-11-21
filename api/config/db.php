

<?php
$name = "localhost";
$username = "root";
$password = "";
$database = "zen_chit_fund";

$conn = new mysqli($name, $username, $password, $database);

if ($conn->connect_error) {
    $output = array();
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "DB Connection Lost...";

    echo json_encode($output, JSON_NUMERIC_CHECK);
};


// <<<<<<<<<<===================== Function For Check Numbers Only =====================>>>>>>>>>>

function numericCheck($data)
{
    if (!preg_match('/[^0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets Only =====================>>>>>>>>>>

function alphaCheck($data)
{
    if (!preg_match('/[^a-zA-Z]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets and Numbers Only =====================>>>>>>>>>>

function alphaNumericCheck($data)
{
    if (!preg_match('/[^a-zA-Z0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function for checking user exist or not =====================>>>>>>>>>>
function userExist($user)
{
    global $conn;

    $checkUser = $conn->query("SELECT `name` FROM `user` WHERE `id`='$user'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

function staffExist($staff)
{
    global $conn;

    $checkUser = $conn->query("SELECT `staff_name` FROM `staff` WHERE `id`='$staff'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

function customerExist($customer)
{
    global $conn;

    $checkUser = $conn->query("SELECT `customer_name` FROM `customer` WHERE `id`='$customer'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function for get the username =====================>>>>>>>>>>
// function getUserName($user)
// {
//     global $conn;
//     $result = "";

//     $checkUser = $conn->query("SELECT `name` FROM `user` WHERE `id`='$user'");
//     if ($checkUser->num_rows > 0) {
//         if ($userData = $checkUser->fetch_assoc()) {
//             $result = $userData['name'];
//         }
//     } else {
//         $checkUser = $conn->query("SELECT `staff_name` FROM `staff` WHERE `id`='$user'");
//         if ($userData = $checkUser->fetch_assoc()) {
//             $result = $userData['staff_name'];
//         }
//     }

//     return $result;
// }

function convertUniqueName($value)
{

    $value = str_replace(' ', '', $value);
    $value = strtolower($value);

    return $value;
}

function getCategoryId($namecategory)
{
    global $conn;

    $Getcategory_id = $conn->query("SELECT `id` FROM `category` WHERE name LIKE '%$namecategory%'");
    $row = $Getcategory_id->fetch_row();
    $category_id = $row[0];

    return $category_id;
}

function getCategoryName($categoryID)
{
    global $conn;

    $sql_Category = $conn->query("SELECT `category_name` FROM `category` WHERE `id` = '$categoryID' AND `deleted_at` ='0'");
    if ($sql_Category->num_rows > 0) {
        $row = $sql_Category->fetch_row();
        $category_name = $row[0];

        return $category_name;
    } else {
        return null;
    }
}

function pngImageToWebP($data, $file_path)
{
    if (!extension_loaded('gd')) {
        error_log('GD extension is not available.');
        return false;
    }

    $imageData = base64_decode($data);
    if ($imageData === false) {
        error_log('Failed to decode base64 data.');
        return false;
    }

    try {
        $sourceImage = imagecreatefromstring($imageData);
        if ($sourceImage === false) {
            throw new Exception('Failed to create the source image. Data might not be in a recognized image format.');
        }

        date_default_timezone_set('Asia/Calcutta');
        $timestamp = date('Y-m-d H:i:s');
        $timestamp = str_replace(array(" ", ":"), "-", $timestamp);
        $file_pathnew = $file_path . $timestamp . ".webp";
        $returnFilename = $timestamp . ".webp";

        if (!imagewebp($sourceImage, $file_pathnew, 80)) {
            throw new Exception('Failed to convert image to WebP.');
        }

        imagedestroy($sourceImage);

        return $returnFilename;
    } catch (Exception $e) {
        error_log('Error: ' . $e->getMessage());
        return false;
    }
}


function isBase64ImageValid($base64Image)
{
    // Check if the provided string is a valid base64 string
    if (!preg_match('/^(data:image\/(png|jpeg|jpg|gif);base64,)/', $base64Image)) {
        return false;
    }

    // Remove the data URI prefix
    $base64Image = str_replace('data:image/png;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpeg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/gif;base64,', '', $base64Image);

    // Check if the remaining string is a valid base64 string
    if (!base64_decode($base64Image, true)) {
        return false;
    }

    // Check if the decoded data is a valid image
    $image = imagecreatefromstring(base64_decode($base64Image));
    if (!$image) {
        return false;
    }

    // Clean up resources
    imagedestroy($image);

    return true;
}


function ImageRemove($string, $id)
{
    global $conn;
    $status = "No Data Updated";
    if ($string == "user") {
        $sql_user = "UPDATE `user` SET `img`=null WHERE `user_id` ='$id' ";
        if ($conn->query($sql_user) === TRUE) {
            $status = "User Image Removed Successfully";
        } else {
            $status = "User Image Not Removed !";
        }
    } else if ($string == "customer") {
        $sql_customer = "UPDATE `customer` SET `img`=null WHERE `customer_id`='$id' ";
        if ($conn->query($sql_customer) === TRUE) {
            $status = "customer Image Removed Successfully";
        } else {
            $status = "customer Image Not Removed !";
        }
    } else if ($string == "company") {
        $sql_company = "UPDATE `company` SET  `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_company) === TRUE) {
            $status = "company Image Removed Successfully";
        } else {
            $status = "company Image Not Removed !";
        }
    } else if ($string == "customer_proof") {
        $sql_products = " UPDATE `customer` SET `proof_img`=null WHERE `customer_id`='$id' ";
        if ($conn->query($sql_products) === TRUE) {
            $status = "Customer Proff Image Removed Successfully";
        } else {
            $status = "Customer Proff Image Not Removed !";
        }
    }
    return $status;
}
function uniqueID($prefix_name, $auto_increment_id)
{

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = date('Y-m-d H:i:s');
    $encryptId = $prefix_name . "_" . $timestamp . "_" . $auto_increment_id;

    $hashid = md5($encryptId);

    return $hashid;
}
function generateCustomerNo($id)
{
    // Return the customer number with 'CNO' prefix
    return 'CNO' . $id;
}



function logCustomerHistory($customer_id, $customer_no, $action_type, $old_value = null, $new_value = null, $remarks = null, $created_by_id = null, $created_by_name = null)
{
    global $conn, $timestamp;
    $old_value = $old_value ? json_encode($old_value, JSON_NUMERIC_CHECK) : null;
    $new_value = $new_value ? json_encode($new_value, JSON_NUMERIC_CHECK) : null;
    $sql = "INSERT INTO `customer_history` (`customer_id`, `customer_no`, `action_type`, `old_value`, `new_value`, `remarks`, `created_by_id`, `created_by_name`, `created_at`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssssss", $customer_id, $customer_no, $action_type, $old_value, $new_value, $remarks, $created_by_id, $created_by_name, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

function getUserName($user)
{
    global $conn;
    $result = "";

    // Check in the `user` table first
    $checkUser = $conn->query("SELECT `name` FROM `user` WHERE `user_id`='$user' LIMIT 1");
    if ($checkUser && $checkUser->num_rows > 0) {
        $userData = $checkUser->fetch_assoc();
        $result = $userData['name'];
    }

    return $result;
}
