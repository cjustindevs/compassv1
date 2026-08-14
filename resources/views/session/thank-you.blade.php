<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Thank You</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #F8FBF9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .thank-you-card {
            background: white;
            border-radius: 28px;
            padding: 56px 48px;
            max-width: 560px;
            width: 100%;
            text-align: center;
            border: 1px solid #e5e7eb;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.06);
            position: relative;
            overflow: hidden;
        }

        .thank-you-card::before {
            content: '';
            position: absolute;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(4,160,82,0.10) 0%, transparent 70%);
        }

        .check-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            margin: 0 auto 28px;
            box-shadow: 0 12px 32px rgba(4,160,82,0.35);
            animation: pop-in 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes pop-in {
            0% { transform: scale(0.4); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        h1 {
            font-size: 26px;
            font-weight: 800;
            color: #163B2D;
            margin-bottom: 12px;
        }

        p.subtitle {
            color: #6b7280;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .heart-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: #04A052;
            font-size: 14px;
            margin: 20px 0 32px;
        }
        .heart-line::before,
        .heart-line::after {
            content: '';
            height: 1px;
            width: 56px;
            background: #e5e7eb;
        }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            font-weight: 600;
            padding: 14px 40px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.25);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(4,160,82,0.35);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            margin-top: 16px;
            transition: color 0.2s;
        }
        .btn-ghost:hover { color: #04A052; }

        @media (max-width: 480px) {
            .thank-you-card { padding: 40px 20px; border-radius: 20px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

    <div class="thank-you-card">
        <div class="check-circle">
            <i class="fas fa-check"></i>
        </div>

        <h1>Thank You!</h1>
        <p class="subtitle">
            Your feedback has been recorded and will help us
            <strong>improve the support</strong> we provide.
        </p>
        <p class="subtitle" style="margin-bottom:0;">
            We hope your session gave you the space you needed. Take care of yourself.
        </p>

        <div class="heart-line">
            <i class="fas fa-heart"></i>
            <span>You matter</span>
            <i class="fas fa-heart"></i>
        </div>

        <a href="{{ route('seeker.dashboard') }}" class="btn-primary">
            <i class="fas fa-home mr-2"></i> Go to Dashboard
        </a>

        <div>
            <a href="{{ route('request.screening') }}" class="btn-ghost">
                <i class="fas fa-comment-dots mr-1"></i> Need more support? Start a new session
            </a>
        </div>
    </div>

</body>
</html>