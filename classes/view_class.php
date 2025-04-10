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
$class_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Check if we have a valid class ID
if ($class_id === false || $class_id <= 0) {
    $message = "Invalid class ID provided.";
    $class = null;
} else {
    // Fetch class data with teacher information
    $sql_class = "SELECT c.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
                 FROM Classes c
                 LEFT JOIN Teachers t ON c.teacher_id = t.teacher_id
                 WHERE c.class_id = ?";
    
    if ($stmt_class = $mysqli->prepare($sql_class)) {
        $stmt_class->bind_param("i", $class_id);
        
        if ($stmt_class->execute()) {
            $result_class = $stmt_class->get_result();
            
            if ($result_class->num_rows > 0) {
                $class = $result_class->fetch_assoc();
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

// Define the class year options for reference
$class_year_options = [
    'Reception Year', 'Year One', 'Year Two', 'Year Three', 'Year Four', 'Year Five', 'Year Six'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($class) ? htmlspecialchars($class['class_name']) : 'Class Details'; ?> - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Class Details</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($class)): ?>
            <div class="class-details">
                <h2><?php echo htmlspecialchars($class['class_name']); ?></h2>
                
                <div class="info-section">
                    <h3>Class Information</h3>
                    <div class="info-row">
                        <div class="info-label">Capacity:</div>
                        <div><?php echo htmlspecialchars($class['capacity']); ?> students</div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Teacher Information</h3>
                    <div class="info-row">
                        <div class="info-label">Teacher:</div>
                        <div>
                            <?php if ($class['teacher_id']): ?>
                                <a href="../teachers/view_teacher.php?id=<?php echo $class['teacher_id']; ?>">
                                    <?php echo htmlspecialchars($class['teacher_name']); ?>
                                </a>
                            <?php else: ?>
                                <span class="no-teacher">No teacher assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <a href="edit_class.php?id=<?php echo $class_id; ?>" class="btn btn-primary">Edit Class</a>
                <a href="view_classes.php" class="btn btn-secondary">Back to Classes List</a>
            </div>
        <?php else: ?>
            <p>Invalid class ID or class not found. <a href="view_classes.php">Return to Classes List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
