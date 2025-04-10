<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection file
require_once(__DIR__ . '/../includes/db_connect.php');

$parent_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating form
$linked_pupils_count = 0; // Count of pupils linked to this parent

// Define valid relationship types
$relationship_types = ['Mother', 'Father', 'Guardian'];

// Handle parent deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_parent'])) {
    if ($parent_id === false || $parent_id <= 0) {
        $message = "Invalid parent ID provided for deletion.";
    } else {
        // Check if parent has linked pupils first
        $sql_check_links = "SELECT COUNT(*) as count FROM Pupil_Parent WHERE parent_id = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_links)) {
            $stmt_check->bind_param("i", $parent_id);
            
            if ($stmt_check->execute()) {
                $result_check = $stmt_check->get_result();
                $row = $result_check->fetch_assoc();
                $linked_pupils_count = $row['count'];
                
                if ($linked_pupils_count > 0) {
                    $message = "Cannot delete parent: There are $linked_pupils_count pupils linked to this parent. Please unlink pupils first.";
                } else {
                    // Use a Transaction for atomicity
                    $mysqli->begin_transaction();
                    
                    try {
                        // Delete the parent record
                        $sql_delete_parent = "DELETE FROM Parents WHERE parent_id = ?";
                        $stmt_delete_parent = $mysqli->prepare($sql_delete_parent);
                        $stmt_delete_parent->bind_param("i", $parent_id);
                        
                        if (!$stmt_delete_parent->execute()) {
                            throw new Exception("Error deleting parent: " . $stmt_delete_parent->error);
                        }
                        
                        $stmt_delete_parent->close();
                        
                        // All operations successful, commit transaction
                        $mysqli->commit();
                        $_SESSION['message'] = "Parent deleted successfully!";
                        // Redirect to parent list
                        header("Location: view_parents.php");
                        exit();
                        
                    } catch (Exception $e) {
                        // Roll back transaction on error
                        $mysqli->rollback();
                        $message = "Deletion failed: " . htmlspecialchars($e->getMessage());
                    }
                }
            } else {
                $message = "Error checking linked pupils: " . htmlspecialchars($stmt_check->error);
            }
            
            $stmt_check->close();
        } else {
            $message = "Error preparing check statement: " . htmlspecialchars($mysqli->error);
        }
    }
}

// Check if we have a valid parent ID
if ($parent_id === false || $parent_id <= 0) {
    $message = "Invalid parent ID provided.";
    $parent = null;
} else {
    // If this is not a form submission for update, fetch the parent's data
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['update_parent'])) {
        $sql_parent = "SELECT * FROM Parents WHERE parent_id = ?";
        if ($stmt_parent = $mysqli->prepare($sql_parent)) {
            $stmt_parent->bind_param("i", $parent_id);
            
            if ($stmt_parent->execute()) {
                $result_parent = $stmt_parent->get_result();
                
                if ($result_parent->num_rows > 0) {
                    $parent = $result_parent->fetch_assoc();
                    
                    // Populate the form_data with parent's information
                    $form_data = $parent;
                    
                    // Count linked pupils
                    $sql_count_pupils = "SELECT COUNT(*) as count FROM Pupil_Parent WHERE parent_id = ?";
                    if ($stmt_count = $mysqli->prepare($sql_count_pupils)) {
                        $stmt_count->bind_param("i", $parent_id);
                        
                        if ($stmt_count->execute()) {
                            $result_count = $stmt_count->get_result();
                            $row = $result_count->fetch_assoc();
                            $linked_pupils_count = $row['count'];
                        }
                        
                        $stmt_count->close();
                    }
                } else {
                    $message = "Parent not found with ID: " . htmlspecialchars($parent_id);
                    $parent = null;
                }
            } else {
                $message = "Error executing query: " . htmlspecialchars($stmt_parent->error);
            }
            
            $stmt_parent->close();
        } else {
            $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
        }
    }
    
    // Process form submission for updates
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_parent'])) {
        // 1. Retrieve form data
        $first_name = trim($form_data['first_name'] ?? '');
        $last_name = trim($form_data['last_name'] ?? '');
        $address_line1 = trim($form_data['address_line1'] ?? '');
        $address_line2 = trim($form_data['address_line2'] ?? '');
        $city = trim($form_data['city'] ?? '');
        $postcode = trim($form_data['postcode'] ?? '');
        $email = trim($form_data['email'] ?? '');
        $phone = trim($form_data['phone'] ?? '');
        $relationship_type = trim($form_data['relationship_type'] ?? 'Guardian');
        
        // 2. Server-Side Validation
        if (empty($first_name)) { $error_fields['first_name'] = "First name is required."; }
        if (empty($last_name)) { $error_fields['last_name'] = "Last name is required."; }
        if (empty($address_line1)) { $error_fields['address_line1'] = "Address Line 1 is required."; }
        if (empty($city)) { $error_fields['city'] = "City is required."; }
        if (empty($postcode)) { $error_fields['postcode'] = "Postcode is required."; }
        if (empty($phone)) { $error_fields['phone'] = "Phone number is required."; }
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_fields['email'] = "Please enter a valid email address.";
        }
        
        // Validate relationship type
        if (!in_array($relationship_type, $relationship_types)) {
            $error_fields['relationship_type'] = "Please select a valid relationship type.";
        }
        
        // Check if email already exists (but not for this parent)
        if (!empty($email) && !isset($error_fields['email'])) {
            $sql_check_email = "SELECT parent_id FROM Parents WHERE email = ? AND parent_id != ?";
            if ($stmt_check = $mysqli->prepare($sql_check_email)) {
                $stmt_check->bind_param("si", $email, $parent_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows > 0) {
                    $error_fields['email'] = "This email address is already in use by another parent.";
                }
                
                $stmt_check->close();
            }
        }
        
        // 3. If validation passes, update the database
        if (empty($error_fields)) {
            $sql_update = "UPDATE Parents SET 
                          first_name = ?, 
                          last_name = ?, 
                          address_line1 = ?, 
                          address_line2 = ?, 
                          city = ?, 
                          postcode = ?, 
                          email = ?, 
                          phone = ?, 
                          relationship_type = ? 
                          WHERE parent_id = ?";
            
            if ($stmt_update = $mysqli->prepare($sql_update)) {
                // If email is empty, pass NULL to database
                $email_param = !empty($email) ? $email : null;
                $address_line2_param = !empty($address_line2) ? $address_line2 : null;
                
                $stmt_update->bind_param("sssssssssi", 
                    $first_name, 
                    $last_name, 
                    $address_line1, 
                    $address_line2_param, 
                    $city, 
                    $postcode, 
                    $email_param, 
                    $phone, 
                    $relationship_type, 
                    $parent_id
                );
                
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = "Parent updated successfully!";
                    // Redirect to view parent details
                    header("Location: view_parent.php?id=" . $parent_id);
                    exit();
                } else {
                    $message = "Error updating parent: " . htmlspecialchars($stmt_update->error);
                }
                
                $stmt_update->close();
            } else {
                $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
            }
        } else {
            $message = "Please correct the errors below.";
        }
    }
}

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Parent - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">

