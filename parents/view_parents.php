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
$parents = []; // Array to hold parents data

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Determine sorting order
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'last_name';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validate the sort field to prevent SQL injection
$valid_sort_fields = ['last_name', 'first_name', 'city', 'relationship_type'];
if (!in_array($sort_field, $valid_sort_fields)) {
    $sort_field = 'last_name'; // Default sort field
}

// Modify the SQL query to get linked pupils count
$sql = "SELECT p.parent_id, p.first_name, p.last_name, p.city, p.postcode, p.relationship_type, p.phone,
        (SELECT COUNT(*) FROM Pupil_Parent pp WHERE pp.parent_id = p.parent_id) AS linked_pupils_count
        FROM Parents p 
        ORDER BY $sort_field $sort_order";

// Execute the query
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $parent_id = $row['parent_id'];
        
        // Get linked pupils (up to 3 to keep the list manageable)
        $linked_pupils = [];
        $sql_pupils = "SELECT pup.first_name, pup.last_name 
                      FROM Pupils pup 
                      JOIN Pupil_Parent pp ON pup.pupil_id = pp.pupil_id 
                      WHERE pp.parent_id = $parent_id 
                      ORDER BY pup.last_name, pup.first_name 
                      LIMIT 3";
        
        if ($pupils_result = $mysqli->query($sql_pupils)) {
            while ($pupil = $pupils_result->fetch_assoc()) {
                $linked_pupils[] = $pupil['last_name'] . ', ' . $pupil['first_name'];
            }
            $pupils_result->free();
        }
        
        // Add pupils to parent data
        $row['linked_pupils'] = $linked_pupils;
        $row['linked_pupils_count'] = intval($row['linked_pupils_count']);
        
        $parents[] = $row;
    }
    $result->free();
} else {
    $message = "Error: " . $mysqli->error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Parents - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">

</head>
<body>
    <div class="container-wide">
        <h1>St Alphonsus Primary School - Parents</h1>
        <div class="main-navigation">
            <a href="../classes/view_classes.php" class="nav-item">Classes</a>
            <a href="../teachers/view_teachers.php" class="nav-item">Teachers</a>
            <a href="../pupil/view_pupils.php" class="nav-item">Pupils</a>
    
        </div>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <a href="add_parent.php" class="add-btn">Add New Parent</a>
        
        <?php if (empty($parents)): ?>
            <div class="no-records">
                <p>No parents found in the database.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>
                            <a href="?sort=last_name&order=<?php echo ($sort_field === 'last_name' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Last Name
                                <?php if ($sort_field === 'last_name'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=first_name&order=<?php echo ($sort_field === 'first_name' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                First Name
                                <?php if ($sort_field === 'first_name'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=relationship_type&order=<?php echo ($sort_field === 'relationship_type' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Relationship
                                <?php if ($sort_field === 'relationship_type'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Phone</th>
                        <th>Linked Pupils</th>
                        <th>
                            <a href="?sort=city&order=<?php echo ($sort_field === 'city' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                City
                                <?php if ($sort_field === 'city'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Postcode</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parents as $parent): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($parent['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($parent['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($parent['relationship_type']); ?></td>
                            <td><?php echo htmlspecialchars($parent['phone']); ?></td>
                            <td>
                                <?php if ($parent['linked_pupils_count'] > 0): ?>
                                    <ul class="pupil-list">
                                        <?php foreach ($parent['linked_pupils'] as $pupil_name): ?>
                                            <li><?php echo htmlspecialchars($pupil_name); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($parent['linked_pupils_count'] > count($parent['linked_pupils'])): ?>
                                        <div class="pupils-count">
                                            and <?php echo ($parent['linked_pupils_count'] - count($parent['linked_pupils'])); ?> more...
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($parent['city']); ?></td>
                            <td><?php echo htmlspecialchars($parent['postcode']); ?></td>
                            <td>
                                <a href="view_parent.php?id=<?php echo $parent['parent_id']; ?>" class="actions-btn">View</a>
                                <a href="edit_parent.php?id=<?php echo $parent['parent_id']; ?>" class="actions-btn edit-btn">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p><a href="../index.php">Return to Dashboard</a></p>
    </div>
</body>
</html>
