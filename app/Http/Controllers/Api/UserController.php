<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Hobby;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('hobbies')->get();
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users',
            'password' => 'required',
            'hobbies' => 'array'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        foreach ($request->hobbies as $hobi) {
            Hobby::create([
                'user_id' => $user->id,
                'nama' => $hobi
            ]);
        }

        return response()->json($user->load('hobbies'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('hobbies')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $user->update($request->only(['name', 'email']));
        
        if ($request->filled('password')) {
            $user->update(['password' => bcrypt($request->password)]);
        }

        $user->hobbies()->delete();

        foreach ($request->hobbies as $hobi) {
            Hobby::create([
                'user_id' => $user->id,
                'nama' => $hobi
            ]);
        }

        return response()->json($user->load('hobbies'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
