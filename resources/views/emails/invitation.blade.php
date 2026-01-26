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
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f1f5f9;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f1f5f9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Card container: matches register page card (rounded-xl, border, shadow) -->
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); overflow: hidden;">
                    <!-- Top accent bar: red → red-500 → slate-900 (register card gradient) -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(to right, #dc2626 0%, #ef4444 50%, #0f172a 100%); font-size: 0; line-height: 0;">&nbsp;</td>
                    </tr>
                    <!-- Card header + watermark row -->
                    <tr>
                        <td style="padding: 0;">
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <!-- Main header content -->
                                    <td style="padding: 32px 32px 24px 32px; vertical-align: top; width: 100%;">
                                        <h1 style="margin: 0 0 6px; color: #0f172a; font-size: 22px; font-weight: 600; line-height: 1.3;">
                                            Account Invitation
                                        </h1>
                                        <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 1.5;">
                                            Your registration has been approved. Set your password and activate your account using the link below.
                                        </p>
                                    </td>
                                    <!-- Watermark: PESO logo (register-card style) -->
                                    <td style="vertical-align: top; width: 140px; padding: 16px 24px 0 0; text-align: right;">
                                        @if(isset($message) && $logoPath && file_exists($logoPath))
                                            <img src="{{ $message->embed($logoPath) }}" alt="" style="width: 160px; height: auto; max-height: 160px; opacity: 0.08; display: block; margin-left: auto;" />
                                        @elseif($logoBase64)
                                            <img src="{{ $logoBase64 }}" alt="" style="width: 160px; height: auto; max-height: 160px; opacity: 0.08; display: block; margin-left: auto;" />
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 0 32px 32px;">
                            <p style="margin: 0 0 20px; color: #0f172a; font-size: 16px; font-weight: 500; line-height: 1.5;">
                                Dear {{ $user->name }},
                            </p>
                            <p style="margin: 0 0 16px; color: #334155; font-size: 15px; line-height: 1.6;">
                                Your registration request for the <strong>PESO OJT Attendance System</strong> has been reviewed and <strong>approved</strong>. Your account has been created with the following role:
                            </p>
                            <!-- Role badge: red/slate accent -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 20px 0 24px;">
                                <tr>
                                    <td>
                                        <span style="display: inline-block; background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 20px; border-radius: 8px;">
                                            {{ ucfirst($role) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 24px; color: #334155; font-size: 15px; line-height: 1.6;">
                                To complete setup and activate your access, click the button below to accept this invitation and set your password:
                            </p>
                            <!-- CTA: red button (register page style) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 28px;">
                                <tr>
                                    <td>
                                        <a href="{{ $invitationUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #dc2626; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; text-align: center; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                                            Accept Invitation & Set Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <!-- Alternative link: red left border -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 24px; background-color: #f8fafc; border-left: 4px solid #dc2626; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <p style="margin: 0 0 8px; color: #475569; font-size: 13px; font-weight: 500;">
                                            If the button doesn’t work, copy and paste this link into your browser:
                                        </p>
                                        <p style="margin: 0; word-break: break-all;">
                                            <a href="{{ $invitationUrl }}" style="color: #dc2626; text-decoration: none; font-size: 12px; font-family: 'Courier New', monospace;">{{ $invitationUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <!-- Important notice -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 24px;">
                                <tr>
                                    <td style="padding: 16px; background-color: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px;">
                                        <p style="margin: 0 0 4px; color: #92400e; font-size: 13px; font-weight: 600;">Important</p>
                                        <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.5;">
                                            This link expires in <strong>7 days</strong>. If you didn’t request this, disregard this email and contact the administrator.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 8px; color: #334155; font-size: 15px; line-height: 1.6;">
                                We look forward to your participation in the PESO OJT Attendance System.
                            </p>
                            <p style="margin: 0; color: #334155; font-size: 15px; line-height: 1.6;">
                                Respectfully yours,<br>
                                <strong style="color: #0f172a;">PESO Cabuyao City</strong><br>
                                <span style="color: #64748b; font-size: 14px;">System Administration</span>
                            </p>
                        </td>
                    </tr>
                    <!-- Footer: card-style (Official use only, support email) -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0 0 8px; color: #64748b; font-size: 12px; text-align: center; line-height: 1.5;">
                                Official use only. Accounts are activated after you accept this invitation.
                            </p>
                            <p style="margin: 0 0 8px; color: #64748b; font-size: 12px; text-align: center; line-height: 1.5;">
                                Need help? Email <a href="mailto:pesocabuyaocity@gmail.com?subject=PESO%20OJT%20Attendance%20-%20Invitation%20Support" style="color: #dc2626; font-weight: 600; text-decoration: none;">pesocabuyaocity@gmail.com</a>.
                            </p>
                            <p style="margin: 0; color: #94a3b8; font-size: 11px; text-align: center; line-height: 1.5; font-style: italic;">
                                This is an automated message. Please do not reply directly to this email.
                            </p>
                            <p style="margin: 8px 0 0; color: #94a3b8; font-size: 11px; text-align: center;">
                                © {{ date('Y') }} Public Employment Service Office — City of Cabuyao
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
