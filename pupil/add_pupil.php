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

$classes = []; // Array to hold class data for the dropdown
$parents = []; // Fetch parents for selection
$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating form

// Fetch classes for the dropdown menu
$sql_classes = "SELECT class_id, class_name FROM Classes ORDER BY class_name";
if ($result_classes = $mysqli->query($sql_classes)) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
    $result_classes->free(); // Free result set
} else {
    $message = "Error fetching classes: " . htmlspecialchars($mysqli->error);
}

// Fetch parents for selection
$sql_parents = "SELECT parent_id, first_name, last_name FROM Parents ORDER BY last_name, first_name";
if ($result_parents = $mysqli->query($sql_parents)) {
    while ($row = $result_parents->fetch_assoc()) {
        $parents[] = $row;
    }
    $result_parents->free();
} else {
    $message = "Error fetching parents: " . htmlspecialchars($mysqli->error);
}

// --- Form Handling Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Retrieve form data
    $first_name = trim($form_data['first_name'] ?? ''); 
    $last_name = trim($form_data['last_name'] ?? '');
    $dob = trim($form_data['dob'] ?? '');
    $address_line1 = trim($form_data['address_line1'] ?? '');
    $address_line2 = trim($form_data['address_line2'] ?? '');
    $city = trim($form_data['city'] ?? '');
    $postcode = trim($form_data['postcode'] ?? '');
    $medical_notes = trim($form_data['medical_notes'] ?? '');
    $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $selected_parent_ids = $form_data['parent_ids'] ?? []; // Array from multi-select

    // 2. Server-Side Validation
    if (empty($first_name)) { $error_fields['first_name'] = "First name is required."; }
    if (empty($last_name)) { $error_fields['last_name'] = "Last name is required."; }
    if (empty($dob)) {
        $error_fields['dob'] = "Date of birth is required.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dob)) { // Basic YYYY-MM-DD format check
         $error_fields['dob'] = "Invalid date format (use YYYY-MM-DD).";
    }
    // Address validation
    if (empty($address_line1)) { $error_fields['address_line1'] = "Address Line 1 is required."; }
    if (empty($city)) { $error_fields['city'] = "City is required."; }
    if (empty($postcode)) { $error_fields['postcode'] = "Postcode is required."; }
    
    // Class ID can be empty/null if not assigned yet, so check if it's a valid int if provided
    if (isset($_POST['class_id']) && $_POST['class_id'] !== '' && $class_id === false) {
         $error_fields['class_id'] = "Invalid class selection.";
    }
    
    // Validate Parent Limit
    if (count($selected_parent_ids) > 2) {
        $error_fields['parent_ids'] = "A pupil can have a maximum of two parents/guardians linked.";
    } else {
        // Validate parent IDs are integers
        foreach ($selected_parent_ids as $pid) {
            if (!filter_var($pid, FILTER_VALIDATE_INT)) {
                 $error_fields['parent_ids'] = "Invalid parent selection.";
                 break; // Stop checking once an invalid one is found
            }
        }
    }

    // 3. If validation passes, proceed with database insertion
    if (empty($error_fields)) {
        // Use a Transaction for atomicity (Pupil insert + Parent links)
        $mysqli->begin_transaction();

        try {
            // 4. Insert into Pupils table
            $sql = "INSERT INTO Pupils (first_name, last_name, date_of_birth, address_line1, address_line2, city, postcode, medical_notes, class_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Prepare statement
            if ($stmt = $mysqli->prepare($sql)) {
                // Bind parameters (s=string, s=string, s=string, i=integer)
                // Use NULL for class_id if it wasn't selected or was invalid but allowed
                $class_id_to_insert = null; // Default to null
                
                if ($class_id !== false && $class_id > 0) {
                    // Verify this class_id exists in the Classes table
                    $verify_class = $mysqli->prepare("SELECT 1 FROM Classes WHERE class_id = ? LIMIT 1");
                    $verify_class->bind_param("i", $class_id);
                    $verify_class->execute();
                    $verify_class->store_result();
                    
                    // Only use the class_id if it exists in the database
                    $class_id_to_insert = ($verify_class->num_rows > 0) ? $class_id : null;
                    $verify_class->close();
                }
                $stmt->bind_param("ssssssssi", $first_name, $last_name, $dob, $address_line1, $address_line2, $city, $postcode, $medical_notes, $class_id_to_insert);

                // Execute the statement
                if (!$stmt->execute()) {
                    throw new Exception("Error adding pupil: " . $stmt->error);
                }
                
                // Get the ID of the newly inserted pupil
                $new_pupil_id = $mysqli->insert_id;
                $stmt->close();
                
                // 5. Insert into Pupil_Parent linking table (if parents were selected)
                if (!empty($selected_parent_ids) && $new_pupil_id > 0) {
                    $sql_link = "INSERT INTO Pupil_Parent (pupil_id, parent_id) VALUES (?, ?)";
                    $stmt_link = $mysqli->prepare($sql_link);
                    
                    foreach ($selected_parent_ids as $parent_id) {
                        $valid_parent_id = filter_var($parent_id, FILTER_VALIDATE_INT); // Ensure it's an integer
                        if ($valid_parent_id) {
                           $stmt_link->bind_param("ii", $new_pupil_id, $valid_parent_id);
                           if (!$stmt_link->execute()) {
                               throw new Exception("Error linking parent ID $valid_parent_id: " . $stmt_link->error);
                           }
                        }
                    }
                    $stmt_link->close();
                }
                
                // If all successful, commit the transaction
                $mysqli->commit();
                $_SESSION['message'] = "Pupil added successfully!";
                // Redirect to prevent form resubmission on refresh
                header("Location: add_pupil.php");
                exit(); 
            } else {
                throw new Exception("Error preparing statement: " . $mysqli->error);
            }
        } catch (Exception $e) {
            // An error occurred, roll back changes
            $mysqli->rollback();
            $message = "Transaction failed: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $message = "Please correct the errors below.";
    }
} 

// Check for session message and display it
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
    <title>Add New Pupil - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Add New Pupil</h1>

        <?php if ($message): ?>
            <div class="message <?php echo empty($error_fields) && $_SERVER["REQUEST_METHOD"] !== "POST" ? 'success' : (empty($error_fields) ? 'success' : 'error-msg'); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="add_pupil.php" method="post" novalidate>
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
                    <label for="dob">Date of Birth: <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" required
                           value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>"
                           class="<?php echo isset($error_fields['dob']) ? 'field-error' : ''; ?>">
                     <?php if (isset($error_fields['dob'])): ?>
                        <span class="error"><?php echo $error_fields['dob']; ?></span>
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
                <label>Select existing parents/guardians:</label>
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
                <p>Need to add a new parent? <a href="../parents/add_parent.php" >Add Parent Here</a> (then refresh this page or select them after adding).</p>
            </fieldset>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Add Pupil</button>
                <a href="view_pupils.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
