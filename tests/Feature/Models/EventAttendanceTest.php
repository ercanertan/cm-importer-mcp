<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// === EVENT ATTENDANCE MODEL TESTS ===

it('can create an event attendance', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $attendance = EventAttendance::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'registered',
    ]);

    expect($attendance)->toBeInstanceOf(EventAttendance::class)
        ->and($attendance->event_id)->toBe($event->id)
        ->and($attendance->user_id)->toBe($user->id)
        ->and($attendance->status)->toBe('registered');
});

it('auto-sets registered_at timestamp when creating', function () {
    $attendance = EventAttendance::factory()->create();

    expect($attendance->registered_at)->not->toBeNull()
        ->and($attendance->registered_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('belongs to an event', function () {
    $event = Event::factory()->create();
    $attendance = EventAttendance::factory()->create(['event_id' => $event->id]);

    expect($attendance->event)->toBeInstanceOf(Event::class)
        ->and($attendance->event->id)->toBe($event->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $attendance = EventAttendance::factory()->create(['user_id' => $user->id]);

    expect($attendance->user)->toBeInstanceOf(User::class)
        ->and($attendance->user->id)->toBe($user->id);
});

it('prevents duplicate registrations with unique constraint', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);

    // Attempt to create duplicate should throw exception
    expect(fn() => EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('auto-sets attended_at when status changes to attended', function () {
    $attendance = EventAttendance::factory()->create(['status' => 'registered']);

    expect($attendance->attended_at)->toBeNull();

    $attendance->update(['status' => 'attended']);

    expect($attendance->fresh()->attended_at)->not->toBeNull()
        ->and($attendance->fresh()->attendance_confirmed)->toBeTrue();
});

it('auto-sets cancelled_at when status changes to cancelled', function () {
    $attendance = EventAttendance::factory()->registered()->create();

    expect($attendance->cancelled_at)->toBeNull();

    $attendance->update(['status' => 'cancelled']);

    expect($attendance->fresh()->cancelled_at)->not->toBeNull();
});

// Test scopes
it('filters by status with ofStatus scope', function () {
    EventAttendance::factory()->create(['status' => 'registered']);
    EventAttendance::factory()->create(['status' => 'attended']);
    EventAttendance::factory()->create(['status' => 'registered']);

    $registered = EventAttendance::ofStatus('registered')->get();

    expect($registered)->toHaveCount(2)
        ->and($registered->every(fn($a) => $a->status === 'registered'))->toBeTrue();
});

it('filters registered attendances with registered scope', function () {
    EventAttendance::factory()->create(['status' => 'registered']);
    EventAttendance::factory()->create(['status' => 'attended']);
    EventAttendance::factory()->create(['status' => 'registered']);

    $registered = EventAttendance::registered()->get();

    expect($registered)->toHaveCount(2)
        ->and($registered->every(fn($a) => $a->status === 'registered'))->toBeTrue();
});

it('filters attended attendances with attended scope', function () {
    EventAttendance::factory()->create(['status' => 'attended']);
    EventAttendance::factory()->create(['status' => 'registered']);
    EventAttendance::factory()->create(['status' => 'attended']);

    $attended = EventAttendance::attended()->get();

    expect($attended)->toHaveCount(2)
        ->and($attended->every(fn($a) => $a->status === 'attended'))->toBeTrue();
});

it('filters cancelled attendances with cancelled scope', function () {
    EventAttendance::factory()->create(['status' => 'cancelled']);
    EventAttendance::factory()->create(['status' => 'registered']);
    EventAttendance::factory()->create(['status' => 'cancelled']);

    $cancelled = EventAttendance::cancelled()->get();

    expect($cancelled)->toHaveCount(2)
        ->and($cancelled->every(fn($a) => $a->status === 'cancelled'))->toBeTrue();
});

it('filters no-shows with noShow scope', function () {
    EventAttendance::factory()->create(['status' => 'no_show']);
    EventAttendance::factory()->create(['status' => 'attended']);
    EventAttendance::factory()->create(['status' => 'no_show']);

    $noShows = EventAttendance::noShow()->get();

    expect($noShows)->toHaveCount(2)
        ->and($noShows->every(fn($a) => $a->status === 'no_show'))->toBeTrue();
});

it('filters confirmed attendances with confirmed scope', function () {
    EventAttendance::factory()->create(['attendance_confirmed' => true]);
    EventAttendance::factory()->create(['attendance_confirmed' => false]);
    EventAttendance::factory()->create(['attendance_confirmed' => true]);

    $confirmed = EventAttendance::confirmed()->get();

    expect($confirmed)->toHaveCount(2)
        ->and($confirmed->every(fn($a) => $a->attendance_confirmed === true))->toBeTrue();
});

it('filters by event with forEvent scope', function () {
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();

    EventAttendance::factory()->create(['event_id' => $event1->id]);
    EventAttendance::factory()->create(['event_id' => $event2->id]);
    EventAttendance::factory()->create(['event_id' => $event1->id]);

    $event1Attendances = EventAttendance::forEvent($event1->id)->get();

    expect($event1Attendances)->toHaveCount(2)
        ->and($event1Attendances->every(fn($a) => $a->event_id === $event1->id))->toBeTrue();
});

it('filters by user with forUser scope', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    EventAttendance::factory()->create(['user_id' => $user1->id]);
    EventAttendance::factory()->create(['user_id' => $user2->id]);
    EventAttendance::factory()->create(['user_id' => $user1->id]);

    $user1Attendances = EventAttendance::forUser($user1->id)->get();

    expect($user1Attendances)->toHaveCount(2)
        ->and($user1Attendances->every(fn($a) => $a->user_id === $user1->id))->toBeTrue();
});

it('filters registrations between dates with registeredBetween scope', function () {
    EventAttendance::factory()->create(['registered_at' => now()->subDays(5)]);
    EventAttendance::factory()->create(['registered_at' => now()->subDays(15)]);
    EventAttendance::factory()->create(['registered_at' => now()->subDays(25)]);

    $attendances = EventAttendance::registeredBetween(now()->subDays(10), now())->get();

    expect($attendances)->toHaveCount(1);
});

// Test helper methods
it('checks if user attended with hasAttended method', function () {
    $attended = EventAttendance::factory()->create(['status' => 'attended']);
    $registered = EventAttendance::factory()->create(['status' => 'registered']);

    expect($attended->hasAttended())->toBeTrue()
        ->and($registered->hasAttended())->toBeFalse();
});

it('checks if registration is active with isActive method', function () {
    $active = EventAttendance::factory()->create(['status' => 'registered']);
    $cancelled = EventAttendance::factory()->create(['status' => 'cancelled']);

    expect($active->isActive())->toBeTrue()
        ->and($cancelled->isActive())->toBeFalse();
});

it('checks if registration is cancelled with isCancelled method', function () {
    $cancelled = EventAttendance::factory()->create(['status' => 'cancelled']);
    $registered = EventAttendance::factory()->create(['status' => 'registered']);

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($registered->isCancelled())->toBeFalse();
});

it('checks if marked as no-show with isNoShow method', function () {
    $noShow = EventAttendance::factory()->create(['status' => 'no_show']);
    $attended = EventAttendance::factory()->create(['status' => 'attended']);

    expect($noShow->isNoShow())->toBeTrue()
        ->and($attended->isNoShow())->toBeFalse();
});

it('marks attendance as attended with markAsAttended method', function () {
    $attendance = EventAttendance::factory()->registered()->create();

    expect($attendance->status)->toBe('registered')
        ->and($attendance->attended_at)->toBeNull()
        ->and($attendance->attendance_confirmed)->toBeFalse();

    $result = $attendance->markAsAttended();

    expect($result)->toBeTrue();

    $attendance->refresh();

    expect($attendance->status)->toBe('attended')
        ->and($attendance->attended_at)->not->toBeNull()
        ->and($attendance->attendance_confirmed)->toBeTrue();
});

it('cancels registration with cancel method', function () {
    $attendance = EventAttendance::factory()->registered()->create();

    expect($attendance->status)->toBe('registered')
        ->and($attendance->cancelled_at)->toBeNull();

    $result = $attendance->cancel();

    expect($result)->toBeTrue();

    $attendance->refresh();

    expect($attendance->status)->toBe('cancelled')
        ->and($attendance->cancelled_at)->not->toBeNull();
});

it('marks as no-show with markAsNoShow method', function () {
    $attendance = EventAttendance::factory()->registered()->create();

    expect($attendance->status)->toBe('registered');

    $result = $attendance->markAsNoShow();

    expect($result)->toBeTrue();

    $attendance->refresh();

    expect($attendance->status)->toBe('no_show');
});

it('casts dates to datetime', function () {
    $attendance = EventAttendance::factory()->create([
        'registered_at' => '2024-12-01 10:00:00',
    ]);

    expect($attendance->registered_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('casts metadata to array', function () {
    $metadata = ['question1' => 'answer1', 'dietary_restrictions' => 'vegetarian'];

    $attendance = EventAttendance::factory()->create(['metadata' => $metadata]);

    expect($attendance->metadata)->toBeArray()
        ->and($attendance->metadata)->toBe($metadata);
});

it('casts attendance_confirmed to boolean', function () {
    $attendance = EventAttendance::factory()->create(['attendance_confirmed' => true]);

    expect($attendance->attendance_confirmed)->toBeBool()
        ->and($attendance->attendance_confirmed)->toBeTrue();
});

it('cascades delete when event is deleted', function () {
    $event = Event::factory()->create();
    $attendance = EventAttendance::factory()->create(['event_id' => $event->id]);

    expect(EventAttendance::count())->toBe(1);

    $event->forceDelete(); // Force delete since Event uses soft deletes

    expect(EventAttendance::count())->toBe(0);
});

it('cascades delete when user is deleted', function () {
    $user = User::factory()->create();
    $attendance = EventAttendance::factory()->create(['user_id' => $user->id]);

    expect(EventAttendance::count())->toBe(1);

    $user->delete();

    expect(EventAttendance::count())->toBe(0);
});

it('can chain multiple scopes', function () {
    $event = Event::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Attended and confirmed (should be included)
    EventAttendance::factory()->attended()->create([
        'event_id' => $event->id,
        'user_id' => $user1->id,
        'attendance_confirmed' => true,
    ]);

    // Attended but not confirmed (should be excluded)
    EventAttendance::factory()->attended()->create([
        'event_id' => $event->id,
        'user_id' => $user2->id,
        'attendance_confirmed' => false,
    ]);

    // Registered (should be excluded)
    $user3 = User::factory()->create();
    EventAttendance::factory()->registered()->create([
        'event_id' => $event->id,
        'user_id' => $user3->id,
    ]);

    $result = EventAttendance::forEvent($event->id)
        ->attended()
        ->confirmed()
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->user_id)->toBe($user1->id);
});
