-- Task Checklist Extension
-- Add to your Supabase SQL editor

-- Task checklist items table
create table if not exists public.task_checklists (
    id uuid primary key default gen_random_uuid(),
    task_id integer not null references public.tasks(id) on delete cascade,
    checklist_item text not null,
    is_completed boolean not null default false,
    completed_by bigint references public.users(id) on delete set null,
    completed_at timestamptz,
    sequence integer not null default 0,
    created_by bigint references public.users(id) on delete set null,
    created_at timestamptz not null default now()
);

create index if not exists task_checklists_task_id_idx on public.task_checklists(task_id);
create index if not exists task_checklists_completed_idx on public.task_checklists(is_completed);

-- Add progress tracking to tasks table if not exists
alter table public.tasks add column if not exists progress_percent integer default 0;
alter table public.tasks add column if not exists checklist_count integer default 0;
alter table public.tasks add column if not exists checklist_completed integer default 0;

-- Add inventory transaction logging for audit
create table if not exists public.inventory_transactions (
    id uuid primary key default gen_random_uuid(),
    inventory_id integer not null references public.inventory(id) on delete cascade,
    transaction_type text not null check (transaction_type in ('deduction', 'addition', 'adjustment')),
    quantity_change integer not null,
    reference_type text,
    reference_id text,
    reason text,
    created_by bigint references public.users(id) on delete set null,
    created_at timestamptz not null default now()
);

create index if not exists inventory_transactions_inventory_id_idx on public.inventory_transactions(inventory_id);
create index if not exists inventory_transactions_created_at_idx on public.inventory_transactions(created_at);
