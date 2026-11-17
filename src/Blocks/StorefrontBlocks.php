<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Main\Classes\BaseBlock;

/**
 * Page builder blocks shared by the storefront themes. Each theme registers the
 * blocks it supports and provides a Vue view for each block code.
 */
class StorefrontBlocks
{
    /**
     * Storefront pages that render their own view, keyed by page code (the route
     * name): [block code, language key, icon].
     */
    public const array PAGE_VIEWS = [
        'order' => ['menu-list', 'block_menu_list', 'fa fa-utensils'],
        'checkout.checkout' => ['checkout-form', 'block_checkout_form', 'fa fa-cash-register'],
        'checkout.success' => ['order-confirmation', 'block_order_confirmation', 'fa fa-file-invoice'],
        'booking' => ['booking-form', 'block_booking_form', 'fa fa-calendar-check'],
        'booking.success' => ['booking-confirmation', 'block_booking_confirmation', 'fa fa-calendar-day'],
        'contact' => ['contact-form', 'block_contact_form', 'fa fa-address-card'],
        'locations' => ['location-list', 'block_location_list', 'fa fa-location-dot'],
        'account.login' => ['login-form', 'block_login_form', 'fa fa-right-to-bracket'],
        'account.register' => ['register-form', 'block_register_form', 'fa fa-user-plus'],
        'account.forgot' => ['forgot-password-form', 'block_forgot_password_form', 'fa fa-key'],
        'account.reset' => ['reset-password-form', 'block_reset_password_form', 'fa fa-key'],
        'account.account' => ['account-dashboard', 'block_account_dashboard', 'fa fa-gauge'],
        'account.address' => ['account-address', 'block_account_address', 'fa fa-address-book'],
        'account.orders' => ['account-orders', 'block_account_orders', 'fa fa-bag-shopping'],
        'account.order' => ['account-order', 'block_account_order', 'fa fa-file-invoice'],
        'account.reservations' => ['account-reservations', 'block_account_reservations', 'fa fa-calendar'],
        'account.reservation' => ['account-reservation', 'block_account_reservation', 'fa fa-calendar-day'],
        'account.points' => ['account-points', 'block_account_points', 'fa fa-coins'],
    ];

    /**
     * @return list<BaseBlock|class-string<BaseBlock>>
     */
    public static function definitions(array $except = []): array
    {
        return self::without(array_merge(self::contentBlocks(), self::homeBlocks(), self::pageViewBlocks()), $except);
    }

    /**
     * Blocks that make up the menu page, for themes with views for them.
     *
     * @return list<class-string<BaseBlock>>
     */
    public static function menuBlocks(): array
    {
        return [
            LocalHeader::class,
            OrderOptions::class,
            CategoryList::class,
            MenuItemList::class,
        ];
    }

    /**
     * Blocks that make up the checkout page, for themes with views for them.
     *
     * @return list<class-string<BaseBlock>>
     */
    public static function checkoutBlocks(): array
    {
        return [
            CheckoutHeader::class,
            CheckoutForm::class,
            OrderSummary::class,
        ];
    }

    /**
     * Blocks that make up the other storefront pages (contact, locations,
     * booking, order and booking confirmations, sign in and account pages),
     * for themes with views for them. With the menu and checkout blocks they
     * replace every page view.
     *
     * @return list<class-string<BaseBlock>>
     */
    public static function pageBlocks(): array
    {
        return [
            PageHeader::class,
            Breadcrumb::class,
            OrderingFrom::class,
            ContactDetails::class,
            ContactForm::class,
            LocationFilters::class,
            LocationList::class,
            BookingForm::class,
            BookingSummary::class,
            BookingConfirmation::class,
            ReservationDetails::class,
            ReservationActions::class,
            ReviewForm::class,
            OrderConfirmation::class,
            OrderProgress::class,
            OrderDetails::class,
            OrderContact::class,
            OrderActions::class,
            AuthPanel::class,
            LoginForm::class,
            RegisterForm::class,
            ForgotPasswordForm::class,
            ResetPasswordForm::class,
            AccountHeader::class,
            AccountNav::class,
            AccountDashboard::class,
            AccountDelete::class,
            AccountAddress::class,
            AddressForm::class,
            AccountOrders::class,
            AccountReservations::class,
            AccountPoints::class,
            PointsHistory::class,
        ];
    }

    /**
     * The language key for a buildable page's name in the page builder.
     */
    public static function pageLabel(string $pageCode): string
    {
        return 'igniterlabs.storefront::default.builder.page_'.str_replace('.', '_', $pageCode);
    }

    /**
     * Leaves out the blocks a theme has no view for.
     *
     * @param list<BaseBlock|class-string<BaseBlock>> $blocks
     * @param list<string> $except Block codes to leave out
     * @return list<BaseBlock|class-string<BaseBlock>>
     */
    public static function without(array $blocks, array $except): array
    {
        if ($except === []) {
            return $blocks;
        }

        return array_values(array_filter($blocks, function(BaseBlock|string $block) use ($except): bool {
            $code = is_string($block) ? (new $block)->getCode() : $block->getCode();

            return !in_array($code, $except, true);
        }));
    }

    /**
     * @return list<class-string<BaseBlock>>
     */
    public static function contentBlocks(): array
    {
        return [
            Heading::class,
            RichText::class,
            Image::class,
            ImageText::class,
            CallToAction::class,
            Html::class,
            Spacer::class,
        ];
    }

    /**
     * @return list<class-string<BaseBlock>>
     */
    public static function homeBlocks(): array
    {
        return [
            Hero::class,
            Banners::class,
            FeaturedItems::class,
            FeaturedLocations::class,
        ];
    }

    /**
     * @return list<PageView>
     */
    public static function pageViewBlocks(): array
    {
        $blocks = [];
        foreach (self::PAGE_VIEWS as $pageCode => [$code, $langKey, $icon]) {
            $blocks[] = PageView::make($code, $pageCode, 'igniterlabs.storefront::default.builder.'.$langKey, $icon);
        }

        return $blocks;
    }

    /**
     * Buildable page config for every page view, for a theme's meta/builder.php.
     * Each page requires its page view block and defaults to one full width
     * section holding it, so the page looks as it did before the builder.
     *
     * @param array<string, string> $labels Page labels keyed by page code
     * @param list<string> $except Page codes the theme builds from its own blocks
     */
    public static function pageViewPages(array $labels = [], array $except = []): array
    {
        $pages = [];
        foreach (self::PAGE_VIEWS as $pageCode => [$code]) {
            if (in_array($pageCode, $except, true)) {
                continue;
            }

            $id = str_replace('.', '_', $pageCode);
            $pages[$pageCode] = [
                'label' => $labels[$pageCode] ?? self::pageLabel($pageCode),
                'required' => [$code],
                'default' => [
                    [
                        'id' => 's_'.$id,
                        'layout' => 'cols-1',
                        'settings' => ['container' => 'full', 'spacing' => 'none'],
                        'columns' => [['blocks' => [['id' => 'b_'.$id, 'code' => $code]]]],
                    ],
                ],
            ];
        }

        return $pages;
    }
}
