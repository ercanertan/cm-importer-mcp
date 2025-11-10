<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailEngagement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CmWebhookController extends Controller
{
    /**
     * Handle subscriber deactivation webhook
     * Fired when a subscriber is deactivated (bounced, unsubscribed, etc.)
     */
    public function handleDeactivate(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date', 'Events']);

        if ($validator->fails()) {
            Log::warning('CM webhook validation failed: deactivate', [
                'errors' => $validator->errors(),
                'data' => $request->all(),
            ]);
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found', [
                'event' => 'deactivate',
                'email' => $data['EmailAddress'],
                'custom_fields' => $data['CustomFields'] ?? [],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Process each event in the Events array
        foreach ($data['Events'] as $event) {
            $this->processDeactivationEvent($user, $event, $data);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle subscriber update webhook
     * Fired when subscriber details are updated
     */
    public function handleUpdate(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date']);

        if ($validator->fails()) {
            Log::warning('CM webhook validation failed: update', [
                'errors' => $validator->errors(),
                'data' => $request->all(),
            ]);
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for update', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Update user's CM status if needed
        if (isset($data['State'])) {
            $user->update(['cm_status' => strtolower($data['State'])]);
        }

        Log::info('CM webhook: subscriber updated', [
            'user_id' => $user->id,
            'email' => $data['EmailAddress'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle email open webhook
     */
    public function handleOpen(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date']);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for open', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create engagement record
        EmailEngagement::create([
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'campaign_name' => $data['CampaignName'] ?? null,
            'event_type' => 'open',
            'ip_address' => $data['IPAddress'] ?? null,
            'user_agent' => $data['UserAgent'] ?? null,
            'event_data' => $data,
            'occurred_at' => $this->parseDate($data['Date']),
        ]);

        // Update user engagement metrics
        $user->increment('total_opens');
        $user->update([
            'last_email_opened_at' => now(),
            'last_activity_at' => now(),
        ]);

        Log::info('CM webhook: email opened', [
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle email click webhook
     */
    public function handleClick(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date']);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for click', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create engagement record
        EmailEngagement::create([
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'campaign_name' => $data['CampaignName'] ?? null,
            'event_type' => 'click',
            'url' => $data['URL'] ?? null,
            'ip_address' => $data['IPAddress'] ?? null,
            'user_agent' => $data['UserAgent'] ?? null,
            'event_data' => $data,
            'occurred_at' => $this->parseDate($data['Date']),
        ]);

        // Update user engagement metrics
        $user->increment('total_clicks');
        $user->update([
            'last_email_clicked_at' => now(),
            'last_activity_at' => now(),
        ]);

        Log::info('CM webhook: link clicked', [
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'url' => $data['URL'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle bounce webhook
     */
    public function handleBounce(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date', 'Type']);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for bounce', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create engagement record
        EmailEngagement::create([
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'campaign_name' => $data['CampaignName'] ?? null,
            'event_type' => 'bounce',
            'bounce_type' => strtolower($data['Type']), // 'hard' or 'soft'
            'bounce_reason' => $data['Reason'] ?? null,
            'event_data' => $data,
            'occurred_at' => $this->parseDate($data['Date']),
        ]);

        // Update user engagement metrics
        $user->increment('total_bounces');

        // Update CM status for hard bounces
        if (strtolower($data['Type']) === 'hard') {
            $user->update([
                'cm_status' => 'bounced',
                'cm_status_changed_at' => now(),
            ]);
        }

        Log::info('CM webhook: email bounced', [
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'bounce_type' => $data['Type'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle unsubscribe webhook
     */
    public function handleUnsubscribe(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date']);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for unsubscribe', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create engagement record
        EmailEngagement::create([
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
            'campaign_name' => $data['CampaignName'] ?? null,
            'event_type' => 'unsubscribe',
            'event_data' => $data,
            'occurred_at' => $this->parseDate($data['Date']),
        ]);

        // Update user CM status
        $user->update([
            'cm_status' => 'unsubscribed',
            'cm_unsubscribed_at' => now(),
            'cm_status_changed_at' => now(),
        ]);

        Log::info('CM webhook: user unsubscribed', [
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle spam complaint webhook
     */
    public function handleSpamComplaint(Request $request)
    {
        $validator = $this->validateWebhookData($request, ['EmailAddress', 'Date']);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }

        $data = $request->all();
        $user = $this->findUserByCustomField($data);

        if (!$user) {
            Log::warning('CM webhook: user not found for spam complaint', [
                'email' => $data['EmailAddress'],
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Update user CM status
        $user->update([
            'cm_status' => 'spam_complaint',
            'cm_status_changed_at' => now(),
        ]);

        Log::warning('CM webhook: spam complaint received', [
            'user_id' => $user->id,
            'campaign_id' => $data['CampaignID'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Find user by user_id custom field (most reliable)
     * Falls back to email if custom field not found
     */
    protected function findUserByCustomField(array $data): ?User
    {
        // First, try to find by user_id custom field (most reliable)
        if (isset($data['CustomFields']) && is_array($data['CustomFields'])) {
            foreach ($data['CustomFields'] as $field) {
                if ($field['Key'] === 'user_id' && !empty($field['Value'])) {
                    $user = User::find($field['Value']);
                    if ($user) {
                        return $user;
                    }
                }
            }
        }

        // Fallback to email lookup
        if (isset($data['EmailAddress'])) {
            return User::where('email', $data['EmailAddress'])->first();
        }

        return null;
    }

    /**
     * Validate webhook data
     */
    protected function validateWebhookData(Request $request, array $requiredFields)
    {
        $rules = [];
        foreach ($requiredFields as $field) {
            $rules[$field] = 'required';
        }

        return Validator::make($request->all(), $rules);
    }

    /**
     * Parse Campaign Monitor date format
     */
    protected function parseDate(string $date)
    {
        try {
            // CM sends dates in ISO 8601 format
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            Log::error('Failed to parse CM webhook date', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            return now();
        }
    }

    /**
     * Process deactivation event
     */
    protected function processDeactivationEvent(User $user, array $event, array $data)
    {
        $eventType = $event['Event'] ?? 'unknown';

        switch (strtolower($eventType)) {
            case 'bounce':
                $this->handleBounceFromDeactivation($user, $event, $data);
                break;

            case 'unsubscribe':
                // Create engagement record
                EmailEngagement::create([
                    'user_id' => $user->id,
                    'event_type' => 'unsubscribe',
                    'event_data' => array_merge($data, $event),
                    'occurred_at' => $this->parseDate($event['Date'] ?? $data['Date']),
                ]);

                $user->update([
                    'cm_status' => 'unsubscribed',
                    'cm_unsubscribed_at' => now(),
                    'cm_status_changed_at' => now(),
                ]);
                break;

            default:
                Log::warning('Unknown deactivation event type', [
                    'event_type' => $eventType,
                    'user_id' => $user->id,
                ]);
        }
    }

    /**
     * Handle bounce from deactivation webhook
     */
    protected function handleBounceFromDeactivation(User $user, array $event, array $data)
    {
        EmailEngagement::create([
            'user_id' => $user->id,
            'event_type' => 'bounce',
            'bounce_type' => strtolower($event['Type'] ?? 'hard'),
            'bounce_reason' => $event['Reason'] ?? null,
            'event_data' => array_merge($data, $event),
            'occurred_at' => $this->parseDate($event['Date'] ?? $data['Date']),
        ]);

        $user->increment('total_bounces');

        if (strtolower($event['Type'] ?? 'hard') === 'hard') {
            $user->update([
                'cm_status' => 'bounced',
                'cm_status_changed_at' => now(),
            ]);
        }
    }
}
