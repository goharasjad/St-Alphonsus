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

$class_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating form

// Define the class year options
$class_year_options = [
    'Reception Year', 'Year One', 'Year Two', 'Year Three', 'Year Four', 'Year Five', 'Year Six'
];

// Fetch all teachers for the dropdown
$teachers = [];
$sql_teachers = "SELECT teacher_id, first_name, last_name FROM Teachers ORDER BY last_name, first_name";
if ($result_teachers = $mysqli->query($sql_teachers)) {
    while ($row = $result_teachers->fetch_assoc()) {
        $teachers[] = $row;
    }
    $result_teachers->free();
} else {
    $message = "Error fetching teachers: " . htmlspecialchars($mysqli->error);
}

// Get original teacher information if there is one
$original_teacher_id = null;
$original_teacher_name = '';

if ($class_id !== false && $class_id > 0 && !isset($_POST['update_class'])) {
    $sql_original_teacher = "SELECT t.teacher_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name 
                            FROM Classes c
                            LEFT JOIN Teachers t ON c.teacher_id = t.teacher_id
                            WHERE c.class_id = ?";
    
    if ($stmt_original = $mysqli->prepare($sql_original_teacher)) {
        $stmt_original->bind_param("i", $class_id);
        
        if ($stmt_original->execute()) {
            $result_original = $stmt_original->get_result();
            
            if ($result_original->num_rows > 0) {
                $original_data = $result_original->fetch_assoc();
                $original_teacher_id = $original_data['teacher_id'];
                $original_teacher_name = $original_data['teacher_name'];
            }
        }
        
        $stmt_original->close();
    }
}

// Handle class deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_class'])) {
    if ($class_id === false || $class_id <= 0) {
        $message = "Invalid class ID provided for deletion.";
    } else {
        $sql_delete_class = "DELETE FROM Classes WHERE class_id = ?";
        $stmt_delete_class = $mysqli->prepare($sql_delete_class);
        $stmt_delete_class->bind_param("i", $class_id);
        
        if ($stmt_delete_class->execute()) {
            $_SESSION['message'] = "Class deleted successfully!";
            // Redirect to class list
            header("Location: view_classes.php");
            exit();
        } else {
            $message = "Error deleting class: " . htmlspecialchars($stmt_delete_class->error);
        }
        
        $stmt_delete_class->close();
    }
}

