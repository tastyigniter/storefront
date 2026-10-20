<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;

beforeEach(function(): void {
    $this->location = currentLocation(['permalink_slug' => 'soho']);
    ReviewSettings::set('allow_reviews', 1);
});

function review(int $locationId, array $attributes = []): void
{
    static $id = 0;
    $id++;

    Review::query()->insert(array_merge([
        'location_id' => $locationId,
        'reviewable_type' => 'orders',
        'reviewable_id' => $id,
        'customer_id' => $id,
        'author' => 'Ann Baker',
        'quality' => 5,
        'delivery' => 4,
        'service' => 3,
        'review_text' => 'Lovely',
        'review_status' => 1,
        'created_at' => now()->subDays($id),
        'updated_at' => now(),
    ], $attributes));
}

it('lists approved reviews newest first with rating averages', function(): void {
    review($this->location->getKey(), ['author' => 'Ann Baker', 'review_text' => 'Newest']);
    review($this->location->getKey(), ['author' => 'Cher', 'quality' => 3, 'delivery' => 0, 'service' => 5, 'review_text' => 'Older']);
    review($this->location->getKey(), ['review_status' => 0, 'review_text' => 'Hidden']);

    $this->getJson(route('igniter.theme.locations.reviews', ['slug' => 'soho']))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('nextPage', null)
        ->assertJsonPath('averages', ['quality' => 4, 'delivery' => 4, 'service' => 4])
        ->assertJsonPath('reviews.0.author', 'Ann B.')
        ->assertJsonPath('reviews.0.comment', 'Newest')
        ->assertJsonPath('reviews.0.rating', 4)
        ->assertJsonPath('reviews.1.author', 'Cher')
        ->assertJsonPath('reviews.1.rating', 4);
});

it('pages through the reviews', function(): void {
    foreach (range(1, 12) as $ignored) {
        review($this->location->getKey());
    }

    $this->getJson(route('igniter.theme.locations.reviews', ['slug' => 'soho']))
        ->assertJsonCount(10, 'reviews')
        ->assertJsonPath('nextPage', 2);

    $this->getJson(route('igniter.theme.locations.reviews', ['slug' => 'soho', 'page' => 2]))
        ->assertJsonCount(2, 'reviews')
        ->assertJsonPath('nextPage', null);
});

it('is not found when reviews are off or the location is unknown', function(): void {
    $this->getJson(route('igniter.theme.locations.reviews', ['slug' => 'nowhere']))->assertNotFound();

    ReviewSettings::set('allow_reviews', 0);

    $this->getJson(route('igniter.theme.locations.reviews', ['slug' => 'soho']))->assertNotFound();
});
