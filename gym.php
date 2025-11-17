<?php

/*
 * Author: Maadhyam Rawal
 * University of Liverpool - COMP284 Assignment
 */

/* Ideas of implementation were taken from github: https://youtu.be/bOqTCDfc7Tk?si=aNCrzczwuDlYzb2a (Accessed March 2025)
 *                                                 https://youtu.be/JC86E5CfIrw?si=ZVf3YStMupgb-Iws (Series on YouTube)
 *                                                 https://github.com/tkrebs/ep3-bs/blob/master/config/application.php
 *                                                 https://youtu.be/gCo6JqGMi30?si=N5k5Gip0zbpDDAr1 
 *                                                 https://youtu.be/BUCiSSyIGGU?si=bU2PxyYfPF-r3paj
 *  Also took advice and suggestions from seniors studying COMP519 who were in the George Holt Lab 2 on 13 March 2025 doing Practical 11 and 12 in PHP 
 */                                                 


// Database connection settings
$servername = "studdb.csc.liv.ac.uk"; // Database host
$username = "sgmrawal"; // Database username
$password = "Hriday8748"; // Database password
$dbname = "sgmrawal"; // Database name

// Create a PDO connection to the database
// Use of advanceD PDO: https://youtu.be/kEW6f7Pilc4?si=0peE0S6rDa1y1ufF 
//                      https://youtu.be/QtCdk459NFg?si=HdOppG0t1seXSvju (Series on YouTube)
$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Enable error reporting



/**
 * Validate the name based on the required constraints.
 * The name should consist of letters (a-zA-Z), apostrophes, hyphens, and spaces.
 * It must start with a letter or apostrophe, not contain consecutive apostrophes or hyphens, 
 * and must end with a letter or apostrophe.
 * @param string of the name entered 
 * @return if the name entered is valid or not 
 * 
 * Regex adapted from: https://stackoverflow.com/questions/12796478/better-striping-method-php-regex (Accessed March 2025)
 *                     https://youtu.be/DTQDMKx4Rks?si=K3eMPrv3wUvO7K-A 
 *                     https://youtu.be/rhzKDrUiJVk?si=cDsEstsaCmvgWprJ 
 *                     https://stackoverflow.com/questions/4440626/how-can-i-validate-regex
 *                     https://www.slingacademy.com/article/php-regular-expressions-cheat-sheet/
 *                     https://regexone.com/references/php
 */

function is_valid_name($name) {
    return preg_match("/^[A-Za-z'][A-Za-z' -]*[A-Za-z]$/", $name) && !preg_match("/[ '-]{2,}/", $name);
}



 /**
 * Validate the phone number.
 * The phone number must consist of digits (0-9) and spaces, and must start with '0'.
 * It must have either 9 or 10 digits in total.
 * @param string of the phone number entered 
 * @return if the number is valid or not 
 */ 


 // SQL Commands Cheat Sheet Used: https://www.geeksforgeeks.org/sql-cheat-sheet/ 
function is_valid_phone($phone) {
    // Remove spaces before validation
    $phone = str_replace(' ', '', $phone);
    return preg_match("/^0\d{8,9}$/", $phone); // Check if the phone starts with 0 and has 9 or 10 digits
}



 /**
 * Fetch all available class names from the database.
 * Calculates the factorial of a number
 * @param conn conneection of the database
 * @return returns all the available classes
 */

function get_available_classes($conn) {
    $stmt = $conn->query("SELECT DISTINCT name FROM classes"); // Query to get distinct class names
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch the result as an associative array
    $class_names = array();
    foreach ($result as $row) {
        $class_names[] = $row['name']; // Add each class name to the array
    }
    return $class_names; // Return the list of class names
}




 /**
 * Fetch available gym sessions for a given class.
 * @param conn conneection of the database
 * @param class name of the class user is looking to book
 * @return returns all the available classes in ascending order of days and time
 */
function get_available_sessions($conn, $class) {
    // Query to fetch sessions for a specific class with available spots
    $stmt = $conn->prepare(
        "SELECT id, day, time FROM sessions 
         WHERE class_id = (SELECT id FROM classes WHERE name = ?) 
         AND free_spaces > 0 ORDER BY day"
    );
    $stmt->execute(array($class)); // Execute the query with the class name as parameter
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Return the available sessions
}




 /**
 * Book a session for the user with transaction handling to prevent race conditions.
 * @param conn conneection of the database
 * @param sessionID session ID 
 * @param name name of the user
 * @param phone phone number of the user
 * @return returns either the user has a succesfull bookng by making changes in the bookings table or if it is an unsuccessful booking
 */

 // Refernce Used: https://youtu.be/5g137gsB9Wk?si=Ok7F-3zVkqT-eS_z
 //                https://youtu.be/9_G0Jb920p4?si=UW1UZAp74ntjqhNw

