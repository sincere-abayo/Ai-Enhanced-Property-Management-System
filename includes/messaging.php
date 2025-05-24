<?php
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// For AfricasTalking SDK
use AfricasTalking\SDK\AfricasTalking;
/**
 * Send email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $plainText Plain text version of email
 * @return bool True if email sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $plainText = '') {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Gmail SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'infofonepo@gmail.com'; // Gmail address
        $mail->Password = 'zaoxwuezfjpglwjb'; // Gmail app password
        $mail->SMTPSecure = 'tls'; // Using string 'tls' instead of constant
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('station@gmail.com', 'Property Management System');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Add plain text alternative if provided
        if (!empty($plainText)) {
            $mail->AltBody = $plainText;
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send SMS using AfricasTalking
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $message SMS message
 * @return bool True if SMS sent successfully, false otherwise
 */
function sendSMS($phoneNumber, $message) {
    // AfricasTalking credentials
    $username = "Iot_project";
    $apiKey = "atsk_6ccbe2174a56e50490d59c73c1f7177fc02e47c2cdecb5343b67e6680bc321677b10c4bd";
    
    // Format phone number (ensure it has country code)
    if (!preg_match('/^\+/', $phoneNumber)) {
        // Add Rwanda country code if not present (adjust as needed)
        $phoneNumber = '+250' . ltrim($phoneNumber, '0');
    }
    
    // Truncate message if longer than 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    
    // Initialize the SDK
    $AT = new AfricasTalking($username, $apiKey);
    
    // Get the SMS service
    $sms = $AT->sms();
    
    try {
        // Send the message
        $result = $sms->send([
            'to' => $phoneNumber,
            'message' => $message
        ]);
        
        // Check if the message was sent successfully
        if ($result['status'] == 'success' && !empty($result['data']->SMSMessageData->Recipients)) {
            $recipient = $result['data']->SMSMessageData->Recipients[0];
            if ($recipient->status == 'Success') {
                return true;
            }
        }
        
        error_log("SMS could not be sent. Status: " . json_encode($result));
        return false;
    } catch (Exception $e) {
        error_log("SMS could not be sent. Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate HTML email template for property messages
 * 
 * @param string $subject Email subject
 * @param string $message Email message
 * @param array $sender Sender information (name, role)
 * @return string HTML email template
 */
function getEmailTemplate($subject, $message, $sender) {
    $senderName = $sender['first_name'] . ' ' . $sender['last_name'];
    $senderRole = ucfirst(string: $sender['role']);
    
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$subject}</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #1a56db;
                padding: 20px;
                color: white;
                text-align: center;
            }
            .content {
                padding: 20px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #666;
            }
            .message {
                background-color: white;
                padding: 15px;
                border-left: 4px solid #1a56db;
                margin-bottom: 20px;
            }
            .sender-info {
                margin-bottom: 15px;
                font-size: 14px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Property Management System</h2>
            </div>
            <div class="content">
                <h3>{$subject}</h3>
                <div class="sender-info">
                    From: {$senderName} ({$senderRole})
                </div>
                <div class="message">
                    {$message}
                </div>
                <p>Please log in to your tenant portal to reply to this message.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from your Property Management System.</p>
                <p>Please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Generate HTML email template for property inquiries
 * 
 * @param string $name Inquirer's name
 * @param string $email Inquirer's email
 * @param string $phone Inquirer's phone
 * @param string $message Inquiry message
 * @param int|null $propertyId Property ID (if applicable)
 * @return string HTML email template
 */
function getInquiryEmailTemplate($name, $email, $phone, $message, $propertyId = null) {
    global $pdo;
    
    // Get property details if property ID is provided
    $propertyInfo = '';
    if ($propertyId) {
        try {
            $stmt = $pdo->prepare("
                SELECT property_name, address, city, state, zip_code, monthly_rent
                FROM properties
                WHERE property_id = ?
            ");
            $stmt->execute([$propertyId]);
            $property = $stmt->fetch();
            
            if ($property) {
                $propertyInfo = "
                <div class='property-info'>
                    <h4 style='margin-bottom: 10px;'>Property Information</h4>
                    <p><strong>Name:</strong> {$property['property_name']}</p>
                    <p><strong>Address:</strong> {$property['address']}, {$property['city']}, {$property['state']} {$property['zip_code']}</p>
                    <p><strong>Monthly Rent:</strong> $" . number_format($property['monthly_rent'], 2) . "</p>
                </div>";
            }
        } catch (Exception $e) {
            error_log("Error fetching property details for email: " . $e->getMessage());
        }
    }
    
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>New Property Inquiry</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #1a56db;
                padding: 20px;
                color: white;
                text-align: center;
            }
            .content {
                padding: 20px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #666;
            }
            .message {
                background-color: white;
                padding: 15px;
                border-left: 4px solid #1a56db;
                margin-bottom: 20px;
            }
            .property-info {
                background-color: #f0f4ff;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .contact-info {
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>New Property Inquiry</h2>
            </div>
            <div class="content">
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    <p><strong>Name:</strong> {$name}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Phone:</strong> {$phone}</p>
                </div>
                
                {$propertyInfo}
                
                <h3>Message</h3>
                <div class="message">
                    {$message}
                </div>
                
                <p>Please respond to this inquiry as soon as possible.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from your Property Management System.</p>
                <p>&copy; PropertyPro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}