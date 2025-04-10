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

// Define valid background check statuses
$background_check_statuses = ['Pending', 'Cleared', 'Expired', 'Not Required'];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Check if email already exists
    if (!empty($email) && !isset($error_fields['email'])) {
        $sql_check_email = "SELECT teacher_id FROM Teachers WHERE email = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_email)) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                $error_fields['email'] = "This email address is already in use.";
            }
            
            $stmt_check->close();
        }
    }

    // 3. If validation passes, insert into database
    if (empty($error_fields)) {
        $sql_insert = "INSERT INTO Teachers (first_name, last_name, address_line1, address_line2, city, postcode, email, phone, annual_salary, background_check_status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            // Prepare nullable fields
            $address_line2_param = !empty($address_line2) ? $address_line2 : null;
            $phone_param = !empty($phone) ? $phone : null;
            
            $stmt_insert->bind_param("ssssssssds", 
                $first_name, 
                $last_name, 
                $address_line1, 
                $address_line2_param, 
                $city, 
                $postcode, 
                $email, 
                $phone_param, 
                $annual_salary, 
                $background_check_status
            );
            
            if ($stmt_insert->execute()) {
                $teacher_id = $mysqli->insert_id;
                $_SESSION['message'] = "Teacher added successfully!";
                
                // Redirect to the new teacher's page
                header("Location: view_teacher.php?id=" . $teacher_id);
                exit();
            } else {
                $message = "Error adding teacher: " . htmlspecialchars($stmt_insert->error);
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
    <title>Add New Teacher - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Add New Teacher</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form action="add_teacher.php" method="post" novalidate>
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
                <button type="submit">Add Teacher</button>
                <a href="view_teachers.php">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
