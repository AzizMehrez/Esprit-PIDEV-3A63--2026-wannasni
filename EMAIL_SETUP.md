# Email Configuration Guide

## Setup Email Sending (Gmail)

### Step 1: Enable 2-Factor Authentication on Gmail
1. Go to your Google Account: https://myaccount.google.com
2. Navigate to Security → 2-Step Verification
3. Enable 2-Step Verification

### Step 2: Create an App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select app: "Mail"
3. Select device: "Other" (enter "WANNASNI")
4. Click "Generate"
5. Copy the 16-character password (remove spaces)

### Step 3: Update .env file
```env
MAILER_DSN=gmail+smtp://your.email@gmail.com:your-app-password@default
```

Replace:
- `your.email@gmail.com` with your Gmail address
- `your-app-password` with the 16-character app password (no spaces)

Example:
```env
MAILER_DSN=gmail+smtp://wannasni.app@gmail.com:abcdabcdabcdabcd@default
```

### Step 4: Clear cache
```bash
php bin/console cache:clear
```

## How the Forgot Password Flow Works

1. **User enters email** → Goes to `/fr/forgot-password`
2. **System generates 6-digit code** → Sends email with code (expires in 15 minutes)
3. **User enters code** → Goes to `/fr/verify-code`
4. **Code verified** → User can now reset password
5. **Password reset** → User can login with new password

## Testing Without Email (Development)

When `MAILER_DSN=null://null`, the verification code will be displayed in flash messages instead of sent by email. This is useful for development testing.

## Alternative Email Providers

### Using SMTP (any provider)
```env
MAILER_DSN=smtp://username:password@smtp.example.com:587
```

### Using SendGrid
```env
MAILER_DSN=sendgrid://API_KEY@default
```

### Using Mailgun
```env
MAILER_DSN=mailgun://API_KEY:DOMAIN@default
```

## Security Notes

- Verification codes expire after 15 minutes
- Codes are 6 digits (000000-999999)
- Session-based verification prevents token reuse
- Email existence is not revealed to prevent user enumeration
- Reset tokens are 64-character hex strings for security

## Troubleshooting

### "Failed to authenticate" error
- Check your app password is correct (no spaces)
- Ensure 2FA is enabled on Gmail
- Try regenerating the app password

### Emails not arriving
- Check spam/junk folder
- Verify email address is correct
- Check Gmail sending limits (500/day for free accounts)
- Review logs: `var/log/dev.log`

### Session expired errors
- Increase session lifetime in `config/packages/framework.yaml`
- Check browser cookies are enabled
