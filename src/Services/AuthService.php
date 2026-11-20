<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Core\Session;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\Member;
use App\Models\PremiumMember;
use App\Models\User;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Session $session,
    ) {}

    /** F-02: registrasi member baru (selalu Member reguler, aktif). */
    public function register(array $data): Member
    {
        $name     = trim((string) ($data['name'] ?? ''));
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $confirm  = (string) ($data['confirmPassword'] ?? '');
        $errors   = [];

        $nameLen = (int) preg_match_all('/./us', $name); // hitung karakter Unicode tanpa ext-mbstring
        if ($nameLen < 3 || $nameLen > 50) {
            $errors['name'] = 'Nama harus 3 sampai 50 karakter';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid';
        } elseif ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Email sudah digunakan';
        }

        if (strlen($password) < 8 || strlen($password) > 72
            || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Password 8 sampai 72 karakter dan memuat huruf serta angka';
        }

        if ($password !== $confirm) {
            $errors['confirmPassword'] = 'Konfirmasi password tidak cocok';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $member = new Member(
            id: 'U' . strtoupper(bin2hex(random_bytes(4))),
            name: $name,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
        );
        $this->users->save($member);
        return $member;
    }

    /** F-03: login. Pesan gagal sama untuk "email tidak ada" dan "password salah". */
    public function login(string $email, string $password): User
    {
        $email = strtolower(trim($email));
        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email wajib diisi';
        }
        if ($password === '') {
            $errors['password'] = 'Password wajib diisi';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // samakan waktu proses agar keberadaan email tidak bisa ditebak
            password_verify($password, password_hash('dummy-password', PASSWORD_DEFAULT));
            throw BusinessRuleException::unauthorized('Email atau password salah');
        }
        if (!$user->verifyPassword($password)) {
            throw BusinessRuleException::unauthorized('Email atau password salah');
        }
        if (!$user->isActive()) {
            throw BusinessRuleException::forbidden('Akun Anda nonaktif, hubungi admin');
        }

        $this->session->login($user->getId());
        return $user;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    /** User yang sedang login, atau null (tamu / akun sudah dinonaktifkan). */
    public function currentUser(): ?User
    {
        $id = $this->session->getUserId();
        if ($id === null) {
            return null;
        }
        $user = $this->users->findById($id);
        if (!$user instanceof User || !$user->isActive()) {
            $this->session->destroy();
            return null;
        }
        return $user;
    }

    // ---- guard otorisasi, dipakai semua controller (termasuk milik Anggota 2) ----

    public function requireUser(): User
    {
        return $this->currentUser() ?? throw BusinessRuleException::unauthorized();
    }

    public function requireAdmin(): User
    {
        $user = $this->requireUser();
        if ($user->getRole() !== 'admin') {
            throw BusinessRuleException::forbidden('Hanya admin yang dapat mengakses fitur ini');
        }
        return $user;
    }

    public function requireMember(): Member
    {
        $user = $this->requireUser();
        if (!$user instanceof Member) {
            throw BusinessRuleException::forbidden('Hanya member yang dapat mengakses fitur ini');
        }
        return $user;
    }

    /** F-09: simulasi upgrade ke premium. */
    public function upgradeToPremium(): User
    {
        $member = $this->requireMember();
        if ($member instanceof PremiumMember) {
            throw new BusinessRuleException('Anda sudah menjadi member premium', 409);
        }
        $premium = $member->upgrade();
        $this->users->save($premium);
        return $premium;
    }
}