// Check if we have a valid class ID
if ($class_id === false || $class_id <= 0) {
    $message = "Invalid class ID provided.";
    $class = null;
} else {
    // If this is not a form submission for update, fetch the class's data
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['update_class'])) {
        $sql_class = "SELECT * FROM Classes WHERE class_id = ?";
        if ($stmt_class = $mysqli->prepare($sql_class)) {
            $stmt_class->bind_param("i", $class_id);
            
            if ($stmt_class->execute()) {
                $result_class = $stmt_class->get_result();
                
                if ($result_class->num_rows > 0) {
                    $class = $result_class->fetch_assoc();
                    
                    // Populate the form_data with class's information
                    $form_data = $class;
                } else {
                    $message = "Class not found with ID: " . htmlspecialchars($class_id);
                    $class = null;
                }
            } else {
                $message = "Error executing query: " . htmlspecialchars($stmt_class->error);
            }
            
            $stmt_class->close();
        } else {
            $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
        }
    }
    
    // Process form submission for updates
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_class'])) {
        // 1. Retrieve form data
        $class_name = trim($form_data['class_name'] ?? '');
        $capacity = trim($form_data['capacity'] ?? '');
        $teacher_id = !empty($form_data['teacher_id']) ? filter_var($form_data['teacher_id'], FILTER_VALIDATE_INT) : null;
        
        // 2. Server-Side Validation
        if (empty($class_name)) { $error_fields['class_name'] = "Class name is required."; }
        if (empty($capacity)) { $error_fields['capacity'] = "Capacity is required."; }
        
        // Validate class name is from the predefined options
        if (!empty($class_name) && !in_array($class_name, $class_year_options)) {
            $error_fields['class_name'] = "Please select a valid class name.";
        }

        // Validate capacity
        if (!empty($capacity)) {
            if (!is_numeric($capacity) || $capacity <= 0 || $capacity > 40) {
                $error_fields['capacity'] = "Please enter a valid capacity (between 1 and 40).";
            }
        }

        // Validate teacher_id if provided
        if ($teacher_id !== null && $teacher_id !== false) {
            $sql_check_teacher = "SELECT teacher_id FROM Teachers WHERE teacher_id = ?";
            if ($stmt_check = $mysqli->prepare($sql_check_teacher)) {
                $stmt_check->bind_param("i", $teacher_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows === 0) {
                    $error_fields['teacher_id'] = "Selected teacher does not exist.";
                }
                
                $stmt_check->close();
            }
            
            // Check if teacher is already assigned to a different class
            if (!isset($error_fields['teacher_id'])) {
                $sql_check_teacher_assignment = "SELECT class_id FROM Classes WHERE teacher_id = ? AND class_id != ?";
                if ($stmt_check = $mysqli->prepare($sql_check_teacher_assignment)) {
                    $stmt_check->bind_param("ii", $teacher_id, $class_id);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    
                    if ($stmt_check->num_rows > 0) {
                        $error_fields['teacher_id'] = "This teacher is already assigned to another class. One teacher can only be assigned to one class.";
                    }
                    
                    $stmt_check->close();
                }
            }
        }

        // Check if class name already exists (but not for this class)
        if (!empty($class_name) && !isset($error_fields['class_name'])) {
            $sql_check_name = "SELECT class_id FROM Classes WHERE class_name = ? AND class_id != ?";
            if ($stmt_check = $mysqli->prepare($sql_check_name)) {
                $stmt_check->bind_param("si", $class_name, $class_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows > 0) {
                    $error_fields['class_name'] = "This class name is already in use by another class.";
                }
                
                $stmt_check->close();
            }
        }
        
        // 3. If validation passes, update the database
        if (empty($error_fields)) {
            $sql_update = "UPDATE Classes SET class_name = ?, capacity = ?, teacher_id = ? WHERE class_id = ?";
            
            if ($stmt_update = $mysqli->prepare($sql_update)) {
                $stmt_update->bind_param("siii", $class_name, $capacity, $teacher_id, $class_id);
                
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = "Class updated successfully!";
                    // Redirect to view class details
                    header("Location: view_class.php?id=" . $class_id);
                    exit();
                } else {
                    $message = "Error updating class: " . htmlspecialchars($stmt_update->error);
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
    <title>Edit Class - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .error { color: red; font-size: 0.9em; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        label { display: block; margin-bottom: 5px; }
        input[type=text], input[type=number], select { 
            width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; box-sizing: border-box; 
        }
        fieldset { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
        legend { font-weight: bold; padding: 0 5px; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .field-error { border-color: red; }
        .actions { margin-top: 20px; }
        .actions a {
            display: inline-block;
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        .actions a:hover { opacity: 0.9; }
        .delete-btn {
            background-color: #dc3545;
            margin-left: 10px;
        }
        .delete-btn:hover {
            background-color: #bd2130;
        }
        .teacher-assigned-warning {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 5px 10px;
            margin-top: 5px;
            border-radius: 4px;
            font-size: 0.9em;
            display: none;
        }
        .teacher-change-warning {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 5px 10px;
            margin-top: 5px;
            border-radius: 4px;
            font-size: 0.9em;
            display: none;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teacherSelect = document.getElementById('teacher_id');
            const warningElement = document.getElementById('teacher-warning');
            const changeWarningElement = document.getElementById('teacher-change-warning');
            const originalTeacherId = "<?php echo $original_teacher_id; ?>";
            
            if (teacherSelect && warningElement) {
                teacherSelect.addEventListener('change', function() {
                    // Display standard warning about teacher assignment
                    if (this.value) {
                        warningElement.style.display = 'block';
                        
                        // If original teacher is being changed, show the change warning
                        if (originalTeacherId && this.value !== originalTeacherId && changeWarningElement) {
                            changeWarningElement.style.display = 'block';
                        } else if (changeWarningElement) {
                            changeWarningElement.style.display = 'none';
                        }
                    } else {
                        warningElement.style.display = 'none';
                        if (changeWarningElement) {
                            changeWarningElement.style.display = 'none';
                        }
                    }
                });
                
                // Initialize on page load
                if (teacherSelect.value) {
                    warningElement.style.display = 'block';
                    
                    // Show change warning if different from original teacher
                    if (originalTeacherId && teacherSelect.value !== originalTeacherId && changeWarningElement) {
                        changeWarningElement.style.display = 'block';
                    }
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Edit Class</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($class_id !== false && $class_id > 0 && isset($class)): ?>
            <form action="edit_class.php?id=<?php echo htmlspecialchars($class_id); ?>" method="post" novalidate>
                <!-- Class Information -->
                <fieldset>
                    <legend>Class Information</legend>
                    <div>
                        <label for="class_name">Class Name: <span class="required">*</span></label>
                        <select id="class_name" name="class_name" required
                                class="<?php echo isset($error_fields['class_name']) ? 'field-error' : ''; ?>">
                            <option value="">Select Class Year</option>
                            <?php foreach ($class_year_options as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" 
                                        <?php echo (isset($form_data['class_name']) && $form_data['class_name'] == $option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($error_fields['class_name'])): ?>
                            <span class="error"><?php echo $error_fields['class_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="capacity">Capacity: <span class="required">*</span></label>
                        <input type="number" id="capacity" name="capacity" required min="1" max="40"
                               value="<?php echo htmlspecialchars($form_data['capacity'] ?? ''); ?>"
                               class="<?php echo isset($error_fields['capacity']) ? 'field-error' : ''; ?>">
                        <small>Maximum number of students (1-40)</small>
                        <?php if (isset($error_fields['capacity'])): ?>
                            <span class="error"><?php echo $error_fields['capacity']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <!-- Teacher Assignment -->
                <fieldset>
                    <legend>Teacher Assignment</legend>
                    <div>
                        <label for="teacher_id">Assigned Teacher:</label>
                        <select id="teacher_id" name="teacher_id"
                                class="<?php echo isset($error_fields['teacher_id']) ? 'field-error' : ''; ?>">
                            <option value="">Select a Teacher (Optional)</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['teacher_id']; ?>" 
                                        <?php echo (isset($form_data['teacher_id']) && $form_data['teacher_id'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="teacher-warning" class="teacher-assigned-warning">
                            Warning: A teacher can only be assigned to one class.
                        </div>
                        <?php if ($original_teacher_id): ?>
                        <div id="teacher-change-warning" class="teacher-change-warning">
                            You are changing the teacher from <?php echo htmlspecialchars($original_teacher_name); ?>. 
                            Ensure that the new teacher is not already assigned to another class.
                        </div>
                        <?php endif; ?>
                        <small>Leave unselected if no teacher is assigned.</small>
                        <?php if (isset($error_fields['teacher_id'])): ?>
                            <span class="error"><?php echo $error_fields['teacher_id']; ?></span>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <div class="actions">
                    <button type="submit" name="update_class">Update Class</button>
                    <button type="submit" name="delete_class" class="delete-btn" 
                            onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.')">
                        Delete Class
                    </button>
                    <a href="view_classes.php?id=<?php echo $class_id; ?>">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p>Invalid class ID or class not found. <a href="view_classes.php">Return to Class List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
