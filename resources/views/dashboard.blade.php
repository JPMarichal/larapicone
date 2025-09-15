<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard - {{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { font-family: 'Instrument Sans', sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .user-info { display: flex; align-items: center; gap: 15px; }
                .avatar { width: 50px; height: 50px; border-radius: 50%; }
                .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-danger { background: #dc3545; color: white; }
                .btn-danger:hover { background: #c82333; }
                .welcome-text { color: #333; }
                h1 { color: #333; margin: 0; }
                p { color: #666; }
            </style>
        @endif
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="user-info">
                    @if(auth()->user()->avatar)
                        <img src="{{ auth()->user()->avatar }}" alt="Avatar" class="avatar">
                    @endif
                    <div>
                        <h1>Welcome, {{ auth()->user()->name }}!</h1>
                        <p>{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger">Logout</button>
                </form>
            </div>
            
            <div class="welcome-text">
                <h2>Dashboard</h2>
                <p>You have successfully logged in with Google OAuth!</p>
                <p>This is your protected dashboard area.</p>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                    <h3>User Information:</h3>
                    <ul>
                        <li><strong>Name:</strong> {{ auth()->user()->name }}</li>
                        <li><strong>Email:</strong> {{ auth()->user()->email }}</li>
                        <li><strong>Google ID:</strong> {{ auth()->user()->google_id ?? 'N/A' }}</li>
                        <li><strong>Email Verified:</strong> {{ auth()->user()->email_verified_at ? 'Yes' : 'No' }}</li>
                        <li><strong>Member Since:</strong> {{ auth()->user()->created_at->format('M d, Y') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
</html>
