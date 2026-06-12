<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, sans-serif;
        }

        body{
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            background:linear-gradient(135deg,#4f46e5,#7c3aed);
        }

        .login-box{
            width:380px;
            background:#fff;
            padding:40px;
            border-radius:15px;
            box-shadow:0 15px 35px rgba(0,0,0,.2);
        }

        .login-box h2{
            text-align:center;
            margin-bottom:30px;
            color:#333;
        }

        .input-group{
            margin-bottom:20px;
        }

        .input-group label{
            display:block;
            margin-bottom:8px;
            color:#555;
        }

        .input-group input{
            width:100%;
            padding:14px;
            border:1px solid #ddd;
            border-radius:8px;
            outline:none;
        }

        .input-group input:focus{
            border-color:#4f46e5;
        }

        button{
            width:100%;
            padding:14px;
            border:none;
            background:#4f46e5;
            color:white;
            border-radius:8px;
            cursor:pointer;
            font-size:16px;
            transition:.3s;
        }

        button:hover{
            background:#4338ca;
        }

        .error{
            color:red;
            text-align:center;
            margin-top:15px;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Admin Login</h2>

    <form method="POST" action="{{ route('admin.login') }}">
        @csrf

        <div class="input-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="Enter Email" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter Password" required>
        </div>

        <button type="submit">
            Login
        </button>

        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
        @endif

    </form>
</div>

</body>
</html>