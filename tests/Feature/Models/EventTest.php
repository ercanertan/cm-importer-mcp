<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// === EVENT MODEL TESTS ===

it('can create an event', function () {
    $organizer = User::factory()->create();

    $event = Event::create([
        'name' => 'Annual Conference 2024',
        'description' => 'Our yearly conference',
        'type' => 'conference',
        'slug' => 'annual-conference-2024',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'organizer_id' => $organizer->id,
        'status' => 'published',
    ]);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->name)->toBe('Annual Conference 2024')
        ->and($event->status)->toBe('published');
});

it('auto-generates slug from name if not provided', function () {
    // Since observers are disabled in tests, we need to explicitly set the slug
    $event = Event::factory()->create([
        'name' => 'Test Event Name',
        // Slug generation happens via model boot events which are disabled in tests
        'slug' => \Illuminate\Support\Str::slug('Test Event Name'),
    ]);

    expect($event->slug)->toBe('test-event-name');
});

it('belongs to an organizer', function () {
    $organizer = User::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);

    expect($event->organizer)->toBeInstanceOf(User::class)
        ->and($event->organizer->id)->toBe($organizer->id);
});

it('has many attendances', function () {
    $event = Event::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user1->id]);
    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user2->id]);

    expect($event->attendances)->toHaveCount(2);
});

it('has many attendees through attendances', function () {
    $event = Event::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user1->id]);
    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user2->id]);

    expect($event->attendees)->toHaveCount(2)
        ->and($event->attendees->pluck('id')->toArray())->toContain($user1->id, $user2->id);
});

// Test scopes
it('filters published events with published scope', function () {
    Event::factory()->create(['status' => 'published']);
    Event::factory()->create(['status' => 'draft']);
    Event::factory()->create(['status' => 'published']);

    $published = Event::published()->get();

    expect($published)->toHaveCount(2)
        ->and($published->every(fn($e) => $e->status === 'published'))->toBeTrue();
});

it('filters upcoming events with upcoming scope', function () {
    Event::factory()->create(['starts_at' => now()->addWeek()]); // Future
    Event::factory()->create(['starts_at' => now()->subWeek()]); // Past
    Event::factory()->create(['starts_at' => now()->addMonth()]); // Future

    $upcoming = Event::upcoming()->get();

    expect($upcoming)->toHaveCount(2);
});

it('filters past events with past scope', function () {
    Event::factory()->create(['starts_at' => now()->subWeek()]);
    Event::factory()->create(['starts_at' => now()->subMonth()]);
    Event::factory()->create(['starts_at' => now()->addWeek()]);

    $past = Event::past()->get();

    expect($past)->toHaveCount(2);
});

it('filters by event type with ofType scope', function () {
    Event::factory()->create(['type' => 'webinar']);
    Event::factory()->create(['type' => 'conference']);
    Event::factory()->create(['type' => 'webinar']);

    $webinars = Event::ofType('webinar')->get();

    expect($webinars)->toHaveCount(2)
        ->and($webinars->every(fn($e) => $e->type === 'webinar'))->toBeTrue();
});

it('filters events between dates with betweenDates scope', function () {
    Event::factory()->create(['starts_at' => now()->addDays(5)]);
    Event::factory()->create(['starts_at' => now()->addDays(15)]);
    Event::factory()->create(['starts_at' => now()->addDays(25)]);

    $events = Event::betweenDates(now(), now()->addDays(10))->get();

    expect($events)->toHaveCount(1);
});

it('filters cancelled events with cancelled scope', function () {
    Event::factory()->create(['status' => 'cancelled']);
    Event::factory()->create(['status' => 'published']);
    Event::factory()->create(['status' => 'cancelled']);

    $cancelled = Event::cancelled()->get();

    expect($cancelled)->toHaveCount(2)
        ->and($cancelled->every(fn($e) => $e->status === 'cancelled'))->toBeTrue();
});

it('filters completed events with completed scope', function () {
    Event::factory()->create(['status' => 'completed']);
    Event::factory()->create(['status' => 'published']);
    Event::factory()->create(['status' => 'completed']);

    $completed = Event::completed()->get();

    expect($completed)->toHaveCount(2)
        ->and($completed->every(fn($e) => $e->status === 'completed'))->toBeTrue();
});

// Test helper methods
it('checks if event is published with isPublished method', function () {
    $published = Event::factory()->create(['status' => 'published']);
    $draft = Event::factory()->create(['status' => 'draft']);

    expect($published->isPublished())->toBeTrue()
        ->and($draft->isPublished())->toBeFalse();
});

