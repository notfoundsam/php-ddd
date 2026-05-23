<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in</title>
</head>
<body>
    <h1>Sign in</h1>
    @if(!empty($errors ?? []))
        <ul style="color:red;">
            @foreach($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="/login">
        <input type="hidden" name="fuel_csrf_token" value="{{ $csrf_token ?? '' }}">
        <p>
            <label>Email <input type="email" name="email" value="{{ $email ?? '' }}" required></label>
        </p>
        <p>
            <label>Password <input type="password" name="password" maxlength="72" required></label>
        </p>
        <p>
            <label><input type="checkbox" name="remember" value="1"> Remember me</label>
        </p>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>
