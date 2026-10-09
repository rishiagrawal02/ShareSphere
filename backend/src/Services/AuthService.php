<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\UserRepository;
use App\Support\SessionManager;
use App\Support\Validator;

class AuthService
{
    // Fixed dummy hash for constant-time comparison on unknown email
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012';

    private UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    public function registerDonor(array $input): array
    {
        // Role check: Only 'donor' can register through public donor registration
        $role = $input['role'] ?? 'donor';
        if ($role !== 'donor') {
            throw new ValidationFailedException(['role' => 'Invalid registration role. Only donor registration is allowed.']);
        }

        $validated = Validator::validate($input, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:10',
            'password_confirmation' => 'required|string',
            'phone' => 'string|max:32',
        ]);

        if (($input['accept_terms'] ?? false) !== true && ($input['accept_terms'] ?? '') !== '1') {
            throw new ValidationFailedException(['accept_terms' => 'You must accept the Terms and Conditions.']);
        }

        if ($validated['password'] !== $validated['password_confirmation']) {
            throw new ValidationFailedException(['password_confirmation' => 'Password confirmation does not match.']);
        }

        $existing = $this->userRepo->findByEmail($validated['email']);
        if ($existing !== null) {
            throw new ConflictException('An account with this email address already exists.', 'EMAIL_TAKEN');
        }

        $passwordHash = password_hash($validated['password'], PASSWORD_DEFAULT);

        $user = $this->userRepo->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password_hash' => $passwordHash,
            'role' => 'donor',
            'account_status' => 'active',
            'phone' => $validated['phone'] ?? null,
        ]);

        SessionManager::setUser((int) $user['id'], $user['role']);
        SessionManager::regenerate();

        return $user;
    }

    public function login(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        if ($email === '' || $password === '') {
            throw new UnauthorizedException('Invalid email or password', 'INVALID_CREDENTIALS');
        }

        $user = $this->userRepo->findByEmail($email);

        if ($user === null) {
            // Constant-time execution to prevent email enumeration
            password_verify($password, self::DUMMY_HASH);
            throw new UnauthorizedException('Invalid email or password', 'INVALID_CREDENTIALS');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new UnauthorizedException('Invalid email or password', 'INVALID_CREDENTIALS');
        }

        if ($user['account_status'] === 'suspended') {
            throw new ForbiddenException('Your account has been suspended. Please contact support.', 'ACCOUNT_SUSPENDED');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userRepo->updatePasswordHash((int) $user['id'], $newHash);
        }

        $this->userRepo->updateLastLogin((int) $user['id']);

        SessionManager::setUser((int) $user['id'], $user['role']);
        SessionManager::regenerate();

        unset($user['password_hash']);
        return $user;
    }

    public function logout(): void
    {
        SessionManager::destroy();
    }

    public function getCurrentUser(?int $userId = null): ?array
    {
        $id = $userId ?? SessionManager::getUserId();
        if ($id === null) {
            return null;
        }

        $user = $this->userRepo->findById($id);
        if ($user === null) {
            SessionManager::destroy();
            return null;
        }

        if ($user['account_status'] === 'suspended') {
            SessionManager::destroy();
            throw new ForbiddenException('Account suspended', 'ACCOUNT_SUSPENDED');
        }

        unset($user['password_hash']);
        return $user;
    }
}
