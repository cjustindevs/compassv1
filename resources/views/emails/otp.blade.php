<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMPASS - Email Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            padding: 40px 20px;
            color: #1F2937;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.06);
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            color: #16A34A;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .logo .tagline {
            color: #6B7280;
            font-size: 14px;
            margin-top: 2px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .message {
            color: #4B5563;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .otp-box {
            background: #ECFDF5;
            border: 2px dashed #16A34A;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin: 20px 0 24px;
        }
        .otp-box .code {
            font-size: 42px;
            letter-spacing: 12px;
            font-weight: 700;
            color: #14532D;
            font-family: 'Courier New', monospace;
        }
        .otp-box .expiry {
            font-size: 12px;
            color: #6B7280;
            margin-top: 8px;
        }
        .divider {
            border: none;
            border-top: 1px solid #E5E7EB;
            margin: 24px 0;
        }
        .footer {
            text-align: center;
            color: #9CA3AF;
            font-size: 12px;
            line-height: 1.8;
        }
        .footer .brand {
            color: #16A34A;
            font-weight: 600;
        }
        .highlight {
            color: #16A34A;
            font-weight: 600;
        }
        .note {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 12px;
        }
        @media (max-width: 480px) {
            .container { padding: 32px 20px; }
            .otp-box .code { font-size: 30px; letter-spacing: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>COMPASS</h1>
            <p class="tagline">Peer Support System · Project Dial-A-Friend</p>
        </div>

        <p class="greeting">Hello,</p>

        <p class="message">
            Thank you for choosing <strong>COMPASS</strong>. Use the verification code below to complete your registration and start your journey toward support and wellness.
        </p>

        <div class="otp-box">
            <div class="code">{{ $otp }}</div>
            <div class="expiry">⏱ This code expires in <span class="highlight">10 minutes</span></div>
        </div>

        <p class="message" style="font-size: 14px;">
            If you didn't request this code, please ignore this email or contact us at <a href="mailto:compass@dwcc.edu.ph" style="color: #16A34A;">compass@dwcc.edu.ph</a>
        </p>

        <hr class="divider">

        <div class="footer">
            <p>This is an automated message from <span class="brand">COMPASS</span></p>
            <p style="font-size: 11px;">
                &copy; 2026 COMPASS · Project Dial-A-Friend<br>
                Divine Word College of Calapan<br>
                Gov. Infantado St., Calapan City, Oriental Mindoro
            </p>
        </div>
    </div>
</body>
</html>