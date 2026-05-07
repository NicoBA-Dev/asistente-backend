<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
  public function register(Request $request)
  {
    $fields = $request->validate([
      'name' => 'required|string|max:100',
      'email' => 'required|string|email|max:150|unique:users,email',
      'password' => 'required|string|confirmed',
      'timezone' => 'string|max:50'
    ]);

    $user = User::create([
      'name' => $fields['name'],
      'email' => $fields['email'],
      'password_hash' => Hash::make($fields['password']),
      'timezone' => $fields['timezone'] ?? 'America/La_Paz'
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json(['user' => $user, 'token' => $token], 201);
  }

  public function login(Request $request)
  {
    $fields = $request->validate([
      'email' => 'required|string|email',
      'password' => 'required|string'
    ]);

    $user = User::where('email', $fields['email'])->first();

    if (!$user || !Hash::check($fields['password'], $user->password_hash)) {
      throw ValidationException::withMessages([
        'email' => ['Las credenciales proporcionadas son incorrectas.'],
      ]);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json(['user' => $user, 'token' => $token], 200);
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Sesión cerrada exitosamente']);
  }
}