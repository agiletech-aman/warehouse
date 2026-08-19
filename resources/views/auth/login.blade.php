<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Monitoring system | Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            min-height: 100vh;
            overflow: hidden;
        }

        .login-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* LEFT SIDE */

        .login-left {
            flex: 1;
            position: relative;
            background:
                linear-gradient(rgba(0, 0, 0, .55), rgba(0, 0, 0, .7)),
                url('https://plus.unsplash.com/premium_photo-1663091967607-2e15b89f4d6e?fm=jpg&q=60&w=3000&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTN8fHdhcmVob3VzZXxlbnwwfHwwfHx8MA%3D%3D');

            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            padding: 80px;
        }

        .left-content {
            max-width: 600px;
            color: white;
        }

        .left-content h1 {
            font-size: 70px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .left-content p {
            font-size: 20px;
            color: #d1d5db;
            line-height: 1.8;
        }

        /* RIGHT SIDE */

        .login-right {
            width: 450px;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .login-box {
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            margin: auto;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 700;
        }

        .logo h3 {
            margin-top: 20px;
            font-weight: 700;
        }

        .form-control {
            height: 58px;
            border-radius: 14px;
        }

        .btn-login {
            height: 58px;
            border-radius: 14px;
            background: #2563eb;
            font-weight: 600;
            border: none;
        }

        .btn-login:hover {
            background: #1d4ed8;
        }

        .remember {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 18px 0 30px;
        }

        .remember a {
            text-decoration: none;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
        }

        @media(max-width:991px) {

            .login-left {
                display: none;
            }

            .login-right {
                width: 100%;
            }

        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <!-- Left Side -->
        <div class="login-left">

            <div class="left-content">

                <h1>Warehouse Monitoring system</h1>

                <p>
                    Smart warehouse Monitoring system for temperature,
                    humidity, devices and alerts management.
                </p>

            </div>

        </div>

        <!-- Right Side -->
        <div class="login-right">

            <div class="login-box">

                <div class="logo">

                    <div>
                        <img src="{{asset('logo2.png')}}" alt="Logo" style="width: 80px; height: 80px;">
                    </div>

                    <h3>Welcome Back</h3>

                </div>

                @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
                @endif

                <form action="{{ route('admin.login') }}" method="POST">

                    @csrf

                    <div class="mb-3">

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Email Address"
                            required>

                    </div>

                    <div class="mb-3">

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Password"
                            required>

                    </div>

                    <div class="remember">

                        <div>
                            <input type="checkbox">
                            Remember me
                        </div>



                    </div>

                    <button style="background: linear-gradient(135deg, #E24B4A 0%, #639922 100%); color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 500; width: 100%; cursor: pointer; font-size: 15px; letter-spacing: 0.3px; transition: opacity 0.2s;">
                        Login
                    </button>

                </form>

                <div class="footer-text">
                    Agiletech Solutions &copy; {{ date('Y') }}. All rights reserved.
                </div>

            </div>

        </div>

    </div>

</body>

</html>