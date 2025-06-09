export interface AuditStatistics {
  total_logs: number;
  by_status: Record<string, number>;
  by_action: Array<{
    action: string;
    count: number;
  }>;
  by_area: Array<{
    area: string;
    count: number;
  }>;
  by_user: Array<{
    user: string;
    count: number;
  }>;
  timeline: Record<string, number>;
}

export interface APIResponse<T> {
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}