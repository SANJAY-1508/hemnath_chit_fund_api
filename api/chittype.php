<?php

include 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list users =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `chit_type` WHERE `deleted_at`= 0 AND `chit_type` LIKE '%$search_text%'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["chit_type"][$count] = $row;
           
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Chit Types Details Not Found";
        $output["body"]["chit_type"] = [];
    }
} else if (isset($obj->chit_type)) {
    // <<<<<<<<<<===================== This is to Create and Edit users =====================>>>>>>>>>>
    $chit_type = $obj->chit_type;
   

    if (!empty($chit_type)) {

        if (isset($obj->edit_chit_type_id)) {
            
            $edit_id = $obj->edit_chit_type_id;
            
            if ($edit_id) {
               
                    $updateChitType = "UPDATE `chit_type` SET `chit_type`='$chit_type' WHERE `chit_type_id`='$edit_id'";
                    
                if ($conn->query($updateChitType)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Chit Type Details Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.".$conn->error;
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Chit Type not found.";
            }
        } else {
               
                $createChitTYpe = "INSERT INTO `chit_type` (`chit_type`, `create_at`, `deleted_at`) VALUES ('$chit_type', '$timestamp', '0')";
            
                if ($conn->query($createChitTYpe)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('Chittype',$id);
                    $update ="UPDATE `chit_type` SET `chit_type_id`='$enid' WHERE `id` = $id";
                    $conn->query($update);
                    
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Chit Type Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
        }
    
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_chit_type_id)) {
    // <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
    $delete_chit_type_id = $obj->delete_chit_type_id;
    if (!empty($delete_chit_type_id)) {
        if ($delete_chit_type_id) {
            $deleteChittype = "UPDATE `chit_type` SET `deleted_at`=1 WHERE `chit_type_id`='$delete_chit_type_id'";
            if ($conn->query($deleteChittype) === true) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Chit Type Deleted.";
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
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);

?>
