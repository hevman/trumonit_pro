<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OidcSessionController;
use App\Http\Controllers\Auth\SamlSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SsoSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('auth/sso/redirect', [SsoSessionController::class, 'redirectByTenantSlug'])
        ->middleware('throttle:oidc-auth')
        ->name('sso.redirect');
    Route::get('auth/oidc/redirect', [OidcSessionController::class, 'redirectByTenantSlug'])
        ->middleware('throttle:oidc-auth')
        ->name('sso.oidc.redirect');
    Route::get('auth/oidc/{tenantSlug}/callback', [OidcSessionController::class, 'callback'])
        ->where('tenantSlug', '[a-z0-9\\-]{2,80}')
        ->middleware('throttle:oidc-auth')
        ->name('sso.oidc.callback');
    Route::get('auth/saml/redirect', [SamlSessionController::class, 'redirectByTenantSlug'])
        ->middleware('throttle:saml-auth')
        ->name('sso.saml.redirect');
    Route::match(['get', 'post'], 'auth/saml/{tenantSlug}/acs', [SamlSessionController::class, 'acs'])
        ->where('tenantSlug', '[a-z0-9\\-]{2,80}')
        ->middleware('throttle:saml-auth')
        ->name('sso.saml.acs');
    Route::get('auth/saml/{tenantSlug}/metadata', [SamlSessionController::class, 'metadata'])
        ->where('tenantSlug', '[a-z0-9\\-]{2,80}')
        ->middleware('throttle:saml-auth')
        ->name('sso.saml.metadata');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});
Route::match(['get', 'post'], 'auth/saml/{tenantSlug}/slo', [SamlSessionController::class, 'slo'])
    ->where('tenantSlug', '[a-z0-9\\-]{2,80}')
    ->middleware('throttle:saml-auth')
    ->name('sso.saml.slo');

Route::middleware(['auth', 'session.governance'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout.get');
});
