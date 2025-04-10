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

$teacher_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating form
$assigned_classes_count = 0; // Count of classes assigned to this teacher

// Define valid background check statuses
$background_check_statuses = ['Pending', 'Cleared', 'Expired', 'Not Required'];

// Handle teacher deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_teacher'])) {
    if ($teacher_id === false || $teacher_id <= 0) {
        $message = "Invalid teacher ID provided for deletion.";
    } else {
        // Check if teacher has assigned classes first
        $sql_check_classes = "SELECT COUNT(*) as count FROM Classes WHERE teacher_id = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_classes)) {
            $stmt_check->bind_param("i", $teacher_id);
            
            if ($stmt_check->execute()) {
                $result_check = $stmt_check->get_result();
                $row = $result_check->fetch_assoc();
                $assigned_classes_count = $row['count'];
                
                if ($assigned_classes_count > 0) {
                    $message = "Cannot delete teacher: There are $assigned_classes_count classes assigned to this teacher. Please reassign the classes first.";
                } else {
                    // Use a Transaction for atomicity
                    $mysqli->begin_transaction();
                    
                    try {
                        // Delete the teacher record
                        $sql_delete_teacher = "DELETE FROM Teachers WHERE teacher_id = ?";
                        $stmt_delete_teacher = $mysqli->prepare($sql_delete_teacher);
                        $stmt_delete_teacher->bind_param("i", $teacher_id);
                        
                        if (!$stmt_delete_teacher->execute()) {
                            throw new Exception("Error deleting teacher: " . $stmt_delete_teacher->error);
                        }
                        
                        $stmt_delete_teacher->close();
                        
                        // All operations successful, commit transaction
                        $mysqli->commit();
                        $_SESSION['message'] = "Teacher deleted successfully!";
                        // Redirect to teacher list
                        header("Location: view_teachers.php");
                        exit();
                        
                    } catch (Exception $e) {
                        // Roll back transaction on error
                        $mysqli->rollback();
                        $message = "Deletion failed: " . htmlspecialchars($e->getMessage());
                    }
                }
            } else {
                $message = "Error checking assigned classes: " . htmlspecialchars($stmt_check->error);
            }
            
            $stmt_check->close();
        } else {
            $message = "Error preparing check statement: " . htmlspecialchars($mysqli->error);
        }
    }
}

