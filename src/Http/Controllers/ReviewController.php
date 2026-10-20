<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\Reviews;
use Igniter\User\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Leave a review for a completed order or reservation, like the Orange LeaveReview component.
 */
class ReviewController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function store(Request $request, string $type, string $hash): mixed
    {
        $data = $request->validate([
            'comment' => ['required', 'string', 'min:2', 'max:1028'],
            'quality' => ['required', 'integer', 'between:1,5'],
            'delivery' => ['nullable', 'integer', 'between:0,5'],
            'service' => ['required', 'integer', 'between:1,5'],
        ], [
            'quality.*' => Storefront::lang('alert.rate_quality'),
            'service.*' => Storefront::lang('alert.rate_service'),
            'delivery.*' => Storefront::lang('alert.rate_delivery'),
            'comment.required' => Storefront::lang('alert.review_comment'),
        ], [
            'comment' => lang('igniter.local::default.review.label_review'),
            'quality' => lang('igniter.local::default.review.label_quality'),
            'delivery' => lang('igniter.local::default.review.label_delivery'),
            'service' => lang('igniter.local::default.review.label_service'),
        ]);

        return $this->attempt(function() use ($type, $hash, $data) {
            throw_unless(Auth::customer(), new ApplicationException(lang('igniter.local::default.review.alert_expired_login')));
            throw_unless(ReviewSettings::allowReviews(), new ApplicationException(lang('igniter.local::default.review.alert_review_disabled')));
            throw_unless(
                in_array($type, Reviews::TYPES, true) && ($reviewable = Reviews::find($type, $hash)),
                new ApplicationException(lang('igniter.local::default.review.alert_review_not_found')),
            );

            Review::leaveReview($reviewable, [
                'quality' => $data['quality'],
                'delivery' => $data['delivery'] ?? 0,
                'service' => $data['service'],
                'review_text' => $data['comment'],
            ]);

            return back()->with('success', lang('igniter.local::default.review.alert_review_success'));
        });
    }
}
