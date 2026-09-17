-- The Projects "Upgrade" flow (modules/projects.php -> api/projects-api.php)
-- creates a new project row linked back to the past project it upgrades,
-- via upgrade_of_project_id — but that column was never added, causing:
-- "Could not find the 'upgrade_of_project_id' column of 'projects' in the
-- schema cache" when saving an upgrade.
--
-- Run this once in the Supabase SQL editor.

alter table public.projects add column if not exists upgrade_of_project_id bigint references public.projects(id);