</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Edit Parent</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($parent_id !== false && $parent_id > 0 && isset($parent)): ?>
            <?php if ($linked_pupils_count > 0): ?>
                <div class="linked-pupils-warning">
                    <strong>Note:</strong> This parent has <?php echo $linked_pupils_count; ?> linked pupil(s). 
                    You cannot delete this parent until pupils are unlinked.
                </div>
            <?php endif; ?>
            
            <form action="edit_parent.php?id=<?php echo htmlspecialchars($parent_id); ?>" method="post" novalidate>
                <!-- Basic Parent Details -->
                <fieldset>
                    <legend>Parent Information</legend>
                    <div>
                        <label for="first_name">First Name: <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" required maxlength="50"
                               value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['first_name']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['first_name'])): ?>
                            <span class="error"><?php echo $error_fields['first_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="last_name">Last Name: <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" required maxlength="50"
                               value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['last_name']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['last_name'])): ?>
                            <span class="error"><?php echo $error_fields['last_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="relationship_type">Relationship: <span class="required">*</span></label>
                        <select id="relationship_type" name="relationship_type" required
                                class="<?php echo isset($error_fields['relationship_type']) ? 'field-error' : ''; ?>">
                            <?php foreach ($relationship_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo (isset($form_data['relationship_type']) && $form_data['relationship_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($error_fields['relationship_type'])): ?>
                            <span class="error"><?php echo $error_fields['relationship_type']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <!-- Contact Information -->
                <fieldset>
                    <legend>Contact Information</legend>
                    <div>
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" maxlength="100"
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['email']) ? 'field-error' : ''; ?>">
                        <small>Leave blank if no email available.</small>
                        <?php if (isset($error_fields['email'])): ?>
                            <span class="error"><?php echo $error_fields['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="phone">Phone Number: <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" required maxlength="20"
                               value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['phone']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['phone'])): ?>
                            <span class="error"><?php echo $error_fields['phone']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <!-- Address Details -->
                <fieldset>
                    <legend>Address</legend>
                    <div>
                        <label for="address_line1">Address Line 1: <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" required maxlength="100"
                               value="<?php echo htmlspecialchars($form_data['address_line1'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['address_line1']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['address_line1'])): ?>
                            <span class="error"><?php echo $error_fields['address_line1']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="address_line2">Address Line 2:</label>
                        <input type="text" id="address_line2" name="address_line2" maxlength="100"
                               value="<?php echo htmlspecialchars($form_data['address_line2'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="city">City: <span class="required">*</span></label>
                        <input type="text" id="city" name="city" required maxlength="50"
                               value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['city']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['city'])): ?>
                            <span class="error"><?php echo $error_fields['city']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="postcode">Postcode: <span class="required">*</span></label>
                        <input type="text" id="postcode" name="postcode" required maxlength="10"
                               value="<?php echo htmlspecialchars($form_data['postcode'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['postcode']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['postcode'])): ?>
                            <span class="error"><?php echo $error_fields['postcode']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <div class="actions">
                    <button type="submit" name="update_parent">Update Parent</button>
                    <button type="submit" name="delete_parent" class="delete-btn" 
                            <?php echo $linked_pupils_count > 0 ? 'disabled' : ''; ?>
                            onclick="return confirm('Are you sure you want to delete this parent? This action cannot be undone.')">
                        Delete Parent
                    </button>
                    <a href="view_parents.php?id=<?php echo $parent_id; ?>">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p>Invalid parent ID or parent not found. <a href="view_parents.php">Return to Parent List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
