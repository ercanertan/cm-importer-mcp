<?php

namespace Database\Factories;

use App\Models\EmailEngagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailEngagement>
 */
class EmailEngagementFactory extends Factory
{
    protected $model = EmailEngagement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = $this->faker->randomElement(['sent', 'open', 'click', 'bounce', 'unsubscribe']);

        return [
            'user_id' => User::factory(),
            'campaign_id' => 'cm-' . $this->faker->unique()->numerify('campaign-####'),
            'campaign_name' => $this->faker->words(3, true) . ' Campaign',
            'event_type' => $eventType,
            'url' => $eventType === 'click' ? $this->faker->url() : null,
            'bounce_type' => $eventType === 'bounce' ? $this->faker->randomElement(['hard', 'soft']) : null,
            'bounce_reason' => $eventType === 'bounce' ? $this->faker->sentence() : null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'event_data' => [
                'raw_event' => $this->faker->word(),
                'timestamp' => now()->toIso8601String(),
            ],
            'occurred_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the engagement is an open event.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'open',
            'url' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
        ]);
    }

    /**
     * Indicate that the engagement is a click event.
     */
    public function click(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'click',
            'url' => $this->faker->url(),
            'bounce_type' => null,
            'bounce_reason' => null,
        ]);
    }

    /**
     * Indicate that the engagement is a bounce event.
     */
    public function bounce(string $type = 'hard'): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'bounce',
            'bounce_type' => $type,
            'bounce_reason' => $this->faker->randomElement([
                'Mailbox does not exist',
                'Domain does not exist',
                'Mailbox full',
                'Message too large',
                'Spam detected',
            ]),
            'url' => null,
        ]);
    }

    /**
     * Indicate that the engagement is a hard bounce.
     */
    public function hardBounce(): static
    {
        return $this->bounce('hard');
    }

    /**
     * Indicate that the engagement is a soft bounce.
     */
    public function softBounce(): static
    {
        return $this->bounce('soft');
    }

    /**
     * Indicate that the engagement is a sent event.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'sent',
            'url' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
        ]);
    }

    /**
     * Indicate that the engagement is an unsubscribe event.
     */
    public function unsubscribe(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'unsubscribe',
            'url' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
        ]);
    }

    /**
     * Set the engagement for a specific campaign.
     */
    public function forCampaign(string $campaignId, ?string $campaignName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName ?? $attributes['campaign_name'],
        ]);
    }

    /**
     * Set the engagement to have occurred at a specific time.
     */
    public function occurredAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $date,
        ]);
    }
}
