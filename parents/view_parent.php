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
$parent_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Check if we have a valid parent ID
if ($parent_id === false || $parent_id <= 0) {
    $message = "Invalid parent ID provided.";
    $parent = null;
    $linked_pupils = [];
} else {
    // Fetch parent data
    $sql_parent = "SELECT * FROM Parents WHERE parent_id = ?";
    if ($stmt_parent = $mysqli->prepare($sql_parent)) {
        $stmt_parent->bind_param("i", $parent_id);
        
        if ($stmt_parent->execute()) {
            $result_parent = $stmt_parent->get_result();
            
            if ($result_parent->num_rows > 0) {
                $parent = $result_parent->fetch_assoc();
                
                // Fetch linked pupils
                $sql_pupils = "SELECT p.pupil_id, p.first_name, p.last_name, c.class_name 
                               FROM Pupils p 
                               LEFT JOIN Pupil_Parent pp ON p.pupil_id = pp.pupil_id 
                               LEFT JOIN Classes c ON p.class_id = c.class_id 
                               WHERE pp.parent_id = ? 
                               ORDER BY p.last_name, p.first_name";
                
                if ($stmt_pupils = $mysqli->prepare($sql_pupils)) {
                    $stmt_pupils->bind_param("i", $parent_id);
                    
                    if ($stmt_pupils->execute()) {
                        $result_pupils = $stmt_pupils->get_result();
                        $linked_pupils = [];
                        
                        while ($row = $result_pupils->fetch_assoc()) {
                            $linked_pupils[] = $row;
                        }
                    } else {
                        $message = "Error fetching linked pupils: " . htmlspecialchars($stmt_pupils->error);
                    }
                    
                    $stmt_pupils->close();
                } else {
                    $message = "Error preparing pupils statement: " . htmlspecialchars($mysqli->error);
                }
            } else {
                $message = "Parent not found with ID: " . htmlspecialchars($parent_id);
                $parent = null;
                $linked_pupils = [];
            }
        } else {
            $message = "Error executing query: " . htmlspecialchars($stmt_parent->error);
        }
        
        $stmt_parent->close();
    } else {
        $message = "Error preparing statement: " . htmlspecialchars($mysqli->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($parent) ? htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) : 'Parent Details'; ?> - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">

</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Parent Details</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($parent)): ?>
            <div class="parent-details">
                <h2><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?> (<?php echo htmlspecialchars($parent['relationship_type']); ?>)</h2>
                
                <div class="info-section">
                    <h3>Contact Information</h3>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div><?php echo htmlspecialchars($parent['phone']); ?></div>
                    </div>
                    <?php if (!empty($parent['email'])): ?>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div><?php echo htmlspecialchars($parent['email']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h3>Address</h3>
                    <div class="info-row">
                        <div class="info-label">Address Line 1:</div>
                        <div><?php echo htmlspecialchars($parent['address_line1']); ?></div>
                    </div>
                    <?php if (!empty($parent['address_line2'])): ?>
                    <div class="info-row">
                        <div class="info-label">Address Line 2:</div>
                        <div><?php echo htmlspecialchars($parent['address_line2']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">City:</div>
                        <div><?php echo htmlspecialchars($parent['city']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Postcode:</div>
                        <div><?php echo htmlspecialchars($parent['postcode']); ?></div>
                    </div>
                </div>
            </div>
            
            <h3>Linked Pupils</h3>
            <?php if (empty($linked_pupils)): ?>
                <div class="empty-list-message">
                    <p>No pupils are currently linked to this parent.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linked_pupils as $pupil): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pupil['last_name'] . ', ' . $pupil['first_name']); ?></td>
                                <td><?php echo !empty($pupil['class_name']) ? htmlspecialchars($pupil['class_name']) : 'Not Assigned'; ?></td>
                                <td>
                                    <a href="../pupil/view_pupil.php?id=<?php echo $pupil['pupil_id']; ?>" class="btn btn-primary">View Pupil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="actions">
                <a href="edit_parent.php?id=<?php echo $parent_id; ?>" class="btn btn-primary">Edit Parent</a>
                <a href="view_parents.php" class="btn btn-secondary">Back to Parents List</a>
            </div>
        <?php else: ?>
            <p>Invalid parent ID or parent not found. <a href="view_parents.php">Return to Parents List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