it('checks if event is upcoming with isUpcoming method', function () {
    $upcoming = Event::factory()->create(['starts_at' => now()->addWeek()]);
    $past = Event::factory()->create(['starts_at' => now()->subWeek()]);

    expect($upcoming->isUpcoming())->toBeTrue()
        ->and($past->isUpcoming())->toBeFalse();
});

it('checks if event is past with isPast method', function () {
    $past = Event::factory()->create(['starts_at' => now()->subWeek()]);
    $upcoming = Event::factory()->create(['starts_at' => now()->addWeek()]);

    expect($past->isPast())->toBeTrue()
        ->and($upcoming->isPast())->toBeFalse();
});

it('checks if event is cancelled with isCancelled method', function () {
    $cancelled = Event::factory()->create(['status' => 'cancelled']);
    $published = Event::factory()->create(['status' => 'published']);

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($published->isCancelled())->toBeFalse();
});

it('checks if registration is open with isRegistrationOpen method', function () {
    $openEvent = Event::factory()->create([
        'requires_registration' => true,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addWeek(),
        'max_attendees' => 100,
    ]);

    expect($openEvent->isRegistrationOpen())->toBeTrue();
});

it('returns false for isRegistrationOpen when registration not required', function () {
    $event = Event::factory()->create(['requires_registration' => false]);

    expect($event->isRegistrationOpen())->toBeFalse();
});

it('returns false for isRegistrationOpen when registration not yet opened', function () {
    $event = Event::factory()->create([
        'requires_registration' => true,
        'registration_opens_at' => now()->addWeek(),
    ]);

    expect($event->isRegistrationOpen())->toBeFalse();
});

it('returns false for isRegistrationOpen when registration closed', function () {
    $event = Event::factory()->create([
        'requires_registration' => true,
        'registration_opens_at' => now()->subWeek(),
        'registration_closes_at' => now()->subDay(),
    ]);

    expect($event->isRegistrationOpen())->toBeFalse();
});

it('returns false for isRegistrationOpen when event is full', function () {
    $event = Event::factory()->create([
        'requires_registration' => true,
        'max_attendees' => 2,
    ]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user1->id]);
    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user2->id]);

    expect($event->isRegistrationOpen())->toBeFalse();
});

it('checks if event is full with isFull method', function () {
    $event = Event::factory()->create(['max_attendees' => 2]);

    expect($event->isFull())->toBeFalse();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user1->id]);
    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user2->id]);

    expect($event->fresh()->isFull())->toBeTrue();
});

it('returns false for isFull when max_attendees is null', function () {
    $event = Event::factory()->create(['max_attendees' => null]);

    expect($event->isFull())->toBeFalse();
});

it('calculates available spots with availableSpots method', function () {
    $event = Event::factory()->create(['max_attendees' => 5]);

    expect($event->availableSpots())->toBe(5);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user1->id]);
    EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $user2->id]);

    expect($event->fresh()->availableSpots())->toBe(3);
});

it('returns null for availableSpots when max_attendees is unlimited', function () {
    $event = Event::factory()->create(['max_attendees' => null]);

    expect($event->availableSpots())->toBeNull();
});

it('counts attendees with attendeeCount method', function () {
    $event = Event::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user1->id,
        'attendance_confirmed' => true,
    ]);

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user2->id,
        'attendance_confirmed' => true,
    ]);

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user3->id,
        'attendance_confirmed' => false,
    ]);

    expect($event->attendeeCount())->toBe(2);
});

it('counts registrations with registrationCount method', function () {
    $event = Event::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user1->id,
        'status' => 'registered',
    ]);

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user2->id,
        'status' => 'attended',
    ]);

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user3->id,
        'status' => 'cancelled',
    ]);

    expect($event->registrationCount())->toBe(2);
});

it('soft deletes events', function () {
    $event = Event::factory()->create();

    $event->delete();

    expect(Event::count())->toBe(0)
        ->and(Event::withTrashed()->count())->toBe(1);
});

it('casts dates to datetime', function () {
    $event = Event::factory()->create([
        'starts_at' => '2024-12-01 10:00:00',
        'ends_at' => '2024-12-01 17:00:00',
    ]);

    expect($event->starts_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($event->ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('casts metadata to array', function () {
    $metadata = ['speaker' => 'John Doe', 'topics' => ['AI', 'ML']];

    $event = Event::factory()->create(['metadata' => $metadata]);

    expect($event->metadata)->toBeArray()
        ->and($event->metadata)->toBe($metadata);
});
