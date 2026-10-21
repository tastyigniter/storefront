<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http;

use Igniter\Storefront\Http\Controllers\Account\AddressController;
use Igniter\Storefront\Http\Controllers\Account\ConfirmEmailController;
use Igniter\Storefront\Http\Controllers\Account\DashboardController;
use Igniter\Storefront\Http\Controllers\Account\ForgotPasswordController;
use Igniter\Storefront\Http\Controllers\Account\LoginController;
use Igniter\Storefront\Http\Controllers\Account\OrderController;
use Igniter\Storefront\Http\Controllers\Account\PointsController;
use Igniter\Storefront\Http\Controllers\Account\ProfileController;
use Igniter\Storefront\Http\Controllers\Account\RegisterController;
use Igniter\Storefront\Http\Controllers\Account\ReservationController;
use Igniter\Storefront\Http\Controllers\Account\ResetPasswordController;
use Igniter\Storefront\Http\Controllers\BookingController;
use Igniter\Storefront\Http\Controllers\CartController;
use Igniter\Storefront\Http\Controllers\CheckoutController;
use Igniter\Storefront\Http\Controllers\ContactController;
use Igniter\Storefront\Http\Controllers\FulfillmentController;
use Igniter\Storefront\Http\Controllers\GiftCardController;
use Igniter\Storefront\Http\Controllers\GroupOrderController;
use Igniter\Storefront\Http\Controllers\HomeController;
use Igniter\Storefront\Http\Controllers\LocationController;
use Igniter\Storefront\Http\Controllers\LoyaltyController;
use Igniter\Storefront\Http\Controllers\MenuController;
use Igniter\Storefront\Http\Controllers\OrderActionController;
use Igniter\Storefront\Http\Controllers\ReviewController;
use Igniter\Storefront\Http\Controllers\VendorController;
use Igniter\Storefront\Http\Controllers\WebAppController;
use Igniter\Storefront\Http\Middleware\AuthenticateCustomer;
use Igniter\Storefront\Http\Middleware\HandleInertiaRequests;
use Igniter\Storefront\Http\Middleware\RedirectIfCustomer;
use Igniter\Storefront\Support\GroupOrders;
use Illuminate\Routing\Router;

