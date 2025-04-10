<?php
// Include the database connection file
require_once(__DIR__ . '/../includes/db_connect.php');

$pupils = []; // Array to hold pupil data

// --- Database Query ---
// Enhanced query to fetch pupils with class name and additional information
$sql = "SELECT p.pupil_id, p.first_name, p.last_name, p.date_of_birth, p.enrollment_date, 
        p.address_line1, p.address_line2, p.city, p.postcode, p.medical_notes,
        c.class_name
        FROM Pupils p
        LEFT JOIN Classes c ON p.class_id = c.class_id";

// Add 

$sql .= " ORDER BY p.last_name, p.first_name"; // Order results

// Prepare and execute the statement
if ($stmt = $mysqli->prepare($sql)) {

    // Execute the query
    if ($stmt->execute()) {
        // Get the result set
        $result = $stmt->get_result();

        // Fetch data into the $pupils array
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pupil_id = $row['pupil_id'];
                $pupils[$pupil_id] = $row;
                $pupils[$pupil_id]['parents'] = []; // Initialize empty parents array
            }
        }
        // Free result set
        $result->free();
        
        // Now fetch parent data for all pupils
        if (!empty($pupils)) {
            $pupil_ids = array_keys($pupils);
            $pupil_ids_str = implode(',', $pupil_ids);
            
            $parent_sql = "SELECT pp.pupil_id, p.parent_id, p.first_name, p.last_name, p.phone, p.email
                          FROM Pupil_Parent pp
                          JOIN Parents p ON pp.parent_id = p.parent_id
                          WHERE pp.pupil_id IN ($pupil_ids_str)
                          ORDER BY p.last_name, p.first_name";
            
            if ($parent_result = $mysqli->query($parent_sql)) {
                while ($parent = $parent_result->fetch_assoc()) {
                    $pupils[$parent['pupil_id']]['parents'][] = $parent;
                }
                $parent_result->free();
            }
        }
    } else {
        echo "Error executing query: " . htmlspecialchars($stmt->error);

    }
    // Close statement
    $stmt->close();
} else {
    echo "Error preparing statement: " . htmlspecialchars($mysqli->error);

}

// Close the database connection
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pupils - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container-wide">
        <h1>St Alphonsus Primary School - Pupils</h1>
        <div class="main-navigation">
            <a href="../classes/view_classes.php" class="nav-item">Classes</a>
            <a href="../teachers/view_teachers.php" class="nav-item">Teachers</a>
            <a href="../parents/view_parents.php" class="nav-item">Parents</a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo strpos($_SESSION['message'], 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <a href="add_pupil.php" class="add-btn">Add New Pupil</a>

        <!-- View Toggle Buttons -->
        <div class="view-toggle">
            <button id="table-view-btn" class="active">Table View</button>
            <button id="card-view-btn">Card View</button>
        </div>

        <?php if (!empty($pupils)): ?>
            <!-- Table View (Default) -->
            <div id="table-view">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Date of Birth</th>
                            <th>Enrollment Date</th>
                            <th>Class</th>
                            <th>Address</th>
                            <th>Parents</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pupils as $pupil): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pupil['pupil_id']); ?></td>
                                <td><?php echo htmlspecialchars($pupil['first_name'] . ' ' . $pupil['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($pupil['date_of_birth']); ?></td>
                                <td><?php echo htmlspecialchars($pupil['enrollment_date']); ?></td>
                                <td><?php echo htmlspecialchars($pupil['class_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($pupil['address_line1']); ?>
                                    <?php if (!empty($pupil['address_line2'])): ?>, <?php echo htmlspecialchars($pupil['address_line2']); ?><?php endif; ?>, 
                                    <?php echo htmlspecialchars($pupil['city']); ?>, 
                                    <?php echo htmlspecialchars($pupil['postcode']); ?>
                                </td>
                                <td>
                                    <?php if (!empty($pupil['parents'])): ?>
                                        <?php foreach ($pupil['parents'] as $index => $parent): ?>
                                            <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                            <?php echo ($index < count($pupil['parents']) - 1) ? '<br>' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No parents linked
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_pupil.php?id=<?php echo $pupil['pupil_id']; ?>" class="actions-btn">View</a>
                                    <a href="edit_pupil.php?id=<?php echo $pupil['pupil_id']; ?>" class="actions-btn edit-btn">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card View (Alternative) -->
            <div id="card-view" style="display:none;">
                <?php foreach ($pupils as $pupil): ?>
                    <div class="pupil-card">
                        <div class="pupil-header">
                            <h2><?php echo htmlspecialchars($pupil['first_name'] . ' ' . $pupil['last_name']); ?></h2>
                            <div>
                                <a href="view_pupil.php?id=<?php echo htmlspecialchars($pupil['pupil_id']); ?>" class="actions-btn">View</a>
                                <a href="edit_pupil.php?id=<?php echo htmlspecialchars($pupil['pupil_id']); ?>" class="actions-btn edit-btn">Edit</a>
                            </div>
                        </div>
                        
                        <div class="pupil-details">
                            <div class="pupil-section">
                                <div class="section-title">Basic Information</div>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($pupil['pupil_id']); ?></p>
                                <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($pupil['date_of_birth']); ?></p>
                                <p><strong>Enrollment Date:</strong> <?php echo htmlspecialchars($pupil['enrollment_date']); ?></p>
                                <p><strong>Class:</strong> <?php echo htmlspecialchars($pupil['class_name'] ?? 'N/A'); ?></p>
                            </div>
                            
                            <div class="pupil-section">
                                <div class="section-title">Address</div>
                                <p><?php echo htmlspecialchars($pupil['address_line1']); ?></p>
                                <?php if (!empty($pupil['address_line2'])): ?>
                                    <p><?php echo htmlspecialchars($pupil['address_line2']); ?></p>
                                <?php endif; ?>
                                <p><?php echo htmlspecialchars($pupil['city'] . ', ' . $pupil['postcode']); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($pupil['medical_notes'])): ?>
                            <div class="pupil-section">
                                <div class="section-title">Medical Notes</div>
                                <div class="medical-notes"><?php echo nl2br(htmlspecialchars($pupil['medical_notes'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pupil-section">
                            <div class="section-title">Parents/Guardians</div>
                            <?php if (!empty($pupil['parents'])): ?>
                                <?php foreach ($pupil['parents'] as $parent): ?>
                                    <div class="parent-info">
                                        <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong><br>
                                        <?php if (!empty($parent['phone'])): ?>
                                            Phone: <?php echo htmlspecialchars($parent['phone']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($parent['email'])): ?>
                                            Email: <?php echo htmlspecialchars($parent['email']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No parents linked to this pupil.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        
            <div class="no-records">
                <p>No pupils found in the database. <a href="add_pupil.php">Add one?</a></p>
            </div>
        <?php endif; ?>

        <script>
            // Simple JavaScript to toggle between table and card views
            document.getElementById('table-view-btn').addEventListener('click', function() {
                document.getElementById('table-view').style.display = 'block';
                document.getElementById('card-view').style.display = 'none';
                this.classList.add('active');
                document.getElementById('card-view-btn').classList.remove('active');
            });
            
            document.getElementById('card-view-btn').addEventListener('click', function() {
                document.getElementById('table-view').style.display = 'none';
                document.getElementById('card-view').style.display = 'block';
                this.classList.add('active');
                document.getElementById('table-view-btn').classList.remove('active');
            });
        </script>
        
        <p style="margin-top: 20px;"><a href="../index.php">Return to Dashboard</a></p>
    </div>
</body>
</html>