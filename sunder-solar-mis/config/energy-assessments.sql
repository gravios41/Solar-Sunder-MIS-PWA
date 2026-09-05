-- Energy Assessment schema for bill-based solar sizing
-- Run this in the Supabase SQL editor before opening the MIS module.

create table if not exists public.energy_assessments (
    id uuid primary key default gen_random_uuid(),
    customer_id integer not null references public.customers(id) on delete cascade,
    project_id integer references public.projects(id) on delete set null,
    average_monthly_kwh numeric(12,2) not null default 0,
    average_daily_kwh numeric(12,2) not null default 0,
    peak_sun_hours numeric(4,2) not null default 5,
    system_efficiency numeric(5,4) not null default 0.80,
    recommended_system_kw numeric(12,2) not null default 0,
    recommended_panel_count integer not null default 0,
    panel_wattage integer not null default 550,
    status text not null default 'draft' check (status in ('draft', 'verified', 'pending_approval', 'approved', 'quoted')),
    approval_status text default null check (approval_status is null or approval_status in ('pending', 'approved', 'rejected')),
    approved_by bigint references public.users(id) on delete set null,
    approved_at timestamptz,
    approval_notes text,
    created_by bigint references public.users(id) on delete set null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

-- energy_assessments may already exist from an earlier run without the approval
-- columns above — CREATE TABLE IF NOT EXISTS won't add columns to it, so add
-- them explicitly here too.
alter table public.energy_assessments add column if not exists approval_status text default null check (approval_status is null or approval_status in ('pending', 'approved', 'rejected'));
alter table public.energy_assessments add column if not exists approved_by bigint references public.users(id) on delete set null;
alter table public.energy_assessments add column if not exists approved_at timestamptz;
alter table public.energy_assessments add column if not exists approval_notes text;

create table if not exists public.energy_bill_readings (
    id uuid primary key default gen_random_uuid(),
    assessment_id uuid not null references public.energy_assessments(id) on delete cascade,
    billing_period date not null,
    consumption_kwh numeric(12,2) not null check (consumption_kwh >= 0),
    amount numeric(12,2),
    is_verified boolean not null default false,
    ocr_text text,
    file_path text,
    created_at timestamptz not null default now()
);

create index if not exists energy_assessments_customer_id_idx on public.energy_assessments(customer_id);
create index if not exists energy_bill_readings_assessment_id_idx on public.energy_bill_readings(assessment_id);

-- Recommendation items for approved assessments
create table if not exists public.recommendation_items (
    id uuid primary key default gen_random_uuid(),
    assessment_id uuid not null references public.energy_assessments(id) on delete cascade,
    inventory_id integer references public.inventory(id) on delete set null,
    item_name text not null,
    category text not null check (category in ('panel', 'inverter', 'battery', 'mounting', 'other')),
    quantity integer not null check (quantity > 0),
    estimated_unit_price numeric(12,2),
    estimated_total_price numeric(12,2),
    created_at timestamptz not null default now()
);

create index if not exists recommendation_items_assessment_id_idx on public.recommendation_items(assessment_id);
create index if not exists recommendation_items_inventory_id_idx on public.recommendation_items(inventory_id);
