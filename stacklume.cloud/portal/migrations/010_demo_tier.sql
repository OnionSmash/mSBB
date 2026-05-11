-- Allow 'demo' as a real tier on org_app_subscriptions.
-- New customer signups get auto-enrolled in every active app at tier='demo'
-- so they can poke around the product immediately, while still being
-- gated by the per-app demo policy (e.g. Compass demo = IAM domain only).

BEGIN;

ALTER TABLE org_app_subscriptions DROP CONSTRAINT IF EXISTS org_app_subscriptions_tier_check;
ALTER TABLE org_app_subscriptions ADD CONSTRAINT org_app_subscriptions_tier_check
    CHECK (tier IN ('demo','starter','growth','sentinel','enterprise','disabled'));

COMMIT;
