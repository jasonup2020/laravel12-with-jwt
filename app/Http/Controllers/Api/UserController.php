<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Hobby;
use App\Services\UserService;

class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = $this->userService->getAllUserWithHobbies();
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->storeUser($request->validated());

        return response()->json($user->load('hobbies'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load('hobbies');
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->updateUser($request->validated(), $user);

        return response()->json($user->load('hobbies'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->userService->destroyUser($user);
        return response()->json(['message' => 'User deleted']);
    }
}
