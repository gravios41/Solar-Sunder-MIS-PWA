-- Run this once in the Supabase SQL editor.
-- Lets an energy assessment / quotation exist BEFORE a real Customer record
-- does — capturing just the client's name (and optionally contact details)
-- until the quotation is approved, at which point the real Customer row is
-- created and these columns get linked to it via customer_id.

alter table public.energy_assessments alter column customer_id drop not null;
alter table public.energy_assessments add column if not exists client_name text;
alter table public.energy_assessments add column if not exists client_phone text;
alter table public.energy_assessments add column if not exists client_email text;
alter table public.energy_assessments add column if not exists client_address text;
-- Tracks the draft quotation created from this assessment, independently of
-- project_id (which now stays null until that quotation is approved).
alter table public.energy_assessments add column if not exists quotation_id bigint references public.quotations(id);

alter table public.quotations alter column customer_id drop not null;
alter table public.quotations add column if not exists client_name text;
alter table public.quotations add column if not exists client_phone text;
alter table public.quotations add column if not exists client_email text;
alter table public.quotations add column if not exists client_address text;
