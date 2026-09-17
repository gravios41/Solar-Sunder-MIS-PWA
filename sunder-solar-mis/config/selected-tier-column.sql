-- The recommendation tier (budget/standard/luxury) picked in the live
-- preview before saving was never persisted, so the Review Recommendation
-- modal had no way to know what was already chosen and always asked again
-- from scratch. This column carries that choice through.
--
-- Run this once in the Supabase SQL editor.

alter table public.energy_assessments add column if not exists selected_tier text default 'standard';
