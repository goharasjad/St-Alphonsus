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
$teacher_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Check if we have a valid teacher ID
if ($teacher_id === false || $teacher_id <= 0) {
    $message = "Invalid teacher ID provided.";
    $teacher = null;
    $assigned_classes = [];
} else {
    // Fetch teacher data
    $sql_teacher = "SELECT * FROM Teachers WHERE teacher_id = ?";
    if ($stmt_teacher = $mysqli->prepare($sql_teacher)) {
        $stmt_teacher->bind_param("i", $teacher_id);
        
        if ($stmt_teacher->execute()) {
            $result_teacher = $stmt_teacher->get_result();
            
            if ($result_teacher->num_rows > 0) {
                $teacher = $result_teacher->fetch_assoc();
                
                // Fetch assigned classes
                $sql_classes = "SELECT class_id, class_name
                               FROM Classes 
                               WHERE teacher_id = ? 
                               ORDER BY class_name";
                
                if ($stmt_classes = $mysqli->prepare($sql_classes)) {
                    $stmt_classes->bind_param("i", $teacher_id);
                    
                    if ($stmt_classes->execute()) {
                        $result_classes = $stmt_classes->get_result();
                        $assigned_classes = [];
                        
                        while ($row = $result_classes->fetch_assoc()) {
                            $assigned_classes[] = $row;
                        }
                    } else {
                        $message = "Error fetching assigned classes: " . htmlspecialchars($stmt_classes->error);
                    }
                    
                    $stmt_classes->close();
                } else {
                    $message = "Error preparing classes statement: " . htmlspecialchars($mysqli->error);
                }
            } else {
                $message = "Teacher not found with ID: " . htmlspecialchars($teacher_id);
                $teacher = null;
                $assigned_classes = [];
            }
        } else {
            $message = "Error executing query: " . htmlspecialchars($stmt_teacher->error);
        }
        
        $stmt_teacher->close();
    } else {
        $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
    }
}

// Helper function to get status class
function getStatusClass($status) {
    switch ($status) {
        case 'Cleared':
            return 'status-cleared';
        case 'Pending':
            return 'status-pending';
        case 'Expired':
            return 'status-expired';
        case 'Not Required':
            return 'status-not-required';
        default:
            return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($teacher) ? htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) : 'Teacher Details'; ?> - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .teacher-details {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        h2 { margin-top: 0; color: #343a40; }
        
        .info-section { margin-bottom: 20px; }
        .info-section h3 { 
            font-size: 1.1em; 
            border-bottom: 1px solid #dee2e6; 
            padding-bottom: 5px; 
            margin-bottom: 10px; 
        }
        
        .info-row { 
            display: flex; 
            margin-bottom: 8px; 
        }
        
        .info-label { 
            font-weight: bold; 
            width: 150px; 
            flex-shrink: 0; 
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1em;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .empty-list-message {
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            margin-top: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-cleared {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-not-required {
            background-color: #e2e3e5;
            color: #383d41;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Teacher Details</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($teacher)): ?>
            <div class="teacher-details">
                <h2><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h2>
                
                <div class="info-section">
                    <h3>Contact Information</h3>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div><?php echo htmlspecialchars($teacher['email']); ?></div>
                    </div>
                    <?php if (!empty($teacher['phone'])): ?>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div><?php echo htmlspecialchars($teacher['phone']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h3>Employment Details</h3>
                    <div class="info-row">
                        <div class="info-label">Annual Salary:</div>
                        <div><?php echo '£' . number_format($teacher['annual_salary'], 2); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Background Check:</div>
                        <div>
                            <span class="status-badge <?php echo getStatusClass($teacher['background_check_status']); ?>">
                                <?php echo htmlspecialchars($teacher['background_check_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Address</h3>
                    <div class="info-row">
                        <div class="info-label">Address Line 1:</div>
                        <div><?php echo htmlspecialchars($teacher['address_line1']); ?></div>
                    </div>
                    <?php if (!empty($teacher['address_line2'])): ?>
                    <div class="info-row">
                        <div class="info-label">Address Line 2:</div>
                        <div><?php echo htmlspecialchars($teacher['address_line2']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">City:</div>
                        <div><?php echo htmlspecialchars($teacher['city']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Postcode:</div>
                        <div><?php echo htmlspecialchars($teacher['postcode']); ?></div>
                    </div>
                </div>
            </div>
            
            <h3>Assigned Classes</h3>
            <?php if (empty($assigned_classes)): ?>
                <div class="empty-list-message">
                    <p>No classes are currently assigned to this teacher.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_classes as $class): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                <td>
                                    <a href="../classes/view_class.php?id=<?php echo $class['class_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.9em;">View Class</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="actions">
                <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn btn-primary">Edit Teacher</a>
                <a href="view_teachers.php" class="btn btn-secondary">Back to Teachers List</a>
            </div>
        <?php else: ?>
            <p>Invalid teacher ID or teacher not found. <a href="view_teachers.php">Return to Teachers List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
