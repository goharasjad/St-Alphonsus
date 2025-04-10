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

$pupil_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$classes = []; // Array to hold class data for the dropdown
$parents = []; // Array to hold parent data for selection
$selected_parents = []; // Array to hold already selected parents
$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating form

// Handle pupil deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_pupil'])) {
    if ($pupil_id === false || $pupil_id <= 0) {
        $message = "Invalid pupil ID provided for deletion.";
    } else {
        // Use a Transaction for atomicity
        $mysqli->begin_transaction();
        
        try {
            // First delete records from Pupil_Parent
            $sql_delete_links = "DELETE FROM Pupil_Parent WHERE pupil_id = ?";
            $stmt_delete_links = $mysqli->prepare($sql_delete_links);
            $stmt_delete_links->bind_param("i", $pupil_id);
            
            if (!$stmt_delete_links->execute()) {
                throw new Exception("Error removing parent links: " . $stmt_delete_links->error);
            }
            
            $stmt_delete_links->close();
            
            // Then delete the pupil record
            $sql_delete_pupil = "DELETE FROM Pupils WHERE pupil_id = ?";
            $stmt_delete_pupil = $mysqli->prepare($sql_delete_pupil);
            $stmt_delete_pupil->bind_param("i", $pupil_id);
            
            if (!$stmt_delete_pupil->execute()) {
                throw new Exception("Error deleting pupil: " . $stmt_delete_pupil->error);
            }
            
            $stmt_delete_pupil->close();
            
            // All operations successful, commit transaction
            $mysqli->commit();
            $_SESSION['message'] = "Pupil deleted successfully!";
            // Redirect to pupil list
            header("Location: view_pupils.php");
            exit();
            
        } catch (Exception $e) {
            // Roll back transaction on error
            $mysqli->rollback();
            $message = "Deletion failed: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Check if we have a valid pupil ID
if ($pupil_id === false || $pupil_id <= 0) {
    $message = "Invalid pupil ID provided.";
    $pupil = null;
} else {
    // Fetch classes for the dropdown menu
    $sql_classes = "SELECT class_id, class_name FROM Classes ORDER BY class_name";
    if ($result_classes = $mysqli->query($sql_classes)) {
        while ($row = $result_classes->fetch_assoc()) {
            $classes[] = $row;
        }
        $result_classes->free();
    } else {
        $message = "Error fetching classes: " . htmlspecialchars($mysqli->error);
    }
    
    // Fetch all parents for the selection
    $sql_parents = "SELECT parent_id, first_name, last_name FROM Parents ORDER BY last_name, first_name";
    if ($result_parents = $mysqli->query($sql_parents)) {
        while ($row = $result_parents->fetch_assoc()) {
            $parents[] = $row;
        }
        $result_parents->free();
    } else {
        $message = "Error fetching parents: " . htmlspecialchars($mysqli->error);
    }
    
    // If this is not a form submission, fetch the pupil's data
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        $sql_pupil = "SELECT * FROM Pupils WHERE pupil_id = ?";
        if ($stmt_pupil = $mysqli->prepare($sql_pupil)) {
            $stmt_pupil->bind_param("i", $pupil_id);
            
            if ($stmt_pupil->execute()) {
                $result_pupil = $stmt_pupil->get_result();
                
                if ($result_pupil->num_rows > 0) {
                    $pupil = $result_pupil->fetch_assoc();
                    
                    // Populate the form_data with pupil's information
                    $form_data = $pupil;
                    
                    // Fetch currently linked parents
                    $sql_linked_parents = "SELECT parent_id FROM Pupil_Parent WHERE pupil_id = ?";
                    if ($stmt_linked = $mysqli->prepare($sql_linked_parents)) {
                        $stmt_linked->bind_param("i", $pupil_id);
                        
                        if ($stmt_linked->execute()) {
                            $linked_result = $stmt_linked->get_result();
                            
                            while ($linked = $linked_result->fetch_assoc()) {
                                $selected_parents[] = $linked['parent_id'];
                            }
                            
                            // Populate parent_ids in form_data
                            $form_data['parent_ids'] = $selected_parents;
                        }
                        
                        $stmt_linked->close();
                    }
                } else {
                    $message = "Pupil not found with ID: " . htmlspecialchars($pupil_id);
                    $pupil = null;
                }
            } else {
                $message = "Error executing query: " . htmlspecialchars($stmt_pupil->error);
            }
            
            $stmt_pupil->close();
        } else {
            $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
        }
    }
    
    // Process form submission for updates
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_pupil'])) {
        // 1. Retrieve form data
        $first_name = trim($form_data['first_name'] ?? '');
        $last_name = trim($form_data['last_name'] ?? '');
        $dob = trim($form_data['date_of_birth'] ?? '');
        $address_line1 = trim($form_data['address_line1'] ?? '');
        $address_line2 = trim($form_data['address_line2'] ?? '');
        $city = trim($form_data['city'] ?? '');
        $postcode = trim($form_data['postcode'] ?? '');
        $medical_notes = trim($form_data['medical_notes'] ?? '');
        $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
        $selected_parent_ids = $form_data['parent_ids'] ?? [];
        
        // 2. Server-Side Validation
        if (empty($first_name)) { $error_fields['first_name'] = "First name is required."; }
        if (empty($last_name)) { $error_fields['last_name'] = "Last name is required."; }
        if (empty($dob)) {
            $error_fields['date_of_birth'] = "Date of birth is required.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dob)) {
            $error_fields['date_of_birth'] = "Invalid date format (use YYYY-MM-DD).";
        }
        
        // Address validation
        if (empty($address_line1)) { $error_fields['address_line1'] = "Address Line 1 is required."; }
        if (empty($city)) { $error_fields['city'] = "City is required."; }
        if (empty($postcode)) { $error_fields['postcode'] = "Postcode is required."; }
        
        // Validate Parent Limit
        if (count($selected_parent_ids) > 2) {
            $error_fields['parent_ids'] = "A pupil can have a maximum of two parents/guardians linked.";
        } else {
            // Validate parent IDs are integers
            foreach ($selected_parent_ids as $pid) {
                if (!filter_var($pid, FILTER_VALIDATE_INT)) {
                    $error_fields['parent_ids'] = "Invalid parent selection.";
                    break;
                }
            }
        }
        
        // 3. If validation passes, update the database
        if (empty($error_fields)) {
            // Use a Transaction for atomicity
            $mysqli->begin_transaction();
            
            try {
                // Update pupil record
                $sql_update = "UPDATE Pupils SET 
                               first_name = ?, 
                               last_name = ?, 
                               date_of_birth = ?, 
                               address_line1 = ?, 
                               address_line2 = ?, 
                               city = ?, 
                               postcode = ?, 
                               medical_notes = ?, 
                               class_id = ? 
                               WHERE pupil_id = ?";
                
                $stmt_update = $mysqli->prepare($sql_update);
                
                // Determine class ID value (NULL or valid ID)
                $class_id_to_update = ($class_id !== false && $class_id > 0) ? $class_id : null;
                
                $stmt_update->bind_param("ssssssssii", 
                    $first_name, 
                    $last_name, 
                    $dob, 
                    $address_line1, 
                    $address_line2, 
                    $city, 
                    $postcode, 
                    $medical_notes, 
                    $class_id_to_update, 
                    $pupil_id
                );
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Error updating pupil: " . $stmt_update->error);
                }
                
                $stmt_update->close();
                
                // Handle parent linking - first remove all existing links
                $sql_delete_links = "DELETE FROM Pupil_Parent WHERE pupil_id = ?";
                $stmt_delete = $mysqli->prepare($sql_delete_links);
                $stmt_delete->bind_param("i", $pupil_id);
                
                if (!$stmt_delete->execute()) {
                    throw new Exception("Error removing existing parent links: " . $stmt_delete->error);
                }
                
                $stmt_delete->close();
                
                // Then add new parent links
                if (!empty($selected_parent_ids)) {
                    $sql_add_link = "INSERT INTO Pupil_Parent (pupil_id, parent_id) VALUES (?, ?)";
                    $stmt_add = $mysqli->prepare($sql_add_link);
                    
                    foreach ($selected_parent_ids as $parent_id) {
                        $valid_parent_id = filter_var($parent_id, FILTER_VALIDATE_INT);
                        if ($valid_parent_id) {
                            $stmt_add->bind_param("ii", $pupil_id, $valid_parent_id);
                            if (!$stmt_add->execute()) {
                                throw new Exception("Error linking parent ID $valid_parent_id: " . $stmt_add->error);
                            }
                        }
                    }
                    
                    $stmt_add->close();
                }
                
                // All operations successful, commit transaction
                $mysqli->commit();
                $_SESSION['message'] = "Pupil updated successfully!";
                // Redirect to view pupil details
                header("Location: view_pupil.php?id=" . $pupil_id);
                exit();
                
            } catch (Exception $e) {
                // Roll back transaction on error
                $mysqli->rollback();
                $message = "Update failed: " . htmlspecialchars($e->getMessage());
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
    <title>Edit Pupil - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">

</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Edit Pupil</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($pupil_id !== false && $pupil_id > 0): ?>
            <form action="edit_pupil.php?id=<?php echo htmlspecialchars($pupil_id); ?>" method="post" novalidate>
                <!-- Basic Pupil Details -->
                <fieldset>
                    <legend>Pupil Information</legend>
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
                        <label for="date_of_birth">Date of Birth: <span class="required">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required
                               value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['date_of_birth']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['date_of_birth'])): ?>
                            <span class="error"><?php echo $error_fields['date_of_birth']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="class_id">Assign to Class:</label>
                        <select id="class_id" name="class_id"
                                class="<?php echo isset($error_fields['class_id']) ? 'field-error' : ''; ?>">
                            <option value="">-- Select Class (Optional) --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class_id']); ?>"
                                        <?php echo (isset($form_data['class_id']) && $form_data['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($error_fields['class_id'])): ?>
                            <span class="error"><?php echo $error_fields['class_id']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="medical_notes">Medical Notes:</label>
                        <textarea id="medical_notes" name="medical_notes" rows="3"><?php echo htmlspecialchars($form_data['medical_notes'] ?? ''); ?></textarea>
                    </div>
                </fieldset>
                
                <!-- Address Details -->
                <fieldset>
                    <legend>Address</legend>
                    <div>
                        <label for="address_line1">Address Line 1: <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" required
                               value="<?php echo htmlspecialchars($form_data['address_line1'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['address_line1']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['address_line1'])): ?>
                            <span class="error"><?php echo $error_fields['address_line1']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="address_line2">Address Line 2:</label>
                        <input type="text" id="address_line2" name="address_line2" 
                               value="<?php echo htmlspecialchars($form_data['address_line2'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="city">City: <span class="required">*</span></label>
                        <input type="text" id="city" name="city" required
                               value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['city']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['city'])): ?>
                            <span class="error"><?php echo $error_fields['city']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="postcode">Postcode: <span class="required">*</span></label>
                        <input type="text" id="postcode" name="postcode" required
                               value="<?php echo htmlspecialchars($form_data['postcode'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['postcode']) ? 'field-error' : ''; ?>">
                        <?php if (isset($error_fields['postcode'])): ?>
                            <span class="error"><?php echo $error_fields['postcode']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <!-- Parent/Guardian Linking -->
                <fieldset>
                    <legend>Link Parents/Guardians (Max 2)</legend>
                    <label>Select parents/guardians:</label>
                    <?php if (isset($error_fields['parent_ids'])): ?>
                        <p class="error"><?php echo $error_fields['parent_ids']; ?></p>
                    <?php endif; ?>
                    <select name="parent_ids[]" id="parent_ids" multiple size="5">
                        <?php foreach ($parents as $parent):
                            $selected = (isset($form_data['parent_ids']) && is_array($form_data['parent_ids']) && in_array($parent['parent_id'], $form_data['parent_ids']));
                        ?>
                            <option value="<?php echo $parent['parent_id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($parent['last_name'] . ', ' . $parent['first_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p><small>Hold Ctrl (or Cmd on Mac) to select multiple. Maximum of two allowed.</small></p>
                    <p>Need to add a new parent? <a href="../parents/add_parent.php">Add Parent Here</a> (then refresh this page or select them after adding).</p>
                </fieldset>
                
                <div class="actions">
                    <button type="submit" name="update_pupil" class="btn btn-primary">Update Pupil</button>
                    <button type="submit" name="delete_pupil" class="delete-btn" 
                            onclick="return confirm('Are you sure you want to delete this pupil? This action cannot be undone.')">
                        Delete Pupil
                    </button>
                    <a href="view_pupils.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p>Invalid pupil ID or pupil not found. <a href="view_pupils.php">Return to Pupil List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
