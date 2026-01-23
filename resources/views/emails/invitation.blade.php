<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Invitation - PESO OJT Attendance System</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, sans-serif !important;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f8fafc;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f8fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 0; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                    
                    <!-- Official Header with Logo -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); border-bottom: 3px solid #1e3a8a;">
                            <!-- PESO Logo Placeholder - Replace with hosted image URL -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                <tr>
                                    <td align="center">
                                        <!-- PESO Logo -->
                                        @php
                                            $logoUrl = env('APP_URL', 'http://localhost') . '/images/image-Photoroom.png';
                                        @endphp
                                        <img src="{{ $logoUrl }}" alt="PESO Logo" style="width: 120px; height: auto; max-height: 120px; display: block; margin: 0 auto 20px; background-color: #ffffff; border-radius: 8px; padding: 10px;" />
                                    </td>
                                </tr>
                            </table>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">
                                Public Employment Service Office
                            </h1>
                            <p style="margin: 8px 0 0; color: #e0e7ff; font-size: 16px; font-weight: 500; letter-spacing: 0.5px;">
                                OJT ATTENDANCE SYSTEM
                            </p>
                            <p style="margin: 12px 0 0; color: #c7d2fe; font-size: 14px; font-weight: 400;">
                                City of Cabuyao
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content Section -->
                    <tr>
                        <td style="padding: 50px 40px;">
                            <!-- Formal Greeting -->
                            <p style="margin: 0 0 24px; color: #1e293b; font-size: 18px; font-weight: 600; line-height: 1.5;">
                                Dear {{ $user->name }},
                            </p>
                            
                            <!-- Main Message -->
                            <p style="margin: 0 0 20px; color: #334155; font-size: 16px; line-height: 1.7; text-align: justify;">
                                We are pleased to inform you that your registration request for the <strong>PESO OJT Attendance System</strong> has been reviewed and <strong>approved</strong> by the system administrator.
                            </p>
                            
                            <p style="margin: 0 0 20px; color: #334155; font-size: 16px; line-height: 1.7; text-align: justify;">
                                Your account has been created with the following role assignment:
                            </p>
                            
                            <!-- Role Badge -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 24px 0 32px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" style="border-collapse: collapse;">
                                            <tr>
                                                <td style="background-color: #eff6ff; border: 2px solid #3b82f6; border-radius: 6px; padding: 12px 24px;">
                                                    <p style="margin: 0; color: #1e40af; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        {{ ucfirst($role) }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 32px; color: #334155; font-size: 16px; line-height: 1.7; text-align: justify;">
                                To complete your account setup and activate your access to the system, please accept this invitation and establish your account password by clicking the button below:
                            </p>
                            
                            <!-- Primary CTA Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 32px 0 40px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $invitationUrl }}" style="display: inline-block; padding: 16px 40px; background-color: #1e40af; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center; letter-spacing: 0.3px; box-shadow: 0 4px 6px rgba(30, 64, 175, 0.25); transition: background-color 0.2s;">
                                            Accept Invitation & Set Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Alternative Link Section -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 32px 0 0; background-color: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 12px; color: #475569; font-size: 14px; font-weight: 500; line-height: 1.6;">
                                            Alternative Access Method:
                                        </p>
                                        <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.6;">
                                            If the button above does not function properly, please copy and paste the following link into your web browser's address bar:
                                        </p>
                                        <p style="margin: 12px 0 0; padding: 12px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; word-break: break-all;">
                                            <a href="{{ $invitationUrl }}" style="color: #1e40af; text-decoration: none; font-size: 13px; font-family: 'Courier New', monospace;">{{ $invitationUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Notice -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 32px 0 0;">
                                <tr>
                                    <td style="padding: 20px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                                        <p style="margin: 0 0 8px; color: #92400e; font-size: 14px; font-weight: 600; line-height: 1.6;">
                                            ⚠️ Important Notice:
                                        </p>
                                        <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.6;">
                                            This invitation link will expire in <strong>7 days</strong> from the date of issuance. If you did not request this invitation or believe this email was sent in error, please disregard this message and contact the system administrator immediately.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Closing -->
                            <p style="margin: 32px 0 0; color: #334155; font-size: 16px; line-height: 1.7;">
                                We look forward to your participation in the PESO OJT Attendance System.
                            </p>
                            
                            <p style="margin: 24px 0 0; color: #334155; font-size: 16px; line-height: 1.7;">
                                Respectfully yours,<br>
                                <strong style="color: #1e40af;">PESO Cabuyao City</strong><br>
                                <span style="color: #64748b; font-size: 14px;">System Administration</span>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Official Footer -->
                    <tr>
                        <td style="padding: 40px; background-color: #1e293b; border-top: 3px solid #0f172a;">
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <p style="margin: 0 0 8px; color: #ffffff; font-size: 16px; font-weight: 600;">
                                            Public Employment Service Office
                                        </p>
                                        <p style="margin: 0; color: #cbd5e1; font-size: 14px;">
                                            City of Cabuyao, Laguna
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px 0; border-top: 1px solid #334155;">
                                        <p style="margin: 0 0 12px; color: #94a3b8; font-size: 12px; text-align: center; line-height: 1.6;">
                                            © {{ date('Y') }} Public Employment Service Office - Cabuyao City. All rights reserved.
                                        </p>
                                        <p style="margin: 0; color: #64748b; font-size: 11px; text-align: center; line-height: 1.6; font-style: italic;">
                                            This is an automated system-generated message. Please do not reply directly to this email.<br>
                                            For inquiries or technical support, please contact the PESO office directly.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
