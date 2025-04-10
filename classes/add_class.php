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

$message = ''; // Variable to store success/error messages
$error_fields = []; // Array to track fields with errors
$form_data = $_POST; // Store submitted data for repopulating the form

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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        
        // Check if teacher is already assigned to another class
        if (!isset($error_fields['teacher_id'])) {
            $sql_check_teacher_assignment = "SELECT class_id FROM Classes WHERE teacher_id = ?";
            if ($stmt_check = $mysqli->prepare($sql_check_teacher_assignment)) {
                $stmt_check->bind_param("i", $teacher_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows > 0) {
                    $error_fields['teacher_id'] = "This teacher is already assigned to another class. One teacher can only be assigned to one class.";
                }
                
                $stmt_check->close();
            }
        }
    }

    // Check if class name already exists
    if (!empty($class_name) && !isset($error_fields['class_name'])) {
        $sql_check_name = "SELECT class_id FROM Classes WHERE class_name = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_name)) {
            $stmt_check->bind_param("s", $class_name);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                $error_fields['class_name'] = "This class name is already in use.";
            }
            
            $stmt_check->close();
        }
    }

    // 3. If validation passes, insert into database
    if (empty($error_fields)) {
        $sql_insert = "INSERT INTO Classes (class_name, capacity, teacher_id) VALUES (?, ?, ?)";
        
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            $stmt_insert->bind_param("sis", $class_name, $capacity, $teacher_id);
            
            if ($stmt_insert->execute()) {
                $class_id = $mysqli->insert_id;
                $_SESSION['message'] = "Class added successfully!";
                
                // Redirect to the new class's page
                header("Location: view_class.php?id=" . $class_id);
                exit();
            } else {
                $message = "Error adding class: " . htmlspecialchars($stmt_insert->error);
            }
            
            $stmt_insert->close();
        } else {
            $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
        }
    } else {
        $message = "Please correct the errors below.";
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
    <title>Add New Class - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teacherSelect = document.getElementById('teacher_id');
            const warningElement = document.getElementById('teacher-warning');
            
            if (teacherSelect && warningElement) {
                teacherSelect.addEventListener('change', function() {
                    // Display a warning about teacher assignment when a teacher is selected
                    if (this.value) {
                        warningElement.style.display = 'block';
                    } else {
                        warningElement.style.display = 'none';
                    }
                });
                
                // Initialize on page load
                if (teacherSelect.value) {
                    warningElement.style.display = 'block';
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Add New Class</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form action="add_class.php" method="post" novalidate>
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
                    <small>Leave unselected if no teacher is assigned yet.</small>
                    <?php if (isset($error_fields['teacher_id'])): ?>
                        <span class="error"><?php echo $error_fields['teacher_id']; ?></span>
                    <?php endif; ?>
                </div>
            </fieldset>
            
            <div class="actions">
                <button type="submit">Add Class</button>
                <a href="view_classes.php">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
