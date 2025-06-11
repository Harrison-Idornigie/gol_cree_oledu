export enum ActivityImpact {
  CRITICAL = 'critical',
  IMPORTANT = 'important',
  STANDARD = 'standard',
  INFO = 'info'
}

export enum ActivityCategory {
  ADMIN = 'admin',
  CONTENT = 'content',
  USER = 'user',
  SYSTEM = 'system',
  SECURITY = 'security',
  DATA = 'data'
}

export enum ActivityType {
  // Admin Actions
  ADMIN_ROLE_CHANGE = 'admin_membership_change',
  ADMIN_INVITE = 'admin_invite',
  ADMIN_REMOVE = 'admin_remove',
  
  // Content Actions
  LEARNING_PATH_CREATE = 'learning_path_create',
  LEARNING_PATH_UPDATE = 'learning_path_update',
  LEARNING_PATH_DELETE = 'learning_path_delete',
  UNIT_MODIFY = 'unit_modify',
  LESSON_MODIFY = 'lesson_modify',
   EXERCISE_MODIFY = 'exercise_modify',
  
  // User Actions
  USER_BAN = 'user_ban',
  USER_SUSPEND = 'user_suspend',
  USER_RESTORE = 'user_restore',
  PROGRESS_ADJUST = 'progress_adjust',
  
  // System Actions
  SETTINGS_CHANGE = 'settings_change',
  FEATURE_TOGGLE = 'feature_toggle',
  API_CONFIG_CHANGE = 'api_config_change',
  
  // Security Actions
  LOGIN_ATTEMPT = 'login_attempt',
  PASSWORD_CHANGE = 'password_change',
  PERMISSION_CHANGE = 'permission_change',
  
  // Data Actions
  BULK_IMPORT = 'bulk_import',
  BULK_EXPORT = 'bulk_export',
  DATA_DELETE = 'data_delete',
  BACKUP_CREATE = 'backup_create'
}

export interface ActivityMetadata {
  old_value?: unknown;
  new_value?: unknown;
  additional_info?: Record<string, unknown>;
}

export interface ActivityItem {
  id: number;
  impact: ActivityImpact;
  category: ActivityCategory;
  action: ActivityType;
  user_id: number;
  user_name: string;
  user_avatar?: string;
  target_type: string;
  target_id: number;
  target_name: string;
  metadata: ActivityMetadata;
  ip_address: string;
  created_at: string;
}

export interface ActivityFilter {
  impact?: ActivityImpact[];
  category?: ActivityCategory[];
  action?: ActivityType[];
  user_id?: number;
  target_type?: string;
  target_id?: number;
  date_from?: string;
  date_to?: string;
  search?: string;
}

export interface ActivityResponse {
  items: ActivityItem[];
  total: number;
  page: number;
  per_page: number;
  has_more: boolean;
}

// Impact level configurations
export const IMPACT_CONFIG = {
  [ActivityImpact.CRITICAL]: {
    color: 'red',
    badge: 'Critical',
    requiresConfirmation: true,
    notifyEmail: true,
    actions: [
      ActivityType.ADMIN_ROLE_CHANGE,
      ActivityType.SETTINGS_CHANGE,
      ActivityType.PERMISSION_CHANGE,
      ActivityType.DATA_DELETE
    ]
  },
  [ActivityImpact.IMPORTANT]: {
    color: 'orange',
    badge: 'Important',
    requiresConfirmation: true,
    notifyEmail: false,
    actions: [
      ActivityType.LEARNING_PATH_CREATE,
      ActivityType.LEARNING_PATH_DELETE,
      ActivityType.USER_BAN,
      ActivityType.USER_SUSPEND,
      ActivityType.BULK_IMPORT,
      ActivityType.BULK_EXPORT
    ]
  },
  [ActivityImpact.STANDARD]: {
    color: 'blue',
    badge: 'Standard',
    requiresConfirmation: false,
    notifyEmail: false,
    actions: [
      ActivityType.LEARNING_PATH_UPDATE,
      ActivityType.UNIT_MODIFY,
      ActivityType.LESSON_MODIFY,
       ActivityType.EXERCISE_MODIFY,
      ActivityType.ADMIN_INVITE
    ]
  },
  [ActivityImpact.INFO]: {
    color: 'green',
    badge: 'Info',
    requiresConfirmation: false,
    notifyEmail: false,
    actions: [
      ActivityType.LOGIN_ATTEMPT,
      ActivityType.BULK_EXPORT,
      ActivityType.PROGRESS_ADJUST
    ]
  }
} as const;