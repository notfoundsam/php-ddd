<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
</head>
<body>
    <h1>Admin Login</h1>
    @if(!empty($errors ?? []))
        <ul style="color:red;">
            @foreach($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="/admin/login">
        <p>
            <label>Email <input type="email" name="email" value="{{ $email ?? '' }}" required></label>
        </p>
        <p>
            <label>Password <input type="password" name="password" required></label>
        </p>
        <p>
            <label><input type="checkbox" name="remember" value="1"> Remember me</label>
        </p>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>
