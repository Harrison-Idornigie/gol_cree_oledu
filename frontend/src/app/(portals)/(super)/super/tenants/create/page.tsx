import TenantCreateForm from '@/components/super/tenants/TenantCreateForm';

export default function CreateTenantPage() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Create New Tenant</h1>
        <p className="text-gray-600">
          Set up a new school district or organization with their own dedicated portal
        </p>
      </div>

      {/* Form */}
      <TenantCreateForm />
    </div>
  );
}
