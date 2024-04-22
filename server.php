<?php
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kolkata');

$conn = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PSW'], $_ENV['DB_NAME']);

function sendMail($to, $subject, $Mailbody, $attchments = [])
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['ML_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['ML_USER'];
        $mail->Password = $_ENV['ML_PSW'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $_ENV['ML_PORT'];

        $mail->setFrom($_ENV['ML_FROM'], 'Alamgir Mailer');

        foreach ($to as $email) {
            $mail->addBCC($email);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $Mailbody;
        $mail->AltBody = 'Sorry!, your mail client does not support HTML. Please use a client that supports HTML. Like Gmail, Outlook, etc.';
        foreach ($attchments as $attachment) {
            $mail->addAttachment($attachment);
        }

        if ($mail->send()) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        echo "[$Mailbody] Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}

function auth($adminId, $adminName)
{
    global $conn;
    $sql = "SELECT * FROM `admin` WHERE `adminId` = '$adminId' AND `adminUsername` = '$adminName'";
    $res = mysqli_query($conn, $sql);
    if ($res->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

if (isset($_POST['action'])) {
    $action = htmlspecialchars($_POST['action'], ENT_QUOTES, 'UTF-8');

    // admin dtls
    isset($_POST['adminId']) ? $adminId = htmlspecialchars($_POST['adminId'], ENT_QUOTES, 'UTF-8') : $adminId = '';
    isset($_POST['adminName']) ? $adminName = htmlspecialchars($_POST['adminName'], ENT_QUOTES, 'UTF-8') : $adminName = '';
    if (auth($adminId, $adminName)) {
        if ($action == 'adminLogin') {
            $sql = "SELECT name,email FROM `admin` WHERE `adminId` = '$adminId' AND `adminUsername` = '$adminName'";
            $res = mysqli_query($conn, $sql);
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                echo json_encode(array('status' => 'success', 'message' => 'Login Successful as ' . $row['name'], 'data' => $row));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Invalid Credentials. Please enter correct credentials.'));
            }
        } elseif ($action == 'addEmail') {
            // user dtls
            isset($_POST['userName']) ? $userName = htmlspecialchars($_POST['userName'], ENT_QUOTES, 'UTF-8') : $userName = '';
            isset($_POST['userEmail']) ? $userEmail = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : $userEmail = '';
            isset($_POST['userDes']) ? $userDes = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8') : $userDes = '';

            $sql = "INSERT INTO `email` (`name`, `email`, `des`, `date`) VALUES ('$userName', '$userEmail', '$userDes', " . date("d m Y h:i:s A") . ")";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(array('status' => 'success', 'message' => 'Email added successfully.'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Something went wrong. Please try again later.'));
            }
            exit();
        } elseif ($action == 'deleteEmail') {
            isset($_POST['eid']) ? $emailId = htmlspecialchars($_POST['eid'], ENT_QUOTES, 'UTF-8') : $emailId = '';

            $sql = "DELETE FROM `email` WHERE `id` = '$emailId'";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(array('status' => 'success', 'message' => 'Email deleted successfully.'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Something went wrong. Please try again later.'));
            }
            exit();
        } elseif ($action == 'updateEmail') {
            isset($_POST['eid']) ? $emailId = htmlspecialchars($_POST['eid'], ENT_QUOTES, 'UTF-8') : $emailId = '';
            isset($_POST['userName']) ? $userName = htmlspecialchars($_POST['userName'], ENT_QUOTES, 'UTF-8') : $userName = '';
            isset($_POST['userEmail']) ? $userEmail = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : $userEmail = '';
            isset($_POST['userDes']) ? $userDes = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8') : $userDes = '';

            $sql = "UPDATE `email` SET `name` = '$userName', `email` = '$userEmail', `des` = '$userDes' WHERE `id` = '$emailId'";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(array('status' => 'success', 'message' => 'Email updated successfully.'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Something went wrong. Please try again later.'));
            }
            exit();
        } elseif ($action == 'getEmailList') {
            $sql = "SELECT * FROM `email`";
            $res = mysqli_query($conn, $sql);
            if ($res->num_rows > 0) {
                echo json_encode(array('status' => 'success', 'message' => 'Email list fetched successfully.', 'data' => $res->fetch_all(MYSQLI_ASSOC)));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'No email found. Please add an email first or wait for email to be added.'));
            }
            exit();
        } elseif ($action == 'sendMail') {
            $attachments = [];
            if (isset($_FILES['attachment'])) {
                for ($i = 0; $i < count($_FILES['attachment']['name']); $i++) {
                    if ($_FILES['attachment']['error'][$i] == 0) {
                        if (!file_exists('uploads')) {
                            mkdir('uploads', 0777, true);
                        }

                        $destination = 'uploads/' . $_FILES['attachment']['name'][$i];

                        if (move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $destination)) {
                            $attachments[] = $destination;
                        } else {
                            echo "Failed to move file " . $_FILES['attachment']['name'][$i] . ".\n";
                        }
                    } else {
                        echo "Error uploading file " . $_FILES['attachment']['name'][$i] . ": " . $_FILES['attachment']['error'][$i] . "\n";
                    }
                }
            }

            isset($_POST['recipients']) ? $recipients = htmlspecialchars($_POST['recipients'], ENT_QUOTES, 'UTF-8') : $recipients = '';
            isset($_POST['subject']) ? $subjectMail = htmlspecialchars($_POST['subject'], ENT_QUOTES, 'UTF-8') : $subjectMail = '';
            isset($_POST['body']) ? $bodyMail = $_POST['body'] : $bodyMail = 'Empty Mail Body';

            str_contains($recipients, ',') ? $recipients = explode(',', $recipients) : $recipients = [$recipients];

            
            if (sendMail($recipients, $subjectMail, $bodyMail, $attachments)) {
                foreach($attachments as $attachment) {
                    unlink($attachment);
                }
                echo json_encode(array('status' => 'success', 'data' => 'Email sent successfully.'));
            } else {
                echo json_encode(array('status' => 'error', 'data' => 'Email failed to send.'));
            }
            exit();
        }
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Authentication failed. Please try to login again.'));
    }
} else {
    http_response_code(400);
    echo "<h1>Bad Request</h1><p>Your request is not valid. Please contact your administrator.<br/>Time : " . date("d M Y h:i:s A") . "</p>";
    exit();
}

?>