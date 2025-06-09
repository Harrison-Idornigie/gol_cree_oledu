import { Card } from "@/components/ui/card";

export default function AdminDashboard() {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Admin Dashboard</h2>
        <p className="text-muted-foreground">
          Welcome to the admin dashboard. Manage your language learning platform here.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Learning Paths</h3>
          <p className="text-sm text-muted-foreground">
            Manage learning paths, units, and lessons
          </p>
        </Card>
        
   
        
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Users & Roles</h3>
          <p className="text-sm text-muted-foreground">
            Manage user access and permissions
          </p>
        </Card>
        
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Progress & Analytics</h3>
          <p className="text-sm text-muted-foreground">
            Track user progress and performance
          </p>
        </Card>
      </div>
    </div>
  );
}