class RouteRegistrar
{
    public function register(Router $router): void
    {
        $router->get('site.webmanifest', [WebAppController::class, 'manifest'])->name('webmanifest');
        $router->get('sw.js', [WebAppController::class, 'serviceWorker'])->name('service-worker');
        $router->get('offline.html', [WebAppController::class, 'offline'])->name('offline');

        $router->middleware(HandleInertiaRequests::class)->group(function() use ($router): void {
            $router->get('/', [HomeController::class, 'index'])->name('home');
            $router->get('order', [MenuController::class, 'current'])->name('order.current');
            $router->get('{location}/order', [MenuController::class, 'index'])->name('order');
            $router->get('checkout', [CheckoutController::class, 'index'])->name('checkout.checkout');
            $router->post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:60,1')->name('checkout.store');
            $router->post('checkout/payment', [CheckoutController::class, 'payment'])->middleware('throttle:60,1')->name('checkout.payment');
            $router->post('checkout/prepare', [CheckoutController::class, 'prepare'])->middleware('throttle:60,1')->name('checkout.prepare');
            $router->post('checkout/payment-profile/delete', [CheckoutController::class, 'deletePaymentProfile'])->name('checkout.payment-profile.delete');
            $router->get('checkout/success/{hash}', [CheckoutController::class, 'success'])->name('checkout.success');
            $router->post('orders/{hash}/reorder', [OrderActionController::class, 'reorder'])->name('order.reorder');
            $router->post('orders/{hash}/cancel', [OrderActionController::class, 'cancel'])->name('order.cancel');
            $router->post('reservations/{hash}/cancel', [BookingController::class, 'cancel'])->name('reservation.cancel');
            $router->post('reviews/{type}/{hash}', [ReviewController::class, 'store'])->whereIn('type', ['order', 'reservation'])->middleware('throttle:20,1')->name('review.store');
            $router->get('book-table', [BookingController::class, 'index'])->name('booking');
            $router->post('book-table', [BookingController::class, 'store'])->middleware('throttle:20,1')->name('booking.store');
            $router->get('book-table/success/{hash}', [BookingController::class, 'success'])->name('booking.success');
            $router->get('contact', [ContactController::class, 'index'])->name('contact');
            $router->post('contact', [ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.store');
            $router->get('locations', [LocationController::class, 'index'])->name('locations');
            $router->get('locations/{slug}/reviews', [LocationController::class, 'reviews'])->middleware('throttle:60,1')->name('locations.reviews');
            $router->get('gift-cards', [GiftCardController::class, 'index'])->name('gift-cards');
            $router->get('vendor/register', [VendorController::class, 'index'])->name('vendor.register');
            $router->post('vendor/register', [VendorController::class, 'store'])->middleware('throttle:10,1')->name('vendor.register.store');
            $router->get('confirm-email', [ConfirmEmailController::class, 'index'])->name('account.confirm');
            $router->post('confirm-email', [ConfirmEmailController::class, 'store'])->name('account.confirm.store');

            if (GroupOrders::installed()) {
                $router->post('group-order', [GroupOrderController::class, 'store'])->middleware(AuthenticateCustomer::class)->name('group-order.start');
                $router->get('group-order/join/{hash}', [GroupOrderController::class, 'join'])->name('group-order.join');
                $router->post('group-order/join/{hash}', [GroupOrderController::class, 'joinStore'])->middleware('throttle:20,1')->name('group-order.join.store');
                $router->post('group-order/invite', [GroupOrderController::class, 'invite'])->middleware('throttle:20,1')->name('group-order.invite');
                $router->post('group-order/lock', [GroupOrderController::class, 'lock'])->name('group-order.lock');
                $router->post('group-order/unlock', [GroupOrderController::class, 'unlock'])->name('group-order.unlock');
                $router->delete('group-order', [GroupOrderController::class, 'destroy'])->name('group-order.destroy');
                $router->delete('group-order/guests/{guestId}', [GroupOrderController::class, 'removeGuest'])->name('group-order.guests.destroy');
            }

            $router->post('fulfillment', [FulfillmentController::class, 'store'])->middleware('throttle:60,1')->name('fulfillment');
            $router->post('fulfillment/locate', [FulfillmentController::class, 'locate'])->middleware('throttle:60,1')->name('fulfillment.locate');
            $router->get('fulfillment/suggestions', [FulfillmentController::class, 'suggestions'])->middleware('throttle:120,1')->name('fulfillment.suggestions');
            $router->get('fulfillment/place', [FulfillmentController::class, 'place'])->middleware('throttle:60,1')->name('fulfillment.place');
            $router->put('fulfillment', [FulfillmentController::class, 'update'])->middleware('throttle:60,1')->name('fulfillment.update');
            $router->post('session', [FulfillmentController::class, 'session'])->name('session');
            $router->post('cart', [CartController::class, 'store'])->name('cart.store');
            $router->get('cart/item/{menuId}', [CartController::class, 'item'])->name('cart.item');
            $router->post('cart/tip', [CartController::class, 'tip'])->name('cart.tip');
            $router->delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
            $router->delete('cart/gift-card', [CartController::class, 'removeGiftCard'])->name('cart.gift-card.remove');
            $router->delete('cart/loyalty', [LoyaltyController::class, 'destroy'])->name('cart.loyalty.remove');
            $router->patch('cart/{rowId}', [CartController::class, 'update'])->name('cart.update');
            $router->delete('cart/{rowId}', [CartController::class, 'destroy'])->name('cart.destroy');
            $router->post('cart/coupon', [CartController::class, 'coupon'])->middleware('throttle:10,1')->name('cart.coupon');
            $router->post('cart/loyalty', [LoyaltyController::class, 'store'])->name('cart.loyalty');

            $router->middleware(RedirectIfCustomer::class)->group(function() use ($router): void {
                $router->get('login', [LoginController::class, 'index'])->name('account.login');
                $router->post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('account.login.store');
                $router->get('register', [RegisterController::class, 'index'])->name('account.register');
                $router->post('register', [RegisterController::class, 'store'])->middleware('throttle:20,1')->name('account.register.store');
                $router->get('forgot-password', [ForgotPasswordController::class, 'index'])->name('account.forgot');
                $router->post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:10,1')->name('account.forgot.store');
                $router->get('forgot-password/{code}', [ResetPasswordController::class, 'index'])->name('account.reset');
                $router->post('forgot-password/{code}', [ResetPasswordController::class, 'store'])->middleware('throttle:10,1')->name('account.reset.store');
            });

            $router->middleware(AuthenticateCustomer::class)->group(function() use ($router): void {
                $router->get('account', [DashboardController::class, 'index'])->name('account.account');
                $router->put('account', [ProfileController::class, 'update'])->name('account.profile');
                $router->delete('account', [ProfileController::class, 'destroy'])->name('account.delete');
                $router->post('logout', [LoginController::class, 'destroy'])->name('account.logout');
                $router->get('account/orders', [OrderController::class, 'index'])->name('account.orders');
                $router->get('account/order/{hash}', [OrderController::class, 'show'])->name('account.order');
                $router->get('account/reservations', [ReservationController::class, 'index'])->name('account.reservations');
                $router->get('account/reservation/{hash}', [ReservationController::class, 'show'])->name('account.reservation');
                $router->get('account/points', [PointsController::class, 'index'])->name('account.points');
                $router->get('account/address', [AddressController::class, 'index'])->name('account.address');
                $router->post('account/address', [AddressController::class, 'store'])->name('account.address.store');
                $router->delete('account/address/{addressId}', [AddressController::class, 'destroy'])->name('account.address.destroy');
            });

        });
    }
}
