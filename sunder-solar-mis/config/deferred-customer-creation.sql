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

-- The quotation is now where a not-yet-existing client's FULL details are
-- captured (mirrors every field on the Customers "Edit Customer" form) —
-- all of it becomes the real Customer record at approval time. Prefixed
-- client_ throughout so nothing collides with quotations' own `status`.
alter table public.quotations add column if not exists client_contact_person text;
alter table public.quotations add column if not exists client_city text;
alter table public.quotations add column if not exists client_state text;      -- Province
alter table public.quotations add column if not exists client_pincode text;    -- Postal Code
alter table public.quotations add column if not exists client_gstin text;
alter table public.quotations add column if not exists client_type text default 'residential';
alter table public.quotations add column if not exists client_status text default 'active';

-- Marks a project as an add-on/upgrade to a past project for the same
-- customer (e.g. adding a battery to an existing system) — used by the
-- "Add New Project" upgrade flow to find what they already have installed.
alter table public.projects add column if not exists upgrade_of_project_id bigint references public.projects(id);
