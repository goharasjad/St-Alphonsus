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

// Define valid relationship types
$relationship_types = ['Mother', 'Father', 'Guardian'];

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

    // Check if email already exists
    if (!empty($email) && !isset($error_fields['email'])) {
        $sql_check_email = "SELECT parent_id FROM Parents WHERE email = ?";
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
        $sql_insert = "INSERT INTO Parents (first_name, last_name, address_line1, address_line2, city, postcode, email, phone, relationship_type) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            // If email is empty, pass NULL to database
            $email_param = !empty($email) ? $email : null;
            $address_line2_param = !empty($address_line2) ? $address_line2 : null;
            
            $stmt_insert->bind_param("sssssssss", 
                $first_name, 
                $last_name, 
                $address_line1, 
                $address_line2_param, 
                $city, 
                $postcode, 
                $email_param, 
                $phone, 
                $relationship_type
            );
            
            if ($stmt_insert->execute()) {
                $parent_id = $mysqli->insert_id;
                $_SESSION['message'] = "Parent added successfully!";
                
                // Redirect to the new parent's page
                header("Location: view_parent.php?id=" . $parent_id);
                exit();
            } else {
                $message = "Error adding parent: " . htmlspecialchars($stmt_insert->error);
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
    <title>Add New Parent - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">

</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Add New Parent</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form action="add_parent.php" method="post" novalidate>
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
                <button type="submit">Add Parent</button>
                <a href="view_parents.php">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
