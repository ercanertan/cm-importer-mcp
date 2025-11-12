# CDP Project - Complete TODO List

**Last Updated:** 2025-11-12
**Total Tasks:** 50
**Status:** Ready to Begin

This is the complete implementation checklist for the B2B Multi-Org CDP with Campaign Monitor integration.

---

## Phase 1: Foundation (Weeks 1-2) - 6 tasks

### Database & Models
- [ ] 1. Design database schema for tier/subscription system (tiers, products, organization_tier, user_product_subscription tables)
- [ ] 2. Create migrations for tier system (tiers table with free/pro/enterprise)
- [ ] 3. Create migrations for products system (products, product_tier, user_product_subscription tables)
- [ ] 4. Create Tier model with organization relationships
- [ ] 5. Create Product model with tier/user relationships
- [ ] 6. Build user product opt-in/opt-out system (subscription management)

---

## Phase 2: Event & Activity Tracking (Weeks 3-4) - 2 tasks

### Behavioral Data
- [ ] 7. Design event tracking system for user behavioral data (events, user_events, event_attendance tables)
- [ ] 8. Create user activity scoring system (track engagement, calculate activity scores for 7d and 30d)

---

## Phase 3: Segmentation Engine (Weeks 5-6) - 1 task

### Query Builder
- [ ] 9. Build advanced segmentation engine for complex queries (e.g., event attendance + activity filters)

---

## Phase 4: CM API Integration (Weeks 7-9) - 16 tasks

### Core Integration
- [ ] 10. Integrate Campaign Monitor API client (use official createsend-php package)
- [ ] 11. Design HYBRID CM field mapping strategy (10 persistent fields + 2 dynamic tag fields)

### Persistent Sync
- [ ] 12. Build persistent field sync service (CDP → CM) for tier_name, activity_scores, org_id, etc. with delta sync
- [ ] 13. Create background job for tier change sync (batched, non-blocking queue job)
- [ ] 14. Create daily activity score update job (delta sync only changed users)

### Registration Sync
- [ ] 15. Create new user registration sync job (AddSubscriberToCm with duplicate handling and retry logic)
- [ ] 16. Add GDPR ConsentToTrack handling in registration and CM sync

### Dynamic Tags
- [ ] 17. Build dynamic tag service for complex one-time campaigns (temp_campaign_tag field)
- [ ] 18. Build TagAndSendCampaign job for complex segmented campaigns (tag users, create segment, send, track)
- [ ] 19. Create tag cleanup job (CleanupCampaignTag - clear temp_campaign_tag after campaign sends, delete CM segment)

### Webhooks & Activity
- [ ] 20. Create CM webhook handler for receiving subscriber events (subscribe/unsubscribe/bounce)
- [ ] 21. Build email activity tracking (opens, clicks, bounces from CM webhooks and API polling)

### Segment Management
- [ ] 22. Create CM persistent segment management system (create tier-based segments in CM)
- [ ] 23. Create CM dynamic segment system (create temp segments using tags for ad-hoc campaigns)
- [ ] 24. Build campaign decision logic (route to persistent segment vs dynamic tag based on query complexity)

### Queue System
- [ ] 25. Design queue system for CM sync operations (separate queues: cm-sync, cm-campaigns, cm-cleanup, webhooks, default)

---

## Phase 5: Campaign Management (Weeks 10-11) - 10 tasks

### Recurring Campaigns
- [ ] 26. Create scheduled campaign system (daily/weekly/monthly recurring campaigns via Laravel scheduler)
- [ ] 27. Build SendRecurringCampaign job for tier-based recurring campaigns (daily for Pro, weekly for Free, etc.)

### Complex Campaigns
- [ ] 28. Build segment preview feature (show first 10-50 users before sending campaign)
- [ ] 29. Add campaign completion notifications (notify admin when complex campaign sent successfully)

