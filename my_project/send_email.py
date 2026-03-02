import smtplib
import sys
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

def send_verification_code(to_email, code):
    """Send verification code email via Gmail SMTP"""
    
    # Gmail credentials
    gmail_user = "azizmehrez050@gmail.com"
    gmail_password = "soib tarr wtkc zrmm"  # App password with spaces
    
    # Create message
    msg = MIMEMultipart('alternative')
    msg['Subject'] = 'Code de vérification - WANNASNI'
    msg['From'] = gmail_user
    msg['To'] = to_email
    
    # HTML email body
    html = f"""
    <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #2c5f2d;">Réinitialisation de mot de passe</h2>
            <p>Vous avez demandé à réinitialiser votre mot de passe.</p>
            <p>Voici votre code de vérification :</p>
            <div style="background: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;">
                <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2c5f2d;">{code}</span>
            </div>
            <p><strong>Ce code expire dans 15 minutes.</strong></p>
            <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
            <p style="color: #6b7280; font-size: 12px;">WANNASNI - L'assistant numérique intelligent pour les seniors</p>
        </body>
    </html>
    """
    
    # Attach HTML part
    part = MIMEText(html, 'html')
    msg.attach(part)
    
    try:
        # Connect to Gmail SMTP server
        server = smtplib.SMTP('smtp.gmail.com', 587)
        server.starttls()
        server.login(gmail_user, gmail_password)
        
        # Send email
        server.sendmail(gmail_user, to_email, msg.as_string())
        server.quit()
        
        print("SUCCESS")
        return True
        
    except Exception as e:
        print(f"ERROR: {str(e)}")
        return False

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python send_email.py <email> <code>")
        sys.exit(1)
    
    to_email = sys.argv[1]
    code = sys.argv[2]
    
    send_verification_code(to_email, code)
