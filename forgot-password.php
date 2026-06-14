<?php
// forgot-password.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Vishwakarma Jagruti Manch</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }

        .login-page {
    width: 100%;
    min-height: 100vh;
    display: flex;
    background: linear-gradient(
        90deg,
        rgba(20, 0, 3, 0.98),
        rgba(35, 0, 5, 0.85)
    );
    overflow: hidden;
}

        .brand-side {
            width: 58%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #fff;
        }

        .brand-box {
            max-width: 620px;
            text-align: center;
        }

        .main-logo {
            width: 95px;
            height: 95px;
            object-fit: contain;
            border-radius: 50%;
            background: #fff;
            padding: 5px;
            box-shadow: 0 0 25px rgba(255, 180, 70, 0.9);
            margin-bottom: 22px;
        }

        .brand-box h1 {
            font-size: 42px;
            line-height: 1.2;
            font-weight: 900;
            letter-spacing: 1px;
            color: #fff;
            text-shadow: 0 3px 8px rgba(0,0,0,0.4);
        }

        .brand-box h1 span {
            display: block;
            color: #ffd23f;
        }

        .brand-box h3 {
            margin-top: 14px;
            font-size: 22px;
            color: #ffe6a3;
            font-weight: 700;
        }

        .welcome-text {
            margin-top: 55px;
            font-size: 20px;
            font-weight: 700;
        }

        .small-text {
            margin: 18px auto 0;
            max-width: 500px;
            font-size: 16px;
            line-height: 1.6;
            font-weight: 600;
        }

        .stats-box {
            margin-top: 70px;
            border: 1px solid rgba(255,255,255,0.45);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            overflow: hidden;
            backdrop-filter: blur(5px);
        }

        .stats-box div {
            width: 33.33%;
            padding: 18px 10px;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.35);
        }

        .stats-box div:last-child {
            border-right: none;
        }

        .stats-box i {
            color: #ffdc4a;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .stats-box h2 {
            font-size: 24px;
            color: #fff;
        }

        .stats-box p {
            font-size: 13px;
            font-weight: 600;
        }

       .form-side {
    width: 58%;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5px 80px 35px 35px;
}

        .login-card {
            width: 100%;
            max-width: 620px;
            background: rgba(255,255,255,0.94);
            padding: 48px 58px;
            border-radius: 22px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
        }

        .login-card h2 {
            text-align: center;
            font-family: Georgia, serif;
            font-size: 38px;
            color: #2f0101;
            margin-bottom: 5px;
        }

        .sub-title {
            text-align: center;
            color: #5a1414;
            margin-bottom: 35px;
        }

        .login-card label {
            font-size: 14px;
            color: #333;
            display: block;
            margin-bottom: 8px;
        }

        .input-box {
            height: 56px;
            border: 1px solid #ddd;
            border-radius: 9px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            margin-bottom: 24px;
            background: #fff;
        }

        .input-box i {
            color: #6c7280;
            font-size: 18px;
            margin-right: 14px;
        }

        .input-box input {
            border: none;
            outline: none;
            width: 100%;
            height: 100%;
            font-size: 15px;
            color: #333;
        }

        .login-btn {
            width: 100%;
            height: 58px;
            border: none;
            border-radius: 7px;
            background: linear-gradient(90deg, #2a0303, #a80d0d);
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .login-btn i {
            margin-left: 10px;
        }

        .help-text {
            text-align: center;
            margin-top: 28px;
            color: #666;
            font-size: 15px;
        }

        .help-text a {
            color: #9d0a0a;
            font-weight: 700;
            text-decoration: none;
        }

        .register-text {
            text-align: center;
            margin-top: 18px;
            color: #666;
            font-size: 15px;
        }

        .register-text a {
            color: #8c0707;
            font-weight: 700;
            text-decoration: none;
        }

        .help-text a:hover,
        .register-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .login-page {
                flex-direction: column;
            }

            .brand-side,
            .form-side {
                width: 100%;
                min-height: auto;
            }

            .brand-side {
                padding: 35px 20px;
            }

            .brand-box h1 {
                font-size: 30px;
            }

            .brand-box h3 {
                font-size: 17px;
            }

            .welcome-text {
                margin-top: 30px;
            }

            .stats-box {
                margin-top: 35px;
            }

            .form-side {
                padding: 25px 15px 40px;
            }

            .login-card {
                padding: 35px 24px;
                border-radius: 18px;
            }

            .login-card h2 {
                font-size: 30px;
            }
        }

        @media (max-width: 480px) {
            .stats-box {
                flex-direction: column;
            }

            .stats-box div {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.35);
            }

            .stats-box div:last-child {
                border-bottom: none;
            }
        }
    </style>
</head>
<body>


<?php include 'header.php'; ?>
<div class="login-page">

    <div class="brand-side">
        <div class="brand-box">

            <img src="images/logo.png" class="main-logo" alt="Logo">

            <h1>
                <span>VISHWAKARMA</span>
                JAGRUTI MANCH
            </h1>

            <h3>एकता • सेवा • संस्कार • समृद्धि</h3>

            <p class="welcome-text">Reset Your Password</p>
            <p class="small-text">
                Enter your registered email or mobile number. We will help you recover your account.
            </p>

            <div class="stats-box">
                <div>
                    <i class="fa-solid fa-users"></i>
                    <h2>50K+</h2>
                    <p>Members</p>
                </div>

                <div>
                    <i class="fa-solid fa-gopuram"></i>
                    <h2>1,245+</h2>
                    <p>Temples</p>
                </div>

                <div>
                    <i class="fa-solid fa-user-group"></i>
                    <h2>22</h2>
                    <p>States</p>
                </div>
            </div>

        </div>
    </div>

    <div class="form-side">
        <div class="login-card">

            <h2>Forgot Password?</h2>
            <p class="sub-title">Recover your account password</p>

            <form action="reset-password.php" method="POST">

                <label>Email / Mobile Number</label>

                <div class="input-box">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="mobile_email" placeholder="Enter your registered email or mobile number" required>
                </div>

                <button type="submit" class="login-btn">
                    Continue <i class="fa-solid fa-arrow-right"></i>
                </button>

                <p class="help-text">
                    Remember your password?
                    <a href="login.php">Back to Login</a>
                </p>

                <p class="register-text">
                    Don't have an account?
                    <a href="register.php">Register Now</a>
                </p>

            </form>

        </div>
    </div>

</div>

</body>
</html>