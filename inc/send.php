<?php

require_once "../vendor/autoload.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Validate and retrieve form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    // Check required fields
    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }

    if (empty($name)) {
        $errors[] = 'Name is required.';
    }

    if (empty($email)) {
        $errors[] = 'From is required.';
    }

    if (empty($message)) {
        $errors[] = 'Message is required.';
    }

    // Output errors if any
    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit(0);
    }

    // If a csv file was uploaded instead of email addresses
    if (isset($_FILES['email_csv']) && $_FILES['email_csv']['error'] === UPLOAD_ERR_OK){
        $file = $_FILES['email_csv'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];

        // process the file and extract emails from the csv file
        $emails = [];
        if ($file_size > 0) {
            $file_handle = fopen($file_tmp, 'r');
        }
        while (($data = fgetcsv($file_handle, 1000, ",")) !== FALSE) {
            if(empty($data))
                continue;
            
            foreach($data as $key => $email){
                if(empty($email))
                    continue;
                if(filter_var($email, FILTER_VALIDATE_EMAIL) === false){
                        continue;
                }
                else{
                    $emails[] = $email;
                }
            }
        }
        fclose($file_handle);

        // Remove duplicates and invalid emails
        $emails = array_unique($emails);
        foreach ($emails as $key => $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                unset($emails[$key]);
            }
        }

        print_r($emails);   
    }

    else{
        $emails = explode(',', $email);
    }

    // Only process 200 emails per day and save user activity to check if they have sent too many emails
    // You can adjust this base on your server configuration

    $max_emails_per_day = 200;
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $date = date('Y-m-d');

    // check if user is already saved in cookie
    if(isset($_COOKIE['user_ip']) && $_COOKIE['user_ip'] == $user_ip && $_COOKIE['user_agent'] == $user_agent){
        $user_activity = json_decode($_COOKIE['user_activity'], true);  
    }
    else{
        // store user activity in cookie
        $user_activity = [$date => count($emails)];
        setcookie('user_ip', $user_ip, time() + (86400), '/'); // set for day
        setcookie('user_agent', $user_agent, time() + (86400), '/');
        setcookie('user_activity', json_encode($user_activity), time() + (86400), '/'); 
    }

    // count number of emails in the email array
    $number_of_emails = count($emails);
    // check if user activity has any value in it yet
    if(isset($user_activity)){
        $number_of_email_sent = json_decode($_COOKIE['user_activity'], true);
        // check if user has sent more than 200 emails in the last 24 hours
        if((int)$number_of_email_sent + $number_of_emails > $max_emails_per_day){
            echo json_encode(['errors' => ['You have sent too many emails in the last 24 hours.']]);
            exit(0);
        }
    }
    else{
        // user hasn't sent any emails yet since the user activity is empty
        $user_activity = [$date => count($emails)];
        setcookie('user_ip', $user_ip, time() + (86400), '/'); 
    }
    

    // check if message contains html escaping characters
    // if (strpos($message, '&lt;') !== false || strpos($message, '&gt;') !== false) {
    //     // decode html escaping characters
    //     $message = html_entity_decode($message);
    // }

    foreach ($emails as $recipient) {
        $recipient = trim($recipient);

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode(['errors' => ['Invalid email address.']]);
            exit(0);
        }

        sendEmail($recipient, $subject, 'Mailer', $name, $message);
    }

    echo json_encode(['success' => 'Email(s) sent successfully.']);
} else {
    // Return an error message if the request method is not POST
    echo json_encode(['errors' => ['Invalid request method.']]);
}

function sendEmail($email, $subject, $from, $name, $message)
{
    $transport = new Swift_SmtpTransport('your_server.com', 587, 'tls');
    $transport->setUsername('your_username@server.com');
    $transport->setPassword('your_password!?');
    $mailer = new Swift_Mailer($transport);

    $emailMessage = new Swift_Message($subject);
    $emailMessage->setFrom(['your_username@server.com' => $name]);
    $emailMessage->setTo([$email => '']);
    $emailMessage->setBody($message, 'text/html');

    // Sleep for 5 seconds to avoid being blocked for spaming
    sleep(5);
    $result = $mailer->send($emailMessage);

    return $result;
}
?>