### UI Development
- [ ] 30. Build admin dashboard for tier/product management
- [ ] 31. Create user dashboard for product opt-in/opt-out management
- [ ] 32. Build segment builder UI for complex filtering (visual query builder with persistent vs dynamic logic)
- [ ] 33. Create campaign management interface (create segments, preview, route to persistent/dynamic, send to CM)

### Infrastructure
- [ ] 34. Implement error handling and retry logic for CM API failures (exponential backoff, 3 tries)
- [ ] 35. Build sync monitoring dashboard (track sync status, errors, queue health, API usage)

---

## Phase 6: Optimization & Launch (Week 12) - 15 tasks

### Performance
- [ ] 36. Add database indexes for performance (user queries, activity scores, tier lookups, events)
- [ ] 37. Implement caching strategy (Redis cache for segments, user counts, activity scores)

### Testing
- [ ] 38. Create automated tests for tier/product system
- [ ] 39. Create automated tests for CM hybrid sync functionality (persistent + dynamic)
- [ ] 40. Create automated tests for segmentation engine and routing logic
- [ ] 41. Create automated tests for registration sync flow (duplicate handling, retry logic)
- [ ] 42. Create automated tests for complex campaign workflow (TagAndSendCampaign job)

### Documentation
- [ ] 43. Document HYBRID CM field mapping and sync strategy (persistent vs dynamic)
- [ ] 44. Create user guide for product opt-in/opt-out flows
- [ ] 45. Create admin guide for campaign management (when to use persistent segments vs dynamic tags)
- [ ] 46. Document new user registration flow (event-driven sync with duplicate handling)
- [ ] 47. Document complex campaign workflow (Brain and Voice pattern with tag cleanup)

### Audit System
- [ ] 48. Create comprehensive audit logging system (track all tier changes, opt-ins, sync events, campaign sends)

### Deployment
- [ ] 49. Build data migration plan for existing 60K users (assign to default tier/products, initial CM sync)
- [ ] 50. Setup supervisor/queue workers configuration (3 workers for cm-sync, 2 for cm-campaigns, 1 for cm-cleanup)

---

## Quick Reference

### Critical Path (Must Do First)
1. Tasks 1-6: Foundation (tiers, products, models)
2. Task 10: CM API integration
3. Task 11: Field mapping strategy
4. Task 15: Registration sync
5. Task 26-27: Recurring campaigns

### Can Be Done in Parallel
- Phase 2 (Events) can start after Phase 1 models are done
- Phase 3 (Segmentation) can be built alongside Phase 4
- UI (tasks 30-33) can be built alongside backend jobs

### Dependencies
- Task 18 (TagAndSendCampaign) requires: 9, 10, 13, 17
- Task 27 (SendRecurringCampaign) requires: 10, 12, 22
- Task 49 (Migration) requires: all Phase 1-4 tasks complete

---

## Task Breakdown by Type

**Backend Jobs:** 15, 13, 14, 18, 19, 27
**Models/Database:** 1-6, 7
**API Integration:** 10, 12, 17, 20, 21, 22, 23
**UI/Frontend:** 30, 31, 32, 33
**Testing:** 38-42
**Documentation:** 43-47
**Infrastructure:** 25, 34, 35, 36, 37, 48, 49, 50

---

## Estimated Hours per Phase

- **Phase 1:** 40 hours (2 weeks)
- **Phase 2:** 32 hours (1.5 weeks)
- **Phase 3:** 24 hours (1 week)
- **Phase 4:** 80 hours (3 weeks)
- **Phase 5:** 64 hours (2 weeks)
- **Phase 6:** 40 hours (1.5 weeks)

**Total: ~280 hours (~12 weeks at 24 hours/week)**

---

## Related Documents

- **Project Plan:** `/Users/ee/Sites/mcp-imports/CDP_PROJECT_PLAN.md`
- **Workflows Guide:** `/Users/ee/Sites/mcp-imports/CDP_WORKFLOWS.md`

---

**Note:** This todo list is synchronized with the TodoWrite system. Any changes made here should be reflected in the system using the TodoWrite tool.
