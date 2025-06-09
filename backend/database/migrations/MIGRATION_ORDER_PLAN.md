# Database Migration Order Plan

## Current Issues
1. Admin invites table created before tenants table
2. Many content models missing BelongsToTenant trait
3. Content tables missing tenant_id columns
4. Roles table created too late in sequence

## Recommended Migration Order

### Phase 1: Core System Infrastructure (Landlord)
```
0001_01_01_000000_create_users_table.php                    ✅ (modify to remove tenant_id initially)
0001_01_01_000001_create_cache_table.php                    ✅
0001_01_01_000002_create_jobs_table.php                     ✅
2025_01_01_000000_create_tenants_table.php                  ✅ (move earlier)
2025_01_01_000001_add_tenant_id_to_users_table.php          🆕 (new migration)
```

### Phase 2: Authentication & Authorization
```
2025_01_02_000000_create_roles_table.php                    📝 (move earlier, add tenant_id)
2025_01_02_000001_create_permissions_table.php              📝 (move earlier, add tenant_id)
2025_01_02_000002_create_role_user_table.php                📝 (move earlier)
2025_01_02_000003_create_permission_role_table.php          📝 (move earlier)
```

### Phase 3: Tenant Management
```
2025_01_03_000000_create_admin_invites_table.php            📝 (move after tenants)
2025_01_03_000001_create_tenant_settings_table.php          🆕 (new)
```

### Phase 4: Core Language Infrastructure
```
2025_03_10_000001_create_core_language_tables.php           📝 (add tenant_id)
2025_03_10_000002_create_word_management_tables.php         📝 (add tenant_id)
```

### Phase 5: Learning Content Hierarchy
```
2025_03_11_000001_create_learning_paths_table.php           📝 (add tenant_id)
2025_03_11_000002_create_units_table.php                    📝 (add tenant_id)
2025_03_11_000003_create_topics_table.php                   📝 (add tenant_id)
2025_03_11_000004_create_lessons_table.php                  📝 (add tenant_id)
2025_03_11_000005_create_exercises_table.php                📝 (add tenant_id)
```

### Phase 6: User Progress & Gamification
```
2025_03_12_000001_create_user_progress_table.php            📝 (add tenant_id)
2025_03_12_000002_create_user_languages_table.php           📝 (add tenant_id)
2025_03_12_000003_create_gamification_tables.php            📝 (add tenant_id)
```

## Legend
✅ = Correct as-is
📝 = Needs modification
🆕 = New migration needed

## Tenant vs Landlord Separation

### Landlord Tables (System-wide)
- tenants
- system_settings
- failed_jobs
- cache
- sessions
- password_reset_tokens

### Tenant Tables (Isolated per tenant)
- users (with tenant_id)
- roles (with tenant_id)
- permissions (with tenant_id)
- languages (with tenant_id)
- learning_paths (with tenant_id)
- units (with tenant_id)
- topics (with tenant_id)
- lessons (with tenant_id)
- exercises (with tenant_id)
- words (with tenant_id)
- sentences (with tenant_id)
- user_progress (with tenant_id)
- All other content and user data

## Implementation Steps

1. **✅ Create new migration structure**
2. **✅ Add BelongsToTenant trait to models**
3. **✅ Update existing migrations with tenant_id**
4. **✅ Create tenant-specific migration runner**
5. **Test migration order in fresh environment**

## Actions Completed

### Models Updated with BelongsToTenant Trait
- ✅ Unit.php
- ✅ Topic.php
- ✅ Exercise.php
- ✅ Word.php
- ✅ Sentence.php
- ✅ Lesson.php

### Migrations Created/Updated
- ✅ Created: 2025_01_01_000002_add_tenant_id_to_users_table.php
- ✅ Updated: 2025_01_01_000001_add_tenant_id_to_tables.php (added all content tables)
- ✅ Created: TenantMigrate command for tenant-specific migrations

### Next Steps Required

1. **Reorder existing migrations** by renaming files:
   ```bash
   # Move admin invites after tenants
   mv 2025_03_08_154523_create_admin_invites_table.php 2025_01_04_000000_create_admin_invites_table.php

   # Move roles/permissions earlier
   mv 2025_03_11_071358_create_roles_table.php 2025_01_02_000000_create_roles_table.php
   mv 2025_03_11_071359_create_permissions_table.php 2025_01_02_000001_create_permissions_table.php
   mv 2025_03_11_071400_create_role_user_table.php 2025_01_02_000002_create_role_user_table.php
   mv 2025_03_11_071401_create_permission_role_table.php 2025_01_02_000003_create_permission_role_table.php
   ```

2. **Add tenant_id to fillable arrays** in all updated models

3. **Test migration order** in fresh environment

4. **Create tenant seeder** for initial data setup
