<?php
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user = current_user();
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'client') {
        redirect('clients/portal.php');
    } elseif ($user['role'] == 'client') {
        redirect('projects/project_list.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] == 'lockout') {
    $error = "You have been logged out because you signed in on another device.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if (login($email, $password)) {
        $user = current_user();
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'client') {
            redirect('clients/portal.php');
        } elseif ($user['role'] == 'client') {
            redirect('projects/project_list.php');
        } else {
            redirect('dashboard.php');
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <style>
        body {
            background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="card glass-panel w-full max-w-md shadow-2xl animate-fade-in-up">
        <div class="card-body">
            <div class="text-center mb-6">
                <h1 class="text-4xl font-bold text-gray-800 tracking-tight"><?php echo APP_NAME; ?></h1>
                <p class="text-gray-600 mt-2">Welcome back! Please sign in.</p>
            </div>

            <?php if ($error): ?>
                <div role="alert" class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" x-data="{ showPass: false }">
                <?php csrf_field(); ?>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Email</span>
                    </label>
                    <input type="email" name="email" placeholder="email@example.com" class="input input-bordered bg-white/50 focus:bg-white transition-colors" required />
                </div>
                <div class="form-control mt-4">
                    <label class="label">
                        <span class="label-text font-semibold">Password</span>
                    </label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'" name="password" placeholder="••••••••" class="input input-bordered w-full bg-white/50 focus:bg-white transition-colors" required />
                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700" @click="showPass = !showPass">
                            <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.574-2.59M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                <div class="form-control mt-6">
                    <button class="btn btn-primary text-white border-none bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-lg">Sign In</button>
                </div>
            </form>
            
            <div class="divider text-gray-500 text-xs">OR</div>
            
            <div class="text-center text-sm">
                <a href="#" class="link link-hover text-gray-600">Forgot password?</a>
            </div>
        </div>
    </div>

</body>
</html>
