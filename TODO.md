# TODO: Add Email Notification for Intervention Creation

## Tasks
- [ ] Create EmailService for sending emails
- [ ] Modify ServiceAdminController to send email after intervention creation
- [ ] Test email functionality

## Details
- When an intervention is assigned, send a "DEVI DE TRAVAIL" (work quote) email to the senior
- Use the existing InterventionPdfGeneratorService to generate the quote PDF
- Attach the PDF to the email
- Use Symfony Mailer for sending emails
