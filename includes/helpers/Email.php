<?php
/**
 * Email Helper
 * Sends emails for user notifications
 */

class Email {
    private static $fromEmail = '';
    private static $fromName = 'SAMS System';

    public static function initialize() {
        // Load default from email or use system email
        self::$fromEmail = defined('SYSTEM_EMAIL') ? SYSTEM_EMAIL : 'noreply@sams.local';
    }

    /**
     * Send password to newly created user
     */
    public static function sendUserPasswordEmail($email, $fullName, $password, $role) {
        self::initialize();

        $roleText = ucfirst($role);
        $subject = "Your SAMS Account - Login Credentials";

        // Create email body
        $body = self::getPasswordEmailTemplate($fullName, $email, $password, $role);

        // Send email
        return self::send($email, $fullName, $subject, $body);
    }

    /**
     * Send welcome email
     */
    public static function sendWelcomeEmail($email, $fullName) {
        self::initialize();

        $subject = "Welcome to SAMS - Student Attendance Management System";
        $body = self::getWelcomeTemplate($fullName);

        return self::send($email, $fullName, $subject, $body);
    }

    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($email, $fullName, $resetToken, $baseUrl) {
        self::initialize();

        $subject = "Password Reset - SAMS Account";
        $resetLink = $baseUrl . '?token=' . urlencode($resetToken);
        $body = self::getPasswordResetTemplate($fullName, $resetLink);

        return self::send($email, $fullName, $subject, $body);
    }

    /**
     * Send generic email
     */
    private static function send($to, $toName, $subject, $body) {
        self::initialize();

        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . self::$fromName . " <" . self::$fromEmail . ">\r\n";
        $headers .= "Reply-To: " . self::$fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        // Try to send
        try {
            $success = @mail($to, $subject, $body, $headers);
            
            // Log the email send attempt
            error_log("[EMAIL] " . ($success ? "Sent" : "Failed") . " to $to - Subject: $subject");
            
            return $success;
        } catch (Exception $e) {
            error_log("[EMAIL_ERROR] " . $e->getMessage());
            return false;
        }
    }

    /**
     * Email template for password
     */
    private static function getPasswordEmailTemplate($fullName, $email, $password, $role) {
        $roleText = ucfirst($role);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .credentials { background: white; border: 1px solid #ddd; padding: 15px; border-radius: 3px; margin: 20px 0; }
                .label { font-weight: bold; color: #007bff; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
                .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 3px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Welcome to SAMS</h2>
                    <p>Student Attendance Management System</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>$fullName</strong>,</p>
                    
                    <p>Your account as a <strong>$roleText</strong> has been created in the SAMS (Student Attendance Management System).</p>
                    
                    <p>Your login credentials are:</p>
                    
                    <div class='credentials'>
                        <p><span class='label'>Email:</span> $email</p>
                        <p><span class='label'>Password:</span> <code>$password</code></p>
                    </div>
                    
                    <div class='warning'>
                        ⚠️ <strong>Important:</strong>
                        <ul>
                            <li>Please change your password after your first login</li>
                            <li>Keep your password confidential</li>
                            <li>Do not share your credentials with anyone</li>
                        </ul>
                    </div>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Open the SAMS application or visit the web portal</li>
                        <li>Log in with the credentials above</li>
                        <li>Complete your profile information</li>
                        <li>Change your password for security</li>
                    </ol>
                    
                    <p>If you did not request this account or have any questions, please contact the system administrator.</p>
                    
                    <p>Best regards,<br><strong>SAMS Administration Team</strong></p>
                    
                    <div class='footer'>
                        <p>This is an automated email. Please do not reply directly to this email.</p>
                        <p>&copy; " . date('Y') . " Student Attendance Management System. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Email template for welcome
     */
    private static function getWelcomeTemplate($fullName) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h2>Welcome to SAMS!</h2></div>
                <p>Hello $fullName,</p>
                <p>Welcome to the Student Attendance Management System.</p>
                <p>You're all set to get started. Log in to access your dashboard.</p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Email template for password reset
     */
    private static function getPasswordResetTemplate($fullName, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
                .button { background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h2>Password Reset Request</h2></div>
                <p>Hello $fullName,</p>
                <p>We received a request to reset your password. Click the link below to reset it:</p>
                <a href='$resetLink' class='button'>Reset Password</a>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't request a password reset, ignore this email.</p>
            </div>
        </body>
        </html>
        ";
    }
}
?>
