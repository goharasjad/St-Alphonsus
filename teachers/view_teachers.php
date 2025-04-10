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
$teachers = []; // Array to hold teachers data

// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear message after displaying
}

// Determine sorting order
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'last_name';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validate the sort field to prevent SQL injection
$valid_sort_fields = ['last_name', 'first_name', 'city', 'email', 'background_check_status', 'annual_salary'];
if (!in_array($sort_field, $valid_sort_fields)) {
    $sort_field = 'last_name'; // Default sort field
}

// First, get all teachers
$sql = "SELECT teacher_id, first_name, last_name, city, email, phone, annual_salary, background_check_status
       FROM Teachers t
       ORDER BY $sort_field $sort_order";

// Execute the query
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        // Initialize an empty array for classes
        $row['classes'] = [];
        $teachers[$row['teacher_id']] = $row;
    }
    $result->free();
} else {
    $message = "Error: " . $mysqli->error;
}

// Then, get all classes for these teachers
if (!empty($teachers)) {
    $teacher_ids = array_keys($teachers);
    $teacher_ids_str = implode(',', $teacher_ids);
    
    $classes_sql = "SELECT c.teacher_id, c.class_name 
                   FROM Classes c 
                   WHERE c.teacher_id IN ($teacher_ids_str)
                   ORDER BY c.class_name";
    
    if ($classes_result = $mysqli->query($classes_sql)) {
        while ($class_row = $classes_result->fetch_assoc()) {
            $teacher_id = $class_row['teacher_id'];
            $teachers[$teacher_id]['classes'][] = $class_row['class_name'];
        }
        $classes_result->free();
    } else {
        $message = "Error loading classes: " . $mysqli->error;
    }
    
    // Convert the associative array back to indexed array for the view
    $teachers = array_values($teachers);
}

// Helper function to format salary
function formatSalary($salary) {
    return '£' . number_format($salary, 2);
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
    <title>View All Teachers - St Alphonsus</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container-wide">
        <h1>St Alphonsus Primary School - Teachers</h1>
         <div class="main-navigation">
            <a href="../classes/view_classes.php" class="nav-item">Classes</a>

            <a href="../pupil/view_pupils.php" class="nav-item">Pupils</a>
            <a href="../parents/view_parents.php" class="nav-item">Parents</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error-msg'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <a href="add_teacher.php" class="add-btn">Add New Teacher</a>
        
        <?php if (empty($teachers)): ?>
            <div class="no-records">
                <p>No teachers found in the database.</p>
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
                            <a href="?sort=email&order=<?php echo ($sort_field === 'email' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Email
                                <?php if ($sort_field === 'email'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Phone</th>
                        <th>
                            <a href="?sort=annual_salary&order=<?php echo ($sort_field === 'annual_salary' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Salary
                                <?php if ($sort_field === 'annual_salary'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=background_check_status&order=<?php echo ($sort_field === 'background_check_status' && $sort_order === 'ASC') ? 'desc' : 'asc'; ?>">
                                Background Check
                                <?php if ($sort_field === 'background_check_status'): ?>
                                    <span class="sort-icon <?php echo $sort_order === 'ASC' ? 'sort-asc' : ''; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Classes</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo $teacher['phone'] ? htmlspecialchars($teacher['phone']) : '-'; ?></td>
                            <td><?php echo formatSalary($teacher['annual_salary']); ?></td>
                            <td>
                                <span class="status-badge <?php echo getStatusClass($teacher['background_check_status']); ?>">
                                    <?php echo htmlspecialchars($teacher['background_check_status']); ?>
                                </span>
                            </td>
                            <td class="class-names">
                                <?php if (empty($teacher['classes'])): ?>
                                    <span class="no-classes">No classes</span>
                                <?php else: ?>
                                    <?php foreach ($teacher['classes'] as $class): ?>
                                        <span class="class-badge"><?php echo htmlspecialchars($class); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="actions-btn">View</a>
                                <a href="edit_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="actions-btn edit-btn">Edit</a>
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
