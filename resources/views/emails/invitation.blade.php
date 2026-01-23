<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Invitation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #dc2626 0%, #1e293b 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">PESO OJT Attendance System</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #1e293b; font-size: 20px; font-weight: 600;">Welcome, {{ $user->name }}!</h2>
                            
                            <p style="margin: 0 0 20px; color: #475569; font-size: 16px; line-height: 1.6;">
                                Your registration request has been approved. You have been assigned the role of <strong>{{ ucfirst($role) }}</strong> in the PESO OJT Attendance System.
                            </p>
                            
                            <p style="margin: 0 0 30px; color: #475569; font-size: 16px; line-height: 1.6;">
                                Click the button below to accept your invitation and set up your account password:
                            </p>
                            
                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $invitationUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;">
                                            Accept Invitation & Set Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                <a href="{{ $invitationUrl }}" style="color: #2563eb; word-break: break-all;">{{ $invitationUrl }}</a>
                            </p>
                            
                            <p style="margin: 30px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                This invitation link will expire in 7 days. If you didn't request this invitation, please ignore this email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8fafc; border-radius: 0 0 8px 8px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; color: #64748b; font-size: 12px; text-align: center; line-height: 1.6;">
                                © {{ date('Y') }} PESO Cabuyao City. All rights reserved.<br>
                                This is an automated message, please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
