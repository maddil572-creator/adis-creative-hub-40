<?php
/**
 * Email Class using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

class Email {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }
    
    /**
     * Configure PHPMailer
     */
    private function configure() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            
            // Default sender
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Character set
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $is_html = false, $attachments = []) {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Recipients
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mailer->addAddress($name);
                    } else {
                        $this->mailer->addAddress($email, $name);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Content
            $this->mailer->isHTML($is_html);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            // Attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mailer->addAttachment($attachment);
                }
            }
            
            $this->mailer->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send contact form notification
     */
    public function sendContactNotification($form_data) {
        $subject = 'New Contact Form Submission - ' . ($form_data['subject'] ?? 'No Subject');
        
        $body = "New contact form submission received:\n\n";
        $body .= "Name: " . ($form_data['name'] ?? 'Not provided') . "\n";
        $body .= "Email: " . ($form_data['email'] ?? 'Not provided') . "\n";
        $body .= "Phone: " . ($form_data['phone'] ?? 'Not provided') . "\n";
        $body .= "Subject: " . ($form_data['subject'] ?? 'Not provided') . "\n\n";
        $body .= "Message:\n" . ($form_data['message'] ?? 'No message') . "\n\n";
        
        if (!empty($form_data['service'])) {
            $body .= "Service: " . $form_data['service'] . "\n";
        }
        
        if (!empty($form_data['budget'])) {
            $body .= "Budget: " . $form_data['budget'] . "\n";
        }
        
        if (!empty($form_data['timeline'])) {
            $body .= "Timeline: " . $form_data['timeline'] . "\n";
        }
        
        $body .= "\n---\n";
        $body .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
        $body .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
        
        return $this->send(SMTP_FROM_EMAIL, $subject, $body);
    }
    
    /**
     * Send newsletter welcome email
     */
    public function sendNewsletterWelcome($email, $name = null) {
        $subject = 'Welcome to Adil GFX Newsletter!';
        
        $body = "Hi" . ($name ? " {$name}" : "") . ",\n\n";
        $body .= "Thank you for subscribing to the Adil GFX newsletter!\n\n";
        $body .= "You'll receive:\n";
        $body .= "• Design tips and tutorials\n";
        $body .= "• Latest portfolio updates\n";
        $body .= "• Exclusive offers and discounts\n";
        $body .= "• Industry insights and trends\n\n";
        $body .= "As a welcome gift, here are 5 free YouTube thumbnail templates:\n";
        $body .= APP_URL . "/free-templates\n\n";
        $body .= "Best regards,\n";
        $body .= "Adil\n";
        $body .= "Professional Designer\n";
        $body .= APP_URL . "\n\n";
        $body .= "---\n";
        $body .= "You can unsubscribe at any time by clicking here: " . APP_URL . "/unsubscribe?email=" . urlencode($email);
        
        return $this->send($email, $subject, $body);
    }
    
    /**
     * Send lead magnet email
     */
    public function sendLeadMagnet($email, $name, $magnet_type = 'templates') {
        $subject = 'Your Free Design Resources from Adil GFX';
        
        $body = "Hi {$name},\n\n";
        $body .= "Thank you for your interest in my design services!\n\n";
        
        switch ($magnet_type) {
            case 'templates':
                $body .= "As promised, here are your 5 free YouTube thumbnail templates:\n";
                $body .= APP_URL . "/downloads/youtube-thumbnail-templates.zip\n\n";
                $body .= "These templates have been used to create thumbnails that achieved:\n";
                $body .= "• 15%+ click-through rates\n";
                $body .= "• Millions of views\n";
                $body .= "• Increased subscriber growth\n\n";
                break;
                
            case 'media_kit':
                $body .= "Here's my complete media kit with portfolio samples and pricing:\n";
                $body .= APP_URL . "/downloads/adil-gfx-media-kit.pdf\n\n";
                break;
        }
        
        $body .= "Need custom design work? I'd love to help you:\n";
        $body .= "• Logo Design - Starting at $149\n";
        $body .= "• YouTube Thumbnails - Starting at $49\n";
        $body .= "• Video Editing - Starting at $299\n";
        $body .= "• Complete Branding - Starting at $999\n\n";
        $body .= "Ready to get started? Reply to this email or visit:\n";
        $body .= APP_URL . "/contact\n\n";
        $body .= "Best regards,\n";
        $body .= "Adil\n";
        $body .= "Professional Designer\n";
        $body .= "WhatsApp: " . WHATSAPP_NUMBER . "\n";
        $body .= APP_URL;
        
        return $this->send($email, $subject, $body);
    }
    
    /**
     * Send project quote email
     */
    public function sendProjectQuote($email, $name, $project_details, $estimated_price) {
        $subject = "Your Project Quote - $" . number_format($estimated_price, 2);
        
        $body = "Hi {$name},\n\n";
        $body .= "Thank you for your interest in my design services!\n\n";
        $body .= "Based on your project requirements, here's your custom quote:\n\n";
        $body .= "PROJECT DETAILS:\n";
        $body .= "Service: " . ($project_details['service'] ?? 'Custom Project') . "\n";
        $body .= "Package: " . ($project_details['package'] ?? 'Custom') . "\n";
        
        if (!empty($project_details['timeline'])) {
            $body .= "Timeline: " . $project_details['timeline'] . "\n";
        }
        
        $body .= "\nESTIMATED INVESTMENT: $" . number_format($estimated_price, 2) . "\n\n";
        $body .= "This includes:\n";
        $body .= "• Professional design work\n";
        $body .= "• Multiple concepts/revisions\n";
        $body .= "• All source files\n";
        $body .= "• Commercial usage rights\n";
        $body .= "• 24-48 hour delivery\n\n";
        $body .= "Ready to move forward? Let's schedule a quick call to discuss your vision:\n";
        $body .= CALENDLY_URL . "\n\n";
        $body .= "Or reply to this email with any questions!\n\n";
        $body .= "Best regards,\n";
        $body .= "Adil\n";
        $body .= "Professional Designer\n";
        $body .= "WhatsApp: " . WHATSAPP_NUMBER . "\n";
        $body .= APP_URL;
        
        return $this->send($email, $subject, $body);
    }
}
?>