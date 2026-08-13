<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>COMPASS – Email Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7faf9;
            padding: 48px 20px;
            color: #1e293b;
        }

        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 28px;
            padding: 52px 44px;
            box-shadow: 0 24px 72px rgba(0, 0, 0, 0.04);
            border: 1px solid #eef2f0;
        }

        /* ---- Header ---- */
        .header {
            text-align: center;
            margin-bottom: 36px;
        }

        .header .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 14px;
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #0f2e1f;
            letter-spacing: -0.4px;
        }

        .header .sub {
            color: #64748b;
            font-size: 14px;
            margin-top: 2px;
        }

        /* ---- Body ---- */
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0f2e1f;
            margin-bottom: 10px;
        }

        .message {
            color: #475569;
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 20px;
        }

        .message strong {
            color: #16a34a;
        }

        /* ---- OTP Box ---- */
        .otp-box {
            background: #f2fbf5;
            border: 2px solid #d1f0df;
            border-radius: 18px;
            padding: 28px 20px;
            text-align: center;
            margin: 24px 0 20px;
        }

        .otp-box .code {
            font-size: 44px;
            letter-spacing: 14px;
            font-weight: 700;
            color: #14532d;
            font-family: 'Courier New', monospace;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .otp-box .expiry {
            font-size: 13px;
            color: #64748b;
            margin-top: 10px;
        }

        .otp-box .expiry span {
            color: #16a34a;
            font-weight: 600;
        }

        /* ---- Divider ---- */
        .divider {
            border: none;
            border-top: 1px solid #e9edec;
            margin: 28px 0;
        }

        /* ---- Footer ---- */
        .footer {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.9;
        }

        .footer .brand {
            color: #16a34a;
            font-weight: 600;
        }

        .footer .contact-link {
            color: #16a34a;
            text-decoration: none;
            font-weight: 500;
        }

        .footer .contact-link:hover {
            text-decoration: underline;
        }

        .footer .meta {
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 4px;
        }

        /* ---- Responsive ---- */
        @media (max-width: 520px) {
            .container {
                padding: 32px 20px;
                border-radius: 20px;
            }

            .otp-box .code {
                font-size: 30px;
                letter-spacing: 10px;
                padding: 6px 12px;
            }

            .header h1 {
                font-size: 22px;
            }

            .message {
                font-size: 14px;
            }
        }

        @media (max-width: 380px) {
            .otp-box .code {
                font-size: 24px;
                letter-spacing: 6px;
            }

            .container {
                padding: 24px 16px;
            }
        }
    </style>
</head>
<body>

    <div class="container">

        <!-- Header -->
        <div class="header">
            <h1>PROJECT DIAL‑A‑FRIEND</h1>
            <p class="sub">Compass Peer Support System</p>
        </div>

        <!-- Greeting -->
        <p class="greeting">Hello,</p>

        <!-- Message -->
        <p class="message">
            Thank you for choosing <strong>COMPASS</strong>. We are truly honored that you've taken this meaningful step toward your well‑being.
            Just as a compass guides travelers through unfamiliar paths, COMPASS is here to provide a safe, compassionate, and supportive space
            where you can seek guidance, share your experiences, and connect with people who genuinely care.
        </p>

        <p class="message">
            Every step you take matters. Reaching out for support is a courageous act of self‑care and growth.
            To help us protect your account and maintain a trusted community, please use the verification code below
            to complete your registration.
        </p>

        
        <!-- OTP Box -->
        <div class="otp-box">
            <div class="code">{{ $otp }}</div>
            <div class="expiry">
                ⏱ This code expires in <span>10 minutes</span>
            </div>
        </div>

        <p class="message" style="font-size: 14px; margin-top: 4px;">
            If you did not request this code, please ignore this email or contact us at
            <a href="mailto:projectdialafriendorganization@gmail.com" class="contact-link">
                projectdialafriendorganization@gmail.com
            </a>
        </p>

        <hr class="divider" />

        <!-- Footer -->
        <div class="footer">
            <p>This is an automated message from <span class="brand">COMPASS</span></p>
            <p style="font-size: 13px;">
                &copy; 2026 COMPASS &middot; Project Dial‑A‑Friend
            </p>
            <p class="meta">
                Divine Word College of Calapan<br />
                Gov. Infantado St., Calapan City, Oriental Mindoro
            </p>
        </div>

    </div>

</body>
</html>