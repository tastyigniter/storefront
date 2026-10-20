<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Http\Requests\Concerns\ChecksCaptcha;
use Igniter\Storefront\Storefront;
use Illuminate\Foundation\Http\FormRequest;

class PlaceReservationRequest extends FormRequest
{
    use ChecksCaptcha;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $location = Location::currentOrDefault();
        $min = 1;
        $max = 20;
        $dateRule = [];
        if ($location instanceof LocationModel) {
            $min = max(1, (int)$location->getMinReservationGuestCount());
            $max = max($min, (int)$location->getMaxReservationGuestCount());

            // Min/Max Advance Reservation Time, as on the booking calendar.
            $minAdvance = max(0, (int)$location->getMinReservationAdvanceTime());
            $dateRule = [
                'after_or_equal:'.now()->addDays($minAdvance)->toDateString(),
                'before_or_equal:'.now()->addDays(max($minAdvance, (int)$location->getMaxReservationAdvanceTime()))->toDateString(),
            ];
        }

        return array_merge([
            'guest' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'date' => ['required', 'date_format:Y-m-d', ...$dateRule],
            'time' => ['required', 'date_format:H:i'],
            'first_name' => ['required', 'string', 'max:48'],
            'last_name' => ['required', 'string', 'max:48'],
            'email' => ['required', 'email', 'max:96'],
            'telephone' => ['required', 'string', 'max:20'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], $this->captchaRules());
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => Storefront::lang('alert.date_too_soon'),
            'date.before_or_equal' => Storefront::lang('alert.date_too_far'),
        ];
    }
}
