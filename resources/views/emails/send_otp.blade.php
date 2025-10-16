<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
</head>
<body>
    <h2>Hello {{ $user->first_name ?? 'User' }},</h2>
    <p>Your OTP code for verification is:</p>

    <h1 style="color: #2c3e50;">{{ $otp }}</h1>

    <p>This OTP will expire in 10 minutes.</p>
    <p>Thank you,<br>Celebrate Now Team</p>
</body>
</html>
