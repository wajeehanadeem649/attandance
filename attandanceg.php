<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'PhpSpreadsheet-master/src/PhpSpreadsheet/Spreadsheet.php'; // Adjust path as necessary

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Database connection
$servername = "localhost";
$username = "root";
$password = ""; // Change if you have a MySQL password
$dbname = "osms";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch attendance records with optional filters
$attendanceData = [];
$whereClauses = [];
$filterParams = [];

if (isset($_POST['room_name']) && $_POST['room_name'] != '') {
    $roomName = $_POST['room_name'];
    $whereClauses[] = "rooms.room_name LIKE '%$roomName%'";
    $filterParams['room_name'] = $roomName;
}

if (isset($_POST['attendance_status']) && $_POST['attendance_status'] != '') {
    $attendanceStatus = $_POST['attendance_status'];
    $whereClauses[] = "attendance_records.attendance_status LIKE '%$attendanceStatus%'";
    $filterParams['attendance_status'] = $attendanceStatus;
}

if (isset($_POST['start_date']) && $_POST['start_date'] != '') {
    $startDate = $_POST['start_date'];
    $whereClauses[] = "attendance_records.attendance_date >= '$startDate'";
    $filterParams['start_date'] = $startDate;
}

if (isset($_POST['end_date']) && $_POST['end_date'] != '') {
    $endDate = $_POST['end_date'];
    $whereClauses[] = "attendance_records.attendance_date <= '$endDate'";
    $filterParams['end_date'] = $endDate;
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

$sql = "SELECT rooms.room_name, attendance_records.student_name, attendance_records.attendance_date, attendance_records.attendance_status, attendance_records.selfie_path 
        FROM attendance_records 
        INNER JOIN rooms ON attendance_records.room_id = rooms.room_id 
        $whereSQL
        ORDER BY rooms.room_name, attendance_records.attendance_date DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $attendanceData[$row['room_name']][] = $row;
    }
} else {
    $attendanceData = []; // Set empty if no records found
}

$conn->close();

// Handle the export functionality
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_records.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write filters applied at the top of the CSV
    fputcsv($output, ['Filtered Records Export']);
    if (isset($filterParams['room_name'])) {
        fputcsv($output, ['Room Name Filter: ' . $filterParams['room_name']]);
    }
    if (isset($filterParams['attendance_status'])) {
        fputcsv($output, ['Attendance Status Filter: ' . $filterParams['attendance_status']]);
    }
    if (isset($filterParams['start_date'])) {
        fputcsv($output, ['Start Date Filter: ' . $filterParams['start_date']]);
    }
    if (isset($filterParams['end_date'])) {
        fputcsv($output, ['End Date Filter: ' . $filterParams['end_date']]);
    }
    fputcsv($output, []); // Empty row for separation

    // Write the records for each room in separate sections
    foreach ($attendanceData as $roomName => $records) {
        // Write the room header
        fputcsv($output, [strtoupper($roomName)]); // Room name in uppercase
        fputcsv($output, []); // Empty row for separation

        // Write the column headers
        fputcsv($output, ['Student Name', 'Attendance Date', 'Status', 'Selfie Path']);

        // Write records for the room
        foreach ($records as $attendance) {
            fputcsv($output, [
                $attendance['student_name'],
                $attendance['attendance_date'],
                $attendance['attendance_status'],
                $attendance['selfie_path'] ? $attendance['selfie_path'] : 'No Image'
            ]);
        }

        // Add an empty row between rooms
        fputcsv($output, []); // Empty row for separation
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Records</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        /* Custom Styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 50px;
        }

        h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 20px;
        }

        h3 {
            font-size: 24px;
            color: #007bff;
            margin-top: 20px;
        }

        .form-control {
            border-radius: 4px;
            border: 1px solid #ccc;
            padding: 10px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 10px 15px;
            font-size: 16px;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        table th {
            background-color: #f1f1f1;
            color: #333;
        }

        table td {
            background-color: #fafafa;
        }

        table img {
            border-radius: 50%;
        }

        .table-bordered {
            border: 2px solid #007bff;
        }

        .table th, .table td {
            vertical-align: middle;
        }

        .filter-form {
            margin-bottom: 30px;
        }

        .filter-form .col-md-3, .filter-form .col-md-2 {
            margin-bottom: 10px;
        }

        .filter-form .btn-primary {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Attendance Records</h1>

        <!-- Filter Form -->
        <form method="post" action="" class="mb-3">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="room_name" class="form-control" placeholder="Room Name" value="<?php echo isset($filterParams['room_name']) ? htmlspecialchars($filterParams['room_name']) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="attendance_status" class="form-control" placeholder="Status" value="<?php echo isset($filterParams['attendance_status']) ? htmlspecialchars($filterParams['attendance_status']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control" value="<?php echo isset($filterParams['start_date']) ? htmlspecialchars($filterParams['start_date']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control" value="<?php echo isset($filterParams['end_date']) ? htmlspecialchars($filterParams['end_date']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>

        <!-- Export Button -->
        <form method="post" action="">
            <button type="submit" name="export_csv" class="btn btn-primary mb-3">Download CSV</button>
        </form>

        <!-- Displaying Attendance Table -->
        <?php if (empty($attendanceData)): ?>
            <p>No records found</p>
        <?php else: ?>
            <?php foreach ($attendanceData as $roomName => $records): ?>
                <h3>Room: <?php echo htmlspecialchars($roomName); ?></h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $attendance): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attendance['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($attendance['attendance_date']); ?></td>
                                <td><?php echo htmlspecialchars($attendance['attendance_status']); ?></td>
                                <td>
                                    <?php if (!empty($attendance['selfie_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($attendance['selfie_path']); ?>" width="50" height="50">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