function book_session($conn, $session_id, $name, $phone) {
    // Query to check the available spaces for the selected session
    $stmt = $conn->prepare("SELECT free_spaces FROM sessions WHERE id = ?");
    $stmt->execute(array($session_id)); // Execute query with session ID
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch the result

    // If no available spaces or session doesn't exist, return an error message
    if (!$row || $row['free_spaces'] <= 0) {
        return "Session is full or does not exist.";
    }

    // Insert the booking into the 'bookings' table
    $stmt = $conn->prepare("INSERT INTO bookings (session_id, name, phone) VALUES (?, ?, ?)");
    $stmt->execute(array($session_id, $name, $phone)); // Execute query with session, name, and phone

    // Decrease the available spaces for the session by 1
    $stmt = $conn->prepare("UPDATE sessions SET free_spaces = free_spaces - 1 WHERE id = ?");
    $stmt->execute(array($session_id)); // Execute query to update free spaces

    return "Booking successful!"; // Return success message
}



 /**
 * Get all bookings for a given user.
 * @param C
 * @param name name of the user
 * @param phone phone number of the user
 * @return table of all the bookings that particular user has made
 * 
 */
function get_user_bookings($conn, $name, $phone) {
    // Query to fetch all bookings for the user
    $stmt = $conn->prepare("SELECT b.id, b.name, c.name AS class, s.day, s.time FROM bookings b 
                            JOIN sessions s ON b.session_id = s.id 
                            JOIN classes c ON s.class_id = c.id 
                            WHERE b.name = ? AND b.phone = ?");
    $stmt->execute(array($name, $phone)); // Execute query with name and phone
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Return the list of user bookings
}




 /**
 * Cancel a user's booking.
 * @param @param conn conneection of the database
 * @param bookingID bookingID of the booking user has already made
 * @return returns if the booking has been cancelled by altering the bookings table or if the cancellation was unsucessfull
 */

 // Took advice and suggestions from seniors studying COMP519 who were in the George Holt Lab 2 on 13 March 2025 doing Practical 11 and 12 in PHP 
 // They suggested that deleting booking could also be seen as a way of managing booking, hence took their advice and made the following function
 // Also added a button in the final table which shows the bookings made by a particular user to help cancel their booking

function delete_booking($conn, $booking_id) {
    // Retrieve session_id from the booking
    $stmt = $conn->prepare("SELECT session_id FROM bookings WHERE id = ?");
    $stmt->execute(array($booking_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return "Booking not found.";
    }

    $session_id = $row['session_id'];

    // Delete the booking
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    if ($stmt->execute(array($booking_id))) {
        // Increase the free_spaces count for the session
        $stmt = $conn->prepare("UPDATE sessions SET free_spaces = free_spaces + 1 WHERE id = ?");
        $stmt->execute(array($session_id));

        return "Booking canceled successfully.";
    }

    return "Error during cancellation.";
}




// Initialize variables for handling form submission and errors
$name = isset($_POST['name']) ? $_POST['name'] : ''; // Get the name from the form submission
$phone = isset($_POST['phone']) ? $_POST['phone'] : ''; // Get the phone number from the form
$class = isset($_POST['class']) ? $_POST['class'] : ''; // Get the selected class
$session_id = isset($_POST['session']) ? $_POST['session'] : ''; // Get the selected session ID
$message = ''; // Message to display after booking or error
$user_bookings = array(); // List of the user's current bookings
$available_sessions = array(); // List of available sessions for the selected class
$error_message = ''; // Error message for form validation
$class_error_message = ''; // Error message for class selection

// Handle the form submission
// Multi Step Form Reference: https://youtu.be/k5NMI_DjkGQ?si=nSAW39GaKieYoWad
//                            https://youtu.be/YkYR369euc0?si=WSGa67dWOcTxUHcB (in Hindi)
//                            https://youtu.be/eUAvB_oxRHo?si=H41Krx6OrCcwhf76 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Load sessions when a class is selected
    if (isset($_POST['load_sessions']) && $class != '') {
        $available_sessions = get_available_sessions($conn, $class);
    }

    // Display error if no class is selected for booking
    if (isset($_POST['book_session']) && $class == '') {
        $class_error_message = "Please select a class.";
    }

    // Handle the booking request
    if (isset($_POST['book_session'])) {
        // Check if all fields are filled out
        if ($name != '' && $phone != '' && $class != '' && $session_id != '') {
            if (!is_valid_phone($phone)) { // Validate phone number format
                $message = "Invalid phone number."; // Show error message if invalid phone
            } else {
                $message = book_session($conn, $session_id, $name, $phone); // Attempt to book the session
                $user_bookings = get_user_bookings($conn, $name, $phone); // Fetch the user's bookings after successful booking
            }
        } else {
            $message = "All fields are required."; // Show error message if any field is missing
        }
    }

    // Handle booking cancellation
    if (isset($_POST['cancel_booking'])) {
        $booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : ''; // Get the booking ID to cancel
        if ($booking_id != '') {
            $message = delete_booking($conn, $booking_id); // Cancel the booking
            $user_bookings = get_user_bookings($conn, $name, $phone); // Fetch updated bookings
        }
    }

    // Show error message if no session is selected for booking
    if (isset($_POST['book_session']) && $session_id == '') {
        $error_message = "Please select a session.";
    }
}




// Fetch the list of available classes to show in the dropdown
$classes = get_available_classes($conn);
?>

<!-- CSS Cheat Sheet Used: https://www.geeksforgeeks.org/css-cheat-sheet-a-basic-guide-to-css/ -->
<!-- HTML Cheat Sheet Used: https://web.stanford.edu/group/csp/cs21/htmlcheatsheet.pdf  -->
<!--                        https://www.codewithharry.com/blogpost/html-cheatsheet/ -->


<!DOCTYPE html>
<html>
<head>
    <title>Gym Booking System</title>
    <h1>Welcome to Maadhyam's Gym Booking Session</h1>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            margin-top: 20px;
            color: #333;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input[type="text"], select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color:rgb(86, 208, 217);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        input[type="submit"]:hover {
            background-color: rgb(86, 208, 217);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
        }
        td form {
            margin: 0;
        }
        .message {
            padding: 10px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: rgb(54, 158, 219);
        }
        .error {
            background-color: #f8d7da;
            color:rgb(149, 7, 21);
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>


<body>
    <div class="container">
        <h1>Book a Gym Class</h1>

        <!-- Display messages if any action was taken -->
        <!-- Reference Used for xss protection: https://youtu.be/Xf4mhxiQlcM?si=0jSPo4HJgFJIyCtr -->
        <!--                                    https://youtu.be/D7KndoW1Tj8?si=cT6zOPvf57CA6QSM -->
        
        <?php if ($message != '') { echo '<p class="message success"><strong>' . $message . '</strong></p>'; } ?>
        <?php if ($error_message != '') { echo '<p class="message error"><strong>' . $error_message . '</strong></p>'; } ?>
        <?php if ($class_error_message != '') { echo '<p class="message warning"><strong>' . $class_error_message . '</strong></p>'; } ?>


        <!-- Form to enter name, phone, and select a class -->
        <form method="POST" action="">
            <label>Name:</label>
            <input type="text" name="name" value="<?php echo $name; ?>" required>
            
            <label>Phone:</label>
            <input type="text" name="phone" value="<?php echo $phone; ?>" required>
            
            <label>Class:</label>
            <select name="class" required>
                <option value="">--Select a Class--</option>
                <?php foreach ($classes as $c) {
                    echo '<option value="' . $c . '"';
                    if ($class == $c) { echo ' selected'; }
                    echo '>' . $c . '</option>';
                } ?>
            </select>
            
            <input type="submit" name="load_sessions" value="Load Sessions">
        </form>



        <!-- Display available sessions if any class is selected -->
        <!-- Hidden Input Reference: https://youtu.be/dfFiYpxh4js?si=jmnS51V3Qmm6dKi6 -->
        <!--                         https://youtu.be/HxuGQYyvOZg?si=t3yOW4pwqiEhNIVZ --> 


        <?php if (count($available_sessions) > 0) { ?>
        <form method="POST" action="">
            <input type="hidden" name="name" value="<?php echo $name; ?>">
            <input type="hidden" name="phone" value="<?php echo $phone; ?>">
            <input type="hidden" name="class" value="<?php echo $class; ?>">
            
            <label>Session:</label>
            <select name="session" required>
                <option value="">--Select a session--</option>
                <?php foreach ($available_sessions as $s) {
                    echo '<option value="' . $s['id'] . '">' . $s['day'] . ' ' . $s['time'] . '</option>';
                } ?>
            </select>
            
            <input type="submit" name="book_session" value="Book Session">
        </form>
        <?php } ?>



        <!-- Display the user's current bookings -->
        <?php if (count($user_bookings) > 0) { ?>
        <h2>Your Bookings</h2>
        <table>
            <tr>
                <th>Name</th>
                <th>Class</th>
                <th>Day</th>
                <th>Time</th>
                <th>Action</th>
            </tr>


            <?php foreach ($user_bookings as $booking) {
                echo '<tr>';
                echo '<td>' . $booking['name'] . '</td>';
                echo '<td>' . $booking['class'] . '</td>';
                echo '<td>' . $booking['day'] . '</td>';
                echo '<td>' . $booking['time'] . '</td>';
                echo '<td><form method="POST" action="">
                    <input type="hidden" name="booking_id" value="' . $booking['id'] . '">
                    <input type="hidden" name="name" value="' . $name . '">
                    <input type="hidden" name="phone" value="' . $phone . '">
                    <input type="submit" name="cancel_booking" value="Cancel">
                </form></td>';
                echo '</tr>';
            } ?>
        </table>
        
        
        <?php } ?>
    </div>
</body>
</html>
