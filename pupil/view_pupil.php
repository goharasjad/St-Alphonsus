<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection file
require_once(__DIR__ . '/../includes/db_connect.php');

// Get pupil ID from URL
$pupil_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$pupil = null;
$parents = [];
$error_message = '';

// Validate pupil ID
if ($pupil_id === false || $pupil_id <= 0) {
    $error_message = "Invalid pupil ID provided.";
} else {
    // Fetch pupil details with class information
    $sql = "SELECT p.*, c.class_name 
            FROM Pupils p 
            LEFT JOIN Classes c ON p.class_id = c.class_id
            WHERE p.pupil_id = ?";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $pupil_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $pupil = $result->fetch_assoc();
                
                // Fetch associated parents
                $parent_sql = "SELECT p.* 
                              FROM Parents p
                              JOIN Pupil_Parent pp ON p.parent_id = pp.parent_id
                              WHERE pp.pupil_id = ?
                              ORDER BY p.last_name, p.first_name";
                              
                if ($parent_stmt = $mysqli->prepare($parent_sql)) {
                    $parent_stmt->bind_param("i", $pupil_id);
                    
                    if ($parent_stmt->execute()) {
                        $parent_result = $parent_stmt->get_result();
                        
                        while ($parent = $parent_result->fetch_assoc()) {
                            $parents[] = $parent;
                        }
                    } else {
                        $error_message = "Error fetching parent information: " . $parent_stmt->error;
                    }
                    
                    $parent_stmt->close();
                }
            } else {
                $error_message = "Pupil not found.";
            }
        } else {
            $error_message = "Error executing query: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = "Error preparing statement: " . $mysqli->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pupil ? htmlspecialchars($pupil['first_name'] . ' ' . $pupil['last_name']) : 'Pupil Details'; ?> - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>St Alphonsus Primary School - Pupil Details</h1>
        
        <?php if ($error_message): ?>
            <div class="message error-msg">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($pupil): ?>
            <div class="class-details">
                <h2><?php echo htmlspecialchars($pupil['first_name'] . ' ' . $pupil['last_name']); ?></h2>
                
                <div class="info-section">
                    <h3>Basic Information</h3>
                    <div class="info-row">
                        <div class="info-label">Date of Birth:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['date_of_birth']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Enrollment Date:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['enrollment_date']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Class:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['class_name'] ?? 'Not Assigned'); ?></div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Address</h3>
                    <div class="info-row">
                        <div class="info-label">Address Line 1:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['address_line1'] ?? 'N/A'); ?></div>
                    </div>
                    <?php if (!empty($pupil['address_line2'])): ?>
                    <div class="info-row">
                        <div class="info-label">Address Line 2:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['address_line2']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">City:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['city'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Postcode:</div>
                        <div class="info-value"><?php echo htmlspecialchars($pupil['postcode'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($pupil['medical_notes'])): ?>
                <div class="info-section">
                    <h3>Medical Notes</h3>
                    <div class="medical-notes">
                        <?php echo nl2br(htmlspecialchars($pupil['medical_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-section">
                    <h3>Parents/Guardians</h3>
                    <?php if (!empty($parents)): ?>
                        <?php foreach ($parents as $parent): ?>
                            <div class="parent-card">
                                <div class="info-row">
                                    <div class="info-label">Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></div>
                                </div>
                                <?php if (!empty($parent['phone'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($parent['phone']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($parent['email'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($parent['email']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($parent['address_line1'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Address:</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($parent['address_line1']); ?>
                                        <?php if (!empty($parent['address_line2'])): ?>, <?php echo htmlspecialchars($parent['address_line2']); ?><?php endif; ?>, 
                                        <?php echo htmlspecialchars($parent['city'] . ', ' . $parent['postcode']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No parents or guardians linked to this pupil.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="actions">
                <a href="edit_pupil.php?id=<?php echo htmlspecialchars($pupil['pupil_id']); ?>" class="btn btn-primary">Edit Pupil</a>
                <a href="view_pupils.php" class="btn btn-secondary">Back to Pupils List</a>
            </div>
        <?php else: ?>
            <p>Invalid pupil ID or pupil not found. <a href="view_pupils.php">Return to Pupils List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
