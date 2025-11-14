# Email Notification Setup Guide

The Lost&Found Hub system now includes email notifications when users contact item posters. This guide explains how to set up the email functionality.

## Email Service: Resend

We're using [Resend](https://resend.com) as the email service provider because:
- **Free tier**: 100 emails/day, 3,000 emails/month
- **Easy setup**: Simple API integration
- **Reliable**: High deliverability rates
- **No credit card required** for free tier

## Setup Instructions

### Step 1: Create a Resend Account

1. Go to [https://resend.com](https://resend.com)
2. Sign up for a free account
3. Verify your email address

### Step 2: Get Your API Key

1. Log in to your Resend dashboard
2. Navigate to **API Keys** section
3. Click **Create API Key**
4. Give it a name (e.g., "Lost&Found Hub")
5. Copy the API key (it starts with `re_`)

### Step 3: Configure the System

Open the file `/api/email-sender.php` and update line 13:

```php
$this->apiKey = 'YOUR_RESEND_API_KEY_HERE'; // Replace with your actual API key
```

**Example:**
```php
$this->apiKey = 're_AbCdEfGh123456789'; // Your actual key from Resend
```

### Step 4: (Optional) Set Up Custom Domain

By default, emails are sent from `onboarding@resend.dev`. To use your own domain:

1. In Resend dashboard, go to **Domains**
2. Click **Add Domain**
3. Enter your domain name
4. Follow DNS configuration instructions
5. Once verified, update line 14 in `email-sender.php`:

```php
$this->fromEmail = 'noreply@yourdomain.com'; // Your verified domain
```

### Step 5: (Optional) Use Environment Variable

For better security, use an environment variable instead of hardcoding the API key:

1. Add to your server's environment variables:
   ```bash
   export RESEND_API_KEY='re_YourActualAPIKey'
   ```

2. The code already checks for this environment variable first:
   ```php
   $this->apiKey = getenv('RESEND_API_KEY') ?: 're_123456789';
   ```

## Testing the Email Feature

1. Make sure you have at least two user accounts registered
2. User A posts a lost/found item
3. User B logs in and clicks "Contact Poster" on User A's item
4. Fill in the message and contact info
5. Submit the form
6. User A should receive an email notification at their registered email address

## Email Template

The email includes:
- **Subject**: "Someone contacted you about your [lost/found] item: [Item Title]"
- **Content**:
  - Greeting with poster's name
  - Item type badge (Lost/Found)
  - Item title
  - Message from the requester
  - Requester's contact information
  - Professional footer

## Troubleshooting

### Emails Not Sending

1. **Check API Key**: Ensure your Resend API key is correct
2. **Check Logs**: Look at `/api_errors.log` for error messages
3. **Verify Email**: Make sure the poster's email in the database is valid
4. **Rate Limits**: Free tier has 100 emails/day limit

### Common Errors

- **401 Unauthorized**: Invalid API key
- **403 Forbidden**: Domain not verified (if using custom domain)
- **429 Too Many Requests**: Rate limit exceeded

### Check PHP Error Logs

```bash
tail -f /var/log/apache2/error.log  # For Apache
tail -f /var/log/nginx/error.log    # For Nginx
```

## Alternative Email Services

If you prefer a different service, you can modify `/api/email-sender.php` to use:

- **SendGrid**: Similar free tier (100 emails/day)
- **Mailgun**: 5,000 emails/month free
- **Amazon SES**: Pay-as-you-go pricing
- **PHPMailer**: Use SMTP with Gmail/Outlook

## Security Notes

1. **Never commit API keys** to version control
2. Use environment variables for production
3. Implement rate limiting to prevent abuse
4. Validate email addresses before sending
5. Consider adding CAPTCHA to contact forms

## Support

For issues with:
- **Resend service**: Contact [Resend Support](https://resend.com/support)
- **System integration**: Check the code in `/api/email-sender.php` and `/api/items.php`

## Free Tier Limits

- **Resend Free**: 100 emails/day, 3,000/month
- Upgrade to paid plans for higher limits if needed

---

**Note**: The contact feature will still work even if email sending fails. Messages are always saved to the database, and email is sent as an additional notification.