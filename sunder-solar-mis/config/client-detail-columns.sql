-- Adds the full client-detail columns (contact person, city, state,
-- pincode, GSTIN, type, status) that the Energy Assessment form and the
-- Quotation client-detail form already collect and send, but that were
-- never added to these two tables (only client_name/phone/email/address
-- were added in deferred-customer-creation.sql). Missing columns caused:
-- "Could not find the 'client_city' column of 'energy_assessments' in the
-- schema cache" when saving an assessment, and would cause the same error
-- saving a manual quotation with a City/Province/Postal Code/GSTIN filled in.
--
-- Run this once in the Supabase SQL editor.

alter table public.energy_assessments add column if not exists client_contact_person text;
alter table public.energy_assessments add column if not exists client_city text;
alter table public.energy_assessments add column if not exists client_state text;
alter table public.energy_assessments add column if not exists client_pincode text;
alter table public.energy_assessments add column if not exists client_gstin text;
alter table public.energy_assessments add column if not exists client_type text;
alter table public.energy_assessments add column if not exists client_status text;

alter table public.quotations add column if not exists client_contact_person text;
alter table public.quotations add column if not exists client_city text;
alter table public.quotations add column if not exists client_state text;
alter table public.quotations add column if not exists client_pincode text;
alter table public.quotations add column if not exists client_gstin text;
alter table public.quotations add column if not exists client_type text;
alter table public.quotations add column if not exists client_status text;
