
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse IoT | Admin Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:'Segoe UI',sans-serif;

            background:
            linear-gradient(rgba(8,18,37,.55),rgba(8,18,37,.55)),
            url('https://res.cloudinary.com/dvc1vatxw/image/upload/v1781327758/ChatGPT_Image_Jun_13_2026_10_43_20_AM_q9jwkk.png');

            background-size:cover;
            background-position:center;
            background-repeat:no-repeat;
        }

        .login-card{
            width:430px;
            padding:45px;

            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.15);

            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);

            border-radius:25px;

            box-shadow:
            0 8px 32px rgba(0,0,0,.25);
        }

        .logo{
            text-align:center;
            margin-bottom:35px;
        }

        .logo h1{
            color:#fff;
            font-size:38px;
            font-weight:700;
            margin-bottom:8px;
        }

        .logo p{
            color:rgba(255,255,255,.8);
            margin-bottom:0;
        }

        .form-label{
            color:#fff;
            font-weight:500;
            margin-bottom:10px;
        }

        .form-control{
            height:55px;
            border-radius:15px;

            background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.15);

            color:#fff;
            padding-left:18px;
        }

        .form-control::placeholder{
            color:rgba(255,255,255,.65);
        }

        .form-control:focus{
            background:rgba(255,255,255,.15);
            border-color:#3b82f6;
            color:#fff;
            box-shadow:none;
        }

        .btn-login{
            height:55px;
            border:none;
            border-radius:15px;

            background:#2563eb;
            color:white;

            font-size:16px;
            font-weight:600;

            transition:.3s;
        }

        .btn-login:hover{
            background:#1d4ed8;
        }

        .footer-text{
            text-align:center;
            color:rgba(255,255,255,.75);
            margin-top:20px;
            font-size:14px;
        }

        .alert{
            border-radius:15px;
        }

        @media(max-width:576px){

            .login-card{
                width:95%;
                padding:30px;
            }

            .logo h1{
                font-size:30px;
            }

        }

    </style>

</head>
<body>

<div class="login-card">

    <div class="logo">
        <h1>Warehouse IoT</h1>
        <p>Admin Login</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('admin.login') }}" method="POST">

        @csrf

        <div class="mb-4">
            <label class="form-label">
                Email Address
            </label>

            <input
                type="email"
                name="email"
                class="form-control"
                placeholder="Enter your email"
                required>
        </div>

        <div class="mb-4">
            <label class="form-label">
                Password
            </label>

            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required>
        </div>

        <button type="submit" class="btn btn-login w-100">
            Sign In
        </button>

    </form>

    <div class="footer-text">
        Smart Warehouse Monitoring System
    </div>

</div>

</body>
</html>

