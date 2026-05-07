<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
  public function index(Request $request)
  {
    $contacts = Contact::where('user_id', $request->user()->id)
      ->orderBy('name', 'asc')
      ->get();

    return response()->json([
      'message' => 'Contactos recuperados.',
      'data' => $contacts
    ], 200);
  }

  public function store(Request $request)
  {
    $fields = $request->validate([
      'name' => 'required|string|max:100',
      'company_name' => 'nullable|string|max:150',
      'phone' => 'nullable|string|max:50',
    ]);

    // Usamos firstOrCreate para evitar duplicados exactos del mismo usuario
    $contact = Contact::firstOrCreate(
      [
        'user_id' => $request->user()->id,
        'name' => trim($fields['name'])
      ],
      [
        'company_name' => $fields['company_name'] ?? null,
        'phone' => $fields['phone'] ?? null
      ]
    );

    return response()->json([
      'message' => 'Contacto procesado correctamente.',
      'data' => $contact
    ], 201);
  }

  public function show(Request $request, string $id)
  {
    $contact = Contact::where('user_id', $request->user()->id)
      ->where('id', $id)
      ->first();

    if (!$contact) {
      return response()->json(['error' => 'Contacto no encontrado.'], 404);
    }

    return response()->json(['data' => $contact], 200);
  }

  public function update(Request $request, string $id)
  {
    $contact = Contact::where('user_id', $request->user()->id)
      ->where('id', $id)
      ->first();

    if (!$contact) {
      return response()->json(['error' => 'Contacto no encontrado.'], 404);
    }

    $fields = $request->validate([
      'name' => 'string|max:100',
      'company_name' => 'nullable|string|max:150',
      'phone' => 'nullable|string|max:50',
    ]);

    $contact->update($fields);

    return response()->json([
      'message' => 'Contacto actualizado.',
      'data' => $contact
    ], 200);
  }

  public function destroy(Request $request, string $id)
  {
    $contact = Contact::where('user_id', $request->user()->id)
      ->where('id', $id)
      ->first();

    if (!$contact) {
      return response()->json(['error' => 'Contacto no encontrado.'], 404);
    }

    $contact->delete();

    return response()->json(['message' => 'Contacto eliminado.'], 200);
  }
}