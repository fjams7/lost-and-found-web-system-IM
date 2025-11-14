<?php
/**
 * Email Sender using Resend API
 * Free tier: 100 emails/day, 3,000 emails/month
 */

class EmailSender {
    private $apiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        // Resend API configuration
        // Users need to sign up at https://resend.com and get their API key
        $this->apiKey = getenv('RESEND_API_KEY') ?: 're_GrzeZ9wM_LvLDqFWDw4fZ4r29U8dbuS5z'; // Replace with actual key
        $this->fromEmail = 'onboarding@resend.dev'; // Resend's test email
        $this->fromName = 'Lost&Found Hub';
    }
    
    /**
     * Send email notification to item poster
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlContent Email HTML content
     * @return array Result with success status and message
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent) {
        $url = 'https://api.resend.com/emails';
        
        $data = [
            'from' => $this->fromName . ' <' . $this->fromEmail . '>',
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlContent
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Email sending error: " . $error);
            return ['success' => false, 'message' => 'Failed to send email: ' . $error];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            error_log("Email API error: HTTP $httpCode - " . $response);
            return ['success' => false, 'message' => 'Email API error: ' . $response];
        }
    }
    
    /**
     * Send contact notification to item poster
     * @param array $posterData Poster information (email, name)
     * @param array $requesterData Requester information (name, email)
     * @param array $itemData Item information (title, type)
     * @param string $message Contact message
     * @param string $contactInfo Requester's contact info
     * @return array Result with success status
     */
    public function sendContactNotification($posterData, $requesterData, $itemData, $message, $contactInfo) {
        $subject = "Someone contacted you about your {$itemData['type']} item: {$itemData['title']}";
        
        $htmlContent = $this->buildContactEmailTemplate(
            $posterData['name'],
            $requesterData['name'],
            $itemData['title'],
            $itemData['type'],
            $message,
            $contactInfo
        );
        
        return $this->sendEmail($posterData['email'], $posterData['name'], $subject, $htmlContent);
    }
    
    /**
     * Build HTML email template for contact notification
     */
    private function buildContactEmailTemplate($posterName, $requesterName, $itemTitle, $itemType, $message, $contactInfo) {
        $typeLabel = ucfirst($itemType);
        $typeColor = $itemType === 'lost' ? '#dc3545' : '#28a745';
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: $typeColor; }
        .item-title { font-size: 20px; font-weight: bold; margin: 15px 0; color: #333; }
        .message-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
        .contact-info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 New Contact Request</h1>
        </div>
        <div class="content">
            <p>Hi <strong>$posterName</strong>,</p>
            
            <p>Someone has contacted you about your <span class="badge">$typeLabel</span> item:</p>
            
            <div class="item-title">$itemTitle</div>
            
            <div class="message-box">
                <h3 style="margin-top: 0; color: #667eea;">Message from $requesterName:</h3>
                <p style="margin: 0;">$message</p>
            </div>
            
            <div class="contact-info">
                <strong>📧 Contact Information:</strong><br>
                $contactInfo
            </div>
            
            <p>Please respond to <strong>$requesterName</strong> using the contact information provided above.</p>
            
            <div class="footer">
                <p>This is an automated notification from Lost&Found Hub.<br>
                Please do not reply to this email.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
?>