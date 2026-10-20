<?php

declare(strict_types=1);

namespace Igniter\Storefront\Actions;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Frontend\Actions\SendContactMail;
use Igniter\Storefront\Storefront;
use Igniter\User\Actions\DeleteCustomer;
use Igniter\User\Actions\LoginCustomer;
use Igniter\User\Actions\RegisterCustomer;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Address;
use Igniter\User\Models\Customer;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class ManageAccount
{
    public function login(array $data): void
    {
        (new LoginCustomer([
            'email' => $data['email'],
            'password' => $data['password'],
        ], (bool)($data['remember'] ?? false)))->handle();
    }

    public function register(array $data): Customer
    {
        if (!(bool)setting('allow_registration', true)) {
            throw new ApplicationException(lang('igniter.user::default.login.alert_registration_disabled'));
        }

        $customer = resolve(RegisterCustomer::class)->handle([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'telephone' => $data['telephone'],
            'newsletter' => (bool)($data['newsletter'] ?? false),
            'status' => true,
        ]);

        if ($customer->is_activated) {
            $customer->mailSendRegistration([
                'account_login_link' => route('igniter.theme.account.login'),
            ]);
        } else {
            $customer->mailSendEmailVerification([
                'account_activation_link' => route('igniter.theme.account.register').'?code='.$customer->getActivationCode(),
            ]);
        }

        return $customer;
    }

    public function activate(string $code): void
    {
        resolve(RegisterCustomer::class)->activate($code);
    }

    public function forgot(array $data): void
    {
        (new Timebox)->call(function() use ($data): void {
            $customer = Customer::query()->whereEmail($data['email'])->first();
            if ($customer instanceof Customer && $customer->enabled()) {
                $customer->resetPassword();
                $customer->mailSendResetPasswordRequest([
                    'reset_link' => route('igniter.theme.account.reset', ['code' => $customer->reset_code]),
                    'account_login_link' => route('igniter.theme.account.login'),
                ]);
            }
        }, microseconds: (int)config('igniter-auth.timeboxDuration', 2_000_000));
    }

    public function reset(string $code, array $data): void
    {
        $customer = Customer::query()->whereResetCode($code)->first();
        if (!$customer instanceof Customer || !$customer->completeResetPassword($code, $data['password'])) {
            throw ValidationException::withMessages([
                'password' => lang('igniter.user::default.reset.alert_reset_failed'),
            ]);
        }

        $customer->mailSendResetPassword([
            'account_login_link' => route('igniter.theme.account.login'),
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
    }

    /**
     * Permanently delete the signed-in customer with the user extension's DeleteCustomer
     * action. Saved cards are removed by the listener in the ServiceProvider.
     */
    public function deleteAccount(string $password): void
    {
        $customer = Auth::customer();
        if (!$customer instanceof Customer) {
            throw new ApplicationException(Storefront::lang('alert.sign_in_to_delete'));
        }

        resolve(DeleteCustomer::class)->handle($customer, $password);
    }

    public function updateProfile(array $data): void
    {
        $customer = Auth::customer();
        $customer->first_name = $data['first_name'];
        $customer->last_name = $data['last_name'];
        $customer->telephone = $data['telephone'] ?? $customer->telephone;
        if (!empty($data['password'])) {
            $customer->password = $data['password'];
        }

        $customer->save();
    }

    public function saveAddress(array $data, ?int $addressId = null): void
    {
        $customer = Auth::customer();
        $address = $addressId
            ? $customer->addresses()->whereKey($addressId)->first()
            : new Address;

        if (!$address instanceof Address) {
            throw new ApplicationException(Storefront::lang('alert.address_not_found'));
        }

        $address->fill($data);
        $address->customer_id = $customer->getKey();
        $address->save();
    }

    public function deleteAddress(int $addressId): void
    {
        $customer = Auth::customer();
        $address = $customer->addresses()->whereKey($addressId)->first();
        if (!$address instanceof Address) {
            throw new ApplicationException(Storefront::lang('alert.address_not_found'));
        }

        $address->delete();
    }

    public function contact(array $data): void
    {
        (new SendContactMail)([
            'full_name' => $data['full_name'],
            'contact_topic' => $data['subject'],
            'contact_email' => $data['email'],
            'contact_telephone' => $data['telephone'],
            'contact_message' => $data['comment'] ?? '',
        ]);
    }
}
