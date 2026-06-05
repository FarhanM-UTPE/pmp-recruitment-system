<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Login - Patria Maritim Perkasa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl p-8 border border-gray-200 w-full max-w-md">
        <div class="flex justify-center mb-6">
            <img alt="Patria Maritim Perkasa Logo" class="w-28 h-auto mx-auto"
                src="{{ asset('images/Logo Patria.png') }}">
        </div>
        <h1 class="text-2xl font-semibold text-gray-900 text-center mb-2">Login</h1>
        <p class="text-sm text-gray-600 text-center mb-6">Masuk ke sistem manajemen tes karyawan</p>
        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email atau NRP</label>
                <input type="text" name="email" id="email"
                    class="mt-1 block w-full border border-gray-300 rounded-lg p-2" value="{{ old('email') }}" required
                    autofocus autocomplete="username">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password"
                    class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Login</button>
        </form>
    </div>
</body>

</html>