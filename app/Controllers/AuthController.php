<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Student;
use App\Models\User;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public function showLogin(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect(Auth::home());
        }

        return $this->view('auth/login', ['title' => 'Log in · CampusIQ', 'tab' => 'login'], 'public');
    }

    public function login(Request $request): Response
    {
        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');
        $old = ['login' => $login];

        $throttle = $_SESSION['_login_throttle'] ?? ['count' => 0, 'until' => 0];
        if ($throttle['until'] > time()) {
            $wait = $throttle['until'] - time();
            return $this->back('/login', ['login' => "Too many tries. Wait {$wait} seconds and try again."], $old);
        }

        $errors = [];
        if ($login === '') {
            $errors['login'] = 'Enter your ID number or email.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }
        if ($errors) {
            return $this->back('/login', $errors, $old);
        }

        $user = Auth::attempt($login, $password);
        if (!$user) {
            $throttle['count']++;
            if ($throttle['count'] >= self::MAX_ATTEMPTS) {
                $throttle = ['count' => 0, 'until' => time() + self::LOCK_SECONDS];
            }
            $_SESSION['_login_throttle'] = $throttle;
            return $this->back('/login', ['login' => 'That ID number or email and password don\'t match.'], $old);
        }

        unset($_SESSION['_login_throttle']);
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        Auth::login($user, (bool) $request->input('remember'));

        $home = Auth::home($user);
        $staffArea = !str_starts_with((string) $intended, '/my/');
        $target = ($intended && ($user['role'] === 'staff') === $staffArea) ? $intended : $home;

        return $this->redirect($target);
    }

    public function showSignup(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect(Auth::home());
        }

        return $this->view('auth/signup', ['title' => 'Sign up · CampusIQ', 'tab' => 'signup'], 'public');
    }

    /**
     * Students and parents only. They link to a student by student number + the email the school has on file:
     * the student's own email for students, the guardian email for parents. Staff accounts come from the seed.
     */
    public function signup(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect(Auth::home());
        }
        $data = $request->only(['role', 'student_no', 'email', 'password', 'password_confirmation']);
        $data['email'] = strtolower($data['email']);
        $errors = [];

        if (!in_array($data['role'], ['student', 'parent'], true)) {
            $errors['role'] = 'Choose student or parent.';
        }
        if ($data['student_no'] === '') {
            $errors['student_no'] = 'Enter the student number, e.g. 10-24031.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($data['password']) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'The passwords don\'t match.';
        }

        $student = null;
        if (!$errors) {
            $student = Student::findByNumber($data['student_no']);
            $emailOnFile = $student ? strtolower($data['role'] === 'parent' ? $student['guardian_email'] : $student['email']) : null;
            if (!$student || $emailOnFile !== $data['email']) {
                $who = $data['role'] === 'parent' ? 'guardian email' : 'student email';
                $errors['student_no'] = "We couldn't match that student number with that {$who}. Use the email the school has on file.";
            } elseif (User::existsForStudent((int) $student['id'], $data['role'])) {
                $errors['student_no'] = 'An account already exists for this ' . $data['role'] . '. Log in instead.';
            } elseif (User::findBy('email', $data['email'])) {
                $errors['email'] = 'That email already has an account. Log in instead.';
            }
        }

        if ($errors) {
            return $this->back('/signup', $errors, $data);
        }

        $idNumber = $data['role'] === 'parent' ? 'P-' . $student['student_no'] : $student['student_no'];
        $userId = User::create([
            'role' => $data['role'],
            'id_number' => $idNumber,
            'name' => $data['role'] === 'parent' ? $student['guardian_name'] : Student::fullName($student),
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'student_id' => $student['id'],
        ]);

        Auth::login(User::find($userId));
        flash('success', "Account created. Your login ID is {$idNumber}.");

        return $this->redirect('/my/records');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        flash('success', 'You\'re logged out.');

        return $this->redirect('/login');
    }
}