// Check if we have a valid teacher ID
if ($teacher_id === false || $teacher_id <= 0) {
    $message = "Invalid teacher ID provided.";
    $teacher = null;
} else {
    // If this is not a form submission for update, fetch the teacher's data
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['update_teacher'])) {
        $sql_teacher = "SELECT * FROM Teachers WHERE teacher_id = ?";
        if ($stmt_teacher = $mysqli->prepare($sql_teacher)) {
            $stmt_teacher->bind_param("i", $teacher_id);
            
            if ($stmt_teacher->execute()) {
                $result_teacher = $stmt_teacher->get_result();
                
                if ($result_teacher->num_rows > 0) {
                    $teacher = $result_teacher->fetch_assoc();
                    
                    // Populate the form_data with teacher's information
                    $form_data = $teacher;
                    
                    // Count assigned classes
                    $sql_count_classes = "SELECT COUNT(*) as count FROM Classes WHERE teacher_id = ?";
                    if ($stmt_count = $mysqli->prepare($sql_count_classes)) {
                        $stmt_count->bind_param("i", $teacher_id);
                        
                        if ($stmt_count->execute()) {
                            $result_count = $stmt_count->get_result();
                            $row = $result_count->fetch_assoc();
                            $assigned_classes_count = $row['count'];
                        }
                        
                        $stmt_count->close();
                    }
                } else {
                    $message = "Teacher not found with ID: " . htmlspecialchars($teacher_id);
                    $teacher = null;
                }
            } else {
                $message = "Error executing query: " . htmlspecialchars($stmt_teacher->error);
            }
            
            $stmt_teacher->close();
        } else {
            $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
        }
    }
    
    // Process form submission for updates
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_teacher'])) {
        // 1. Retrieve form data
        $first_name = trim($form_data['first_name'] ?? '');
        $last_name = trim($form_data['last_name'] ?? '');
        $address_line1 = trim($form_data['address_line1'] ?? '');
        $address_line2 = trim($form_data['address_line2'] ?? '');
        $city = trim($form_data['city'] ?? '');
        $postcode = trim($form_data['postcode'] ?? '');
        $email = trim($form_data['email'] ?? '');
        $phone = trim($form_data['phone'] ?? '');
        $annual_salary = trim($form_data['annual_salary'] ?? '');
        $background_check_status = trim($form_data['background_check_status'] ?? 'Pending');
        
        // 2. Server-Side Validation
        if (empty($first_name)) { $error_fields['first_name'] = "First name is required."; }
        if (empty($last_name)) { $error_fields['last_name'] = "Last name is required."; }
        if (empty($address_line1)) { $error_fields['address_line1'] = "Address Line 1 is required."; }
        if (empty($city)) { $error_fields['city'] = "City is required."; }
        if (empty($postcode)) { $error_fields['postcode'] = "Postcode is required."; }
        if (empty($email)) { $error_fields['email'] = "Email is required."; }
        if (empty($annual_salary)) { $error_fields['annual_salary'] = "Annual salary is required."; }
        
        // Validate email
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_fields['email'] = "Please enter a valid email address.";
        }
        
        // Validate annual salary
        if (!empty($annual_salary)) {
            if (!is_numeric($annual_salary) || $annual_salary <= 0) {
                $error_fields['annual_salary'] = "Please enter a valid annual salary (positive number).";
            }
        }
        
        // Validate background check status
        if (!in_array($background_check_status, $background_check_statuses)) {
            $error_fields['background_check_status'] = "Please select a valid background check status.";
        }
        
        // Check if email already exists (but not for this teacher)
        if (!empty($email) && !isset($error_fields['email'])) {
            $sql_check_email = "SELECT teacher_id FROM Teachers WHERE email = ? AND teacher_id != ?";
            if ($stmt_check = $mysqli->prepare($sql_check_email)) {
                $stmt_check->bind_param("si", $email, $teacher_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows > 0) {
                    $error_fields['email'] = "This email address is already in use by another teacher.";
                }
                
                $stmt_check->close();
            }
        }
        
        // 3. If validation passes, update the database
        if (empty($error_fields)) {
            $sql_update = "UPDATE Teachers SET 
                          first_name = ?, 
                          last_name = ?, 
                          address_line1 = ?, 
                          address_line2 = ?, 
                          city = ?, 
                          postcode = ?, 
                          email = ?, 
                          phone = ?, 
                          annual_salary = ?,
                          background_check_status = ? 
                          WHERE teacher_id = ?";
            
            if ($stmt_update = $mysqli->prepare($sql_update)) {
                // If address_line2 or phone is empty, pass NULL to database
                $address_line2_param = !empty($address_line2) ? $address_line2 : null;
                $phone_param = !empty($phone) ? $phone : null;
                
                $stmt_update->bind_param("ssssssssdsi", 
                    $first_name, 
                    $last_name, 
                    $address_line1, 
                    $address_line2_param, 
                    $city, 
                    $postcode, 
                    $email, 
                    $phone_param, 
                    $annual_salary,
                    $background_check_status,
                    $teacher_id
                );
                
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = "Teacher updated successfully!";
                    // Redirect to view teacher details
                    header("Location: view_teacher.php?id=" . $teacher_id);
                    exit();
                } else {
                    $message = "Error updating teacher: " . htmlspecialchars($stmt_update->error);
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
    <title>Edit Teacher - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Edit Teacher</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($teacher_id !== false && $teacher_id > 0 && isset($teacher)): ?>
            <?php if ($assigned_classes_count > 0): ?>
                <div class="assigned-classes-warning">
                    <strong>Note:</strong> This teacher has <?php echo $assigned_classes_count; ?> assigned class(es). 
                    You cannot delete this teacher until the classes are reassigned.
                </div>
            <?php endif; ?>
            
            <form action="edit_teacher.php?id=<?php echo htmlspecialchars($teacher_id); ?>" method="post" novalidate>
                <!-- Basic Teacher Details -->
                <fieldset>
                    <legend>Teacher Information</legend>
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
                        <label for="email">Email Address: <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required maxlength="100"
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['email']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['email'])): ?>
                            <span class="error"><?php echo $error_fields['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="phone">Phone Number:</label>
                        <input type="tel" id="phone" name="phone" maxlength="20"
                               value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['phone']) ? 'field-error' : ''; ?>">
                        <small>Leave blank if not available.</small>
                        <?php if (isset($error_fields['phone'])): ?>
                            <span class="error"><?php echo $error_fields['phone']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <!-- Employment Details -->
                <fieldset>
                    <legend>Employment Details</legend>
                    <div>
                        <label for="annual_salary">Annual Salary (£): <span class="required">*</span></label>
                        <input type="number" id="annual_salary" name="annual_salary" required step="0.01" min="0"
                               value="<?php echo htmlspecialchars($form_data['annual_salary'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['annual_salary']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['annual_salary'])): ?>
                            <span class="error"><?php echo $error_fields['annual_salary']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="background_check_status">Background Check Status: <span class="required">*</span></label>
                        <select id="background_check_status" name="background_check_status" required
                                class="<?php echo isset($error_fields['background_check_status']) ? 'field-error' : ''; ?>">
                            <?php foreach ($background_check_statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" 
                                        <?php echo (isset($form_data['background_check_status']) && $form_data['background_check_status'] == $status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($error_fields['background_check_status'])): ?>
                            <span class="error"><?php echo $error_fields['background_check_status']; ?></span>
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
                    <button type="submit" name="update_teacher">Update Teacher</button>
                    <button type="submit" name="delete_teacher" class="delete-btn" 
                            <?php echo $assigned_classes_count > 0 ? 'disabled' : ''; ?>
                            onclick="return confirm('Are you sure you want to delete this teacher? This action cannot be undone.')">
                        Delete Teacher
                    </button>
                    <a href="view_teachers.php?id=<?php echo $teacher_id; ?>">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p>Invalid teacher ID or teacher not found. <a href="view_teachers.php">Return to Teacher List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
