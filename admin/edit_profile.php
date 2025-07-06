<?php
include '../db_connect.php'; // Corrected path
session_start();

if (!isset($_GET['id'])) {
    echo "No user ID specified.";
    exit();
}

$id = intval($_GET['id']); // Safe casting

// Fetch user data
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) !== 1) {
    echo "User not found.";
    exit();
}

$user = mysqli_fetch_assoc($result);

// Handle update
if (isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $town = mysqli_real_escape_string($conn, $_POST['town']);
    $region = mysqli_real_escape_string($conn, $_POST['region']);
    $postcode = mysqli_real_escape_string($conn, $_POST['postcode']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);

    $updateSql = "UPDATE users SET 
                    name = '$name', 
                    email = '$email', 
                    phone = '$phone',
                    phone = '$age',
                    address = '$address',
                    town = '$town',
                    region = '$region',
                    postcode = '$postcode',
                    country = '$country'
                  WHERE id = $id";

    if (mysqli_query($conn, $updateSql)) {
        echo "<script>alert('Profile updated successfully!'); window.location.href='parent_dashboard.php';</script>";
    } else {
        echo "Error updating profile: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile -  Virtual Study Group Platform</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        form {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            box-sizing: border-box;
        }
        button {
            background-color: #1abc9c;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
            border-radius: 5px;
        }
        button:hover {
            background-color: #16a085;
        }
    </style>
</head>
<body>

<h2 style="text-align: center;">Edit Profile</h2>

<form method="POST">
    <label>Name:</label>
    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

    <label>Email:</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

    <label>Phone:</label>
    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>

    <label>Age:</label>
    <input type="number" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" required>

    <label>Address:</label>
    <textarea name="address"><?php echo htmlspecialchars($user['address']); ?></textarea>

    <label>Town:</label>
    <input type="text" name="town" value="<?php echo htmlspecialchars($user['town']); ?>">

    <label>Region:</label>
    <input type="text" name="region" value="<?php echo htmlspecialchars($user['region']); ?>">

    <label>Postcode:</label>
    <input type="text" name="postcode" value="<?php echo htmlspecialchars($user['postcode']); ?>">

    <label>Country:</label>
    <input type="text" name="country" value="<?php echo htmlspecialchars($user['country']); ?>">

    <button type="submit" name="update">Update Profile</button>
</form>

</body>
</html>

<?php mysqli_close($conn); ?>
