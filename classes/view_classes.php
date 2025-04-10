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
$classes = []; // Array to hold classes data

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Determine sorting order
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'class_name';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validate the sort field to prevent SQL injection
$valid_sort_fields = ['class_name', 'capacity', 'teacher_name'];
if (!in_array($sort_field, $valid_sort_fields)) {
    $sort_field = 'class_name'; // Default sort field
}

// SQL query accounting for the sort field being teacher_name which is a concat of two fields
$sql = "SELECT c.class_id, c.class_name, c.capacity, c.teacher_id, 
               CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM Classes c
        LEFT JOIN Teachers t ON c.teacher_id = t.teacher_id";

// Add ORDER BY clause conditionally
if ($sort_field === 'teacher_name') {
    $sql .= " ORDER BY teacher_name " . $sort_order;
} else {
    $sql .= " ORDER BY c." . $sort_field . " " . $sort_order;
}

// Execute the query
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $result->free();
} else {
    $message = "Error: " . $mysqli->error;
}

// Define the class year options for display
$class_year_options = [
    'Reception Year', 'Year One', 'Year Two', 'Year Three', 'Year Four', 'Year Five', 'Year Six'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Classes - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container-wide">
        <h1>St Alphonsus Primary School - Classes</h1>
        <div class="main-navigation">

            <a href="../teachers/view_teachers.php" class="nav-item">Teachers</a>
            <a href="../pupil/view_pupils.php" class="nav-item">Pupils</a>
            <a href="../parents/view_parents.php" class="nav-item">Parents</a>
        </div>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <a href="add_class.php" class="add-btn">Add New Class</a>
        
        <?php if (empty($classes)): ?>
            <div class="no-records">
                <p>No classes found in the database.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>
                            <a href="?sort=class_name&order=<?php echo ($sort_field === 'class_name' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Class Name
                                <?php if ($sort_field === 'class_name'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=capacity&order=<?php echo ($sort_field === 'capacity' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Capacity
                                <?php if ($sort_field === 'capacity'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=teacher_name&order=<?php echo ($sort_field === 'teacher_name' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Teacher
                                <?php if ($sort_field === 'teacher_name'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['capacity']); ?></td>
                            <td>
                                <?php if ($class['teacher_id']): ?>
                                    <a href="view_teacher.php?id=<?php echo $class['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($class['teacher_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="no-teacher">No teacher assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view_class.php?id=<?php echo $class['class_id']; ?>" class="actions-btn">View</a>
                                <a href="edit_class.php?id=<?php echo $class['class_id']; ?>" class="actions-btn edit-btn">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p style="margin-top: 20px;"><a href="../index.php">Return to Dashboard</a></p>
    </div>
</body>
</html>
