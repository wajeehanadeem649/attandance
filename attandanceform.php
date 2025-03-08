<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Fetch rooms from the database
$roomsData = [];
$sql = "SELECT room_id, room_name, latitude, longitude FROM rooms";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $roomsData[] = $row;
    }
}

// Function to calculate distance (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth's radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// Handle attendance submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'], $_POST['latitude'], $_POST['longitude'], $_POST['room_id'], $_POST['selfie'])) {
    $name = trim($_POST['name']);
    $studentLat = floatval($_POST['latitude']);
    $studentLon = floatval($_POST['longitude']);
    $roomId = intval($_POST['room_id']);
    $selfieData = $_POST['selfie']; // Base64 data
    $date = date("Y-m-d");

    // Find the selected room
    $selectedRoom = null;
    foreach ($roomsData as $room) {
        if ($room['room_id'] == $roomId) {
            $selectedRoom = $room;
            break;
        }
    }

    $threshold = 50; // Allowed distance in meters

    if ($selectedRoom) {
        // Calculate the distance between student and room
        $distance = calculateDistance($studentLat, $studentLon, $selectedRoom['latitude'], $selectedRoom['longitude']);
        $status = ($distance <= $threshold) ? 'Present' : 'Absent'; // ✅ Fix status setting

        // Decode and save the selfie
        if (!empty($selfieData) && strpos($selfieData, ',') !== false) {
            $selfieParts = explode(',', $selfieData);
            if (isset($selfieParts[1])) {
                $decodedImage = base64_decode($selfieParts[1]);

                if ($decodedImage !== false) {
                    $uploadDir = "selfies/";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $selfieFilename = $uploadDir . "selfie_" . time() . ".png";

                    if (file_put_contents($selfieFilename, $decodedImage) === false) {
                        echo "<script>alert('Error saving selfie image.');</script>";
                        exit;
                    }
                } else {
                    echo "<script>alert('Invalid selfie data.');</script>";
                    exit;
                }
            } else {
                echo "<script>alert('Invalid selfie format.');</script>";
                exit;
            }
        } else {
            echo "<script>alert('No selfie data received.');</script>";
            exit;
        }

        // Check if attendance is already marked
        $checkSql = "SELECT * FROM attendance_records WHERE student_name = ? AND attendance_date = ? AND room_id = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("ssi", $name, $date, $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // ✅ Corrected variable `$selfieFilename`
            $insertSql = "INSERT INTO attendance_records (student_name, attendance_date, attendance_status, room_id, selfie_path) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("sssis", $name, $date, $status, $roomId, $selfieFilename);
            
            if ($stmt->execute()) {
                echo "<script>alert('Attendance marked as $status.');</script>";
            } else {
                echo "<script>alert('Error marking attendance.');</script>";
            }
        } else {
            echo "<script>alert('Attendance already marked for this room today.');</script>";
        }
    }        
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Attendance System</title>
        <!--bootstrap css-->
        <link rel="stylesheet" href="css/bootstrap.min.css">

<!-- fontawesome-->
    <link rel="stylesheet" href="fontjs/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">


     <!--google font-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<!--custom css-->
     <!-- Custom CSS -->
     <style>
        /* Global styling */
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(to right, #5F2C82, #49A09D);
            color: #333;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Header Styling */
        h1 {
            color: #fff;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.4);
            font-size: 50px;
            margin-top: 50px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 5px;
        }

        h3 {
            color: #fff;
            font-weight: 600;
            margin-top: -10px;
            font-size: 26px;
            text-align: center;
            text-transform: capitalize;
        }

        /* Main Container */
        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* Main Card */
        .card {
            background: rgba(255, 255, 255, 0.85);
            width: 100%;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeIn 1s ease-in-out;
        }

        /* Form Styling */
        form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 500px;
    margin: auto;
    padding: 20px;
    border: 2px solid #ddd; /* Light border around the form */
    border-radius: 15px; /* Rounded corners */
    background-color: #fff; /* White background */
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); /* Soft shadow around the form */
    transition: all 0.3s ease;
}

/* Add a subtle hover effect for the form */
form:hover {
    transform: scale(1.02); /* Slightly increase the size */
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2); /* Enhance shadow on hover */
}


        input, select {
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            border-color: #ff416c;
            box-shadow: 0 0 10px rgba(255, 65, 108, 0.5);
            outline: none;
        }

        button {
            padding: 12px 30px;
            font-size: 16px;
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            background: linear-gradient(45deg, #ff4b2b, #ff416c);
            transform: scale(1.05);
        }

        /* Media Container for Video and Selfie Preview */
        .media-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }

        #video {
            width: 200px;
            height: 200px;
            object-fit: cover;

            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        #video:hover {
            transform: scale(1.05);
        }

        #selfiePreview {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        #selfiePreview:hover {
            transform: scale(1.05);
        }

        /* Button Container */
        .button-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 20px;
        }

        /* Export Button Styling */
        .export-button-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
        }

        .export-button-container button {
            background: #28a745;
            padding: 12px 30px;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .export-button-container button:hover {
            background: #218838;
        }

        /* Add FadeIn animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .card {
                width: 80%;
            }
            h1 {
                font-size: 36px;
            }
            h3 {
                font-size: 22px;
            }
            input, select, button {
                width: 90%;
            }
        }

    </style>
</head>
<body>
    <h1>Smart Attendance System</h1>

    <h3>Mark Your Attendance</h3>
    <form method="POST" onsubmit="return getLocationAndSubmit();">
        <input type="text" name="name" placeholder="Enter your name" required>
        <select name="room_id" required>
            <option value="">Select Room</option>
            <?php foreach ($roomsData as $room): ?>
                <option value="<?php echo $room['room_id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <div class="media-container">
    <video id="video" autoplay></video>
    <img id="selfiePreview">
</div>
<canvas id="canvas" style="display:none;"></canvas>
<input type="hidden" name="selfie" id="selfie" required>
<div class="button-container">
    <button type="button" onclick="captureSelfie()">Capture Selfie</button>
    <button type="submi">Mark Attendance</button>

</div>

    </form>
 
  


    <script>
 const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const selfieInput = document.getElementById('selfie');
const selfiePreview = document.getElementById('selfiePreview');
const markAttendanceButton = document.querySelector("button[type='submit']");

let cameraAllowed = false;

// Access the webcam
navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
        video.srcObject = stream;
        cameraAllowed = true;
    })
    .catch(error => {
        alert('Error accessing webcam: ' + error.message + '\nPlease allow camera access to mark attendance.');
        cameraAllowed = false;
        markAttendanceButton.disabled = true; // Disable submission if no camera
    });

function captureSelfie() {
    if (!cameraAllowed) {
        alert("Camera access is required to capture a selfie.");
        return;
    }

    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert the image to Base64
    const selfieData = canvas.toDataURL('image/png');
    selfieInput.value = selfieData;  // Store in hidden input
    selfiePreview.src = selfieData;  // Show preview
}

function getLocationAndSubmit() {
    if (!cameraAllowed) {
        alert("Please allow camera access to mark attendance.");
        return false;
    }

    if (!selfieInput.value) {
        alert("Please capture a selfie before submitting.");
        return false;
    }

    navigator.geolocation.getCurrentPosition(position => {
        document.getElementById('latitude').value = position.coords.latitude;
        document.getElementById('longitude').value = position.coords.longitude;
        document.forms[0].submit();
    }, error => {
        alert("Location access is required to mark attendance.");
    });

    return false;
}
    
</script>

 
</body>
</html>
