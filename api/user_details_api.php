<?php
header('Content-Type: application/json');
include 'db.php'; // Include your database connection file

$method = $_SERVER['REQUEST_METHOD'];

// Define the base URL for uploaded profile pictures
$base_url = 'http://localhost/concept/api/uploads/';

// Handle requests using if-else
if ($method == 'POST') {
    createUserDetails($conn);
} else if ($method == 'GET') {
    getUserDetails($conn);
} else if ($method == 'PUT') {
    updateUserDetails($conn);
} else if ($method == 'DELETE') {
    deleteUserDetails($conn);
} else {
    echo json_encode(["error" => "Invalid request method."]);
}

// Function to create user details
function createUserDetails($conn)
{
    global $base_url;
    
    $userId = $_POST['user_id'] ?? null;
    $address = $_POST['address'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $nationality = $_POST['nationality'] ?? null;
    $occupation = $_POST['occupation'] ?? null;
    $bio = $_POST['bio'] ?? null;
    $file_path = '';

    if (isset($_FILES['profile_picture'])) {
        $upload_result = uploadProfilePicture($_FILES['profile_picture']);
        if (isset($upload_result['error'])) {
            echo json_encode($upload_result);
            exit;
        }
        $file_path = $upload_result['file_path'];
    }

    $sql = "INSERT INTO user_details (user_id, address, phone, date_of_birth, gender, profile_picture, nationality, occupation, bio) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("issssssss", $userId, $address, $phone, $date_of_birth, $gender, $file_path, $nationality, $occupation, $bio);
        if ($stmt->execute()) {
            echo json_encode(["message" => "User details created successfully.", "profile_picture" => $file_path]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["error" => $conn->error]);
    }
}

// Function to upload profile picture
function uploadProfilePicture($file)
{
    global $base_url;

    $upload_dir = __DIR__ . "/uploads/"; // Local folder path
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create the uploads folder if not exists
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return ["error" => "Invalid file type. Only JPG, PNG, and GIF allowed."];
    }

    if ($file['size'] > 2 * 1024 * 1024) { // Limit file size to 2MB
        return ["error" => "File size exceeds 2MB."];
    }

    $file_name = time() . "_" . basename($file['name']);
    $file_path = $upload_dir . $file_name;
    $file_url = $base_url . $file_name; // URL to access the uploaded file

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ["file_path" => $file_url];
    } else {
        return ["error" => "Failed to upload file."];
    }
}

// Function to retrieve user details
function getUserDetails($conn)
{
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(["error" => "User ID is required."]);
        exit();
    }

    $sql = "SELECT * FROM user_details WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(["error" => "User details not found."]);
    }

    $stmt->close();
}

// Function to update user details
function updateUserDetails($conn)
{
    global $base_url;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        echo json_encode(["error" => "Invalid request method. Use PUT for updates."]);
        exit();
    }

    // Parse JSON input
    $input = json_decode(file_get_contents("php://input"), true);

    $userId = $input['user_id'] ?? null;
    $address = $input['address'] ?? null;
    $phone = $input['phone'] ?? null;
    $date_of_birth = $input['date_of_birth'] ?? null;
    $gender = $input['gender'] ?? null;
    $nationality = $input['nationality'] ?? null;
    $occupation = $input['occupation'] ?? null;
    $bio = $input['bio'] ?? null;
    $profile_picture = null;

    if (!$userId) {
        echo json_encode(["error" => "User ID is required."]);
        exit();
    }

    // Fetch current user details
    $query = "SELECT * FROM user_details WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo json_encode(["error" => "User not found."]);
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Handle Profile Picture Upload (if sent separately via FormData)
    if (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $upload_result = uploadProfilePicture($_FILES['profile_picture']);
        if (isset($upload_result['error'])) {
            echo json_encode($upload_result);
            exit();
        }
        $profile_picture = $upload_result['file_path'];
    } else {
        $profile_picture = $user['profile_picture']; // Keep old picture
    }

    // Update user details in the database
    $sql = "UPDATE user_details SET 
                address = COALESCE(?, address), 
                phone = COALESCE(?, phone), 
                date_of_birth = COALESCE(?, date_of_birth), 
                gender = COALESCE(?, gender), 
                nationality = COALESCE(?, nationality), 
                occupation = COALESCE(?, occupation), 
                bio = COALESCE(?, bio), 
                profile_picture = COALESCE(?, profile_picture), 
                updated_at = NOW() 
            WHERE user_id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ssssssssi", 
            $address, 
            $phone, 
            $date_of_birth, 
            $gender, 
            $nationality, 
            $occupation, 
            $bio, 
            $profile_picture, 
            $userId
        );

        if ($stmt->execute()) {
            echo json_encode([
                "message" => "User details updated successfully.", 
                "profile_picture" => $profile_picture
            ]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(["error" => $conn->error]);
    }
}



// Function to delete user details
function deleteUserDetails($conn)
{
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = $data['user_id'] ?? null;

    if (!$userId) {
        echo json_encode(["error" => "User ID is required."]);
        exit();
    }

    $sql = "DELETE FROM user_details WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        echo json_encode(["message" => "User details deleted successfully."]);
    } else {
        echo json_encode(["error" => $stmt->error]);
    }

    $stmt->close();
}

$conn->close();
?>